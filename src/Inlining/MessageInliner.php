<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Inlining;

use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use ReflectionClass;
use Syriable\Filament\Plugins\AutoTranslator\Binding\ComponentBinder;
use Syriable\Filament\Plugins\AutoTranslator\Binding\ComponentIdentifier;
use Syriable\Filament\Plugins\AutoTranslator\Domains\DomainName;
use Syriable\Filament\Plugins\AutoTranslator\Enums\ChangeType;
use Syriable\Filament\Plugins\AutoTranslator\Enums\MessageSlot;
use Syriable\Filament\Plugins\AutoTranslator\Exceptions\UnknownTranslationNamespaceException;
use Syriable\Filament\Plugins\AutoTranslator\Extraction\LanguageFiles;
use Syriable\Filament\Plugins\AutoTranslator\Messages\Resolution;
use Syriable\Filament\Plugins\AutoTranslator\Scanning\ActionNotifications;
use Syriable\Filament\Plugins\AutoTranslator\Scanning\ChromeMessage;
use Syriable\Filament\Plugins\AutoTranslator\Scanning\Surface;
use Syriable\Filament\Plugins\AutoTranslator\Scanning\SurfaceCollector;

/**
 * The reverse of extraction: for keys that already have copy, writes the
 * explicit `->label(__('…'))` setters and chrome methods into your PHP.
 *
 * Only `::make('name')` chains in the files a surface is built from are
 * edited, and only with keys present in the language file.
 */
final class MessageInliner
{
    public function __construct(
        private readonly SurfaceCollector $surfaces,
        private readonly ComponentIdentifier $identifier,
        private readonly ComponentBinder $binder,
        private readonly ActionNotifications $notifications,
        private readonly LanguageFiles $languageFiles,
        private readonly ChainEditor $chains,
        private readonly ClassMethodEditor $methods,
        private readonly Filesystem $files,
    ) {}

    /**
     * Inlines every registered surface.
     *
     * @return list<SourceChange>
     */
    public function inline(?string $locale = null, bool $dryRun = false): array
    {
        $changes = [];

        foreach ($this->surfaces->collect() as $surface) {
            $changes = [...$changes, ...$this->inlineSurface($surface, $locale, $dryRun)];
        }

        return $changes;
    }

    /**
     * Inlines one surface, such as components you built yourself and the
     * files they are written in.
     *
     * @return list<SourceChange>
     */
    public function inlineSurface(Surface $surface, ?string $locale = null, bool $dryRun = false): array
    {
        $locale ??= app()->getLocale();

        if (! LanguageFiles::isSafeLocale($locale)) {
            return [];
        }

        $this->binder->register();
        $tree = $this->treeFor($surface->domain, $locale);

        if ($tree === null) {
            return [];
        }

        return [
            ...$this->inlineChrome($surface, $tree, $dryRun),
            ...$this->inlineComponents($surface, $tree, $dryRun),
        ];
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function treeFor(string $domain, string $locale): ?array
    {
        if (! DomainName::isValid($domain)) {
            return null;
        }

        try {
            return $this->languageFiles->load($this->languageFiles->pathFor($domain, $locale));
        } catch (UnknownTranslationNamespaceException) {
            return null;
        }
    }

    /**
     * @param  array<array-key, mixed>  $tree
     * @return list<SourceChange>
     */
    private function inlineChrome(Surface $surface, array $tree, bool $dryRun): array
    {
        $byOwner = [];

        foreach ($surface->chrome as $chrome) {
            $key = $chrome->identity()->key();

            if ($this->hasCopy($tree, $surface->domain, $key)) {
                $byOwner[$chrome->owner][] = [$chrome, $key];
            }
        }

        $changes = [];

        foreach ($byOwner as $owner => $entries) {
            $file = (string) (new ReflectionClass($owner))->getFileName();

            if (! $this->isWritable($file)) {
                continue;
            }

            $source = $updated = $this->files->get($file);

            foreach ($entries as [$chrome, $key]) {
                /** @var ChromeMessage $chrome */
                $before = $updated;
                $updated = $this->methods->ensure($updated, $chrome->chrome, $key);
                $wrapped = "return __('{$key}');";

                if (! str_contains($updated, $wrapped) || str_contains($before, $wrapped)) {
                    continue;
                }

                $type = str_contains($before, 'function '.$chrome->chrome->method()) ? ChangeType::Updated : ChangeType::Created;
                $changes[] = new SourceChange($file, class_basename($owner), $chrome->chrome->method(), $key, $type, $dryRun);
            }

            if (! $dryRun && $updated !== $source) {
                $this->files->put($file, $updated);
            }
        }

        return $changes;
    }

    /**
     * @param  array<array-key, mixed>  $tree
     * @return list<SourceChange>
     */
    private function inlineComponents(Surface $surface, array $tree, bool $dryRun): array
    {
        // name => method => key; later components with the same name win
        $onMake = [];
        // "action\0status" => method => key
        $onNotification = [];

        foreach ($surface->components as $component) {
            $name = $this->makeName($component);

            if ($name === null) {
                continue;
            }

            foreach (MessageSlot::cases() as $slot) {
                $setter = $slot->setter();

                if ($setter !== null && method_exists($component, $setter) && ($key = $this->keyWithCopy($tree, $surface->domain, $this->identifier->resolve($component, $slot))) !== null) {
                    $onMake[$name][$setter] = $key;
                }
            }

            if (! $component instanceof Action) {
                continue;
            }

            foreach ($this->notifications->of($component) as $notification) {
                foreach ([MessageSlot::Title, MessageSlot::Body] as $slot) {
                    if (($key = $this->keyWithCopy($tree, $surface->domain, $this->identifier->resolve($notification, $slot))) !== null) {
                        $onNotification[$name."\0".$notification->getStatus()][(string) $slot->setter()] = $key;
                    }
                }
            }
        }

        $changes = [];

        foreach ($surface->files as $file) {
            if (! $this->isWritable($file)) {
                continue;
            }

            $source = $updated = $this->files->get($file);

            foreach ($onMake as $name => $calls) {
                $before = $updated;
                $updated = $this->chains->onMake($updated, (string) $name, $this->calls($calls));
                $changes = [...$changes, ...$this->changes($file, (string) $name, $before, $updated, $calls, $dryRun)];
            }

            foreach ($onNotification as $group => $calls) {
                [$action, $status] = explode("\0", (string) $group, 2);
                $before = $updated;
                $updated = $this->chains->onNotification($updated, $action, $status, $this->calls($calls));
                $changes = [...$changes, ...$this->changes($file, 'Notification', $before, $updated, $calls, $dryRun)];
            }

            if (! $dryRun && $updated !== $source) {
                $this->files->put($file, $updated);
            }
        }

        return $changes;
    }

    private function makeName(object $component): ?string
    {
        if (! $component instanceof Field && ! $component instanceof Entry && ! $component instanceof Column && ! $component instanceof BaseFilter && ! $component instanceof Action) {
            return null;
        }

        $name = $component->getName();

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * @param  array<array-key, mixed>  $tree
     */
    private function keyWithCopy(array $tree, string $domain, Resolution $resolution): ?string
    {
        return $resolution->outcome->hasKey() && $this->hasCopy($tree, $domain, $resolution->key) ? $resolution->key : null;
    }

    /**
     * @param  array<array-key, mixed>  $tree
     */
    private function hasCopy(array $tree, string $domain, string $key): bool
    {
        $segments = DomainName::segmentsOf($domain, $key);
        $value = $segments === [] ? null : Arr::get($tree, implode('.', $segments));

        return is_string($value) && $value !== '';
    }

    /**
     * @param  array<string, string>  $calls
     * @return list<array{method: string, key: string}>
     */
    private function calls(array $calls): array
    {
        $list = [];

        foreach ($calls as $method => $key) {
            $list[] = ['method' => (string) $method, 'key' => $key];
        }

        return $list;
    }

    /**
     * @param  array<string, string>  $calls
     * @return list<SourceChange>
     */
    private function changes(string $file, string $target, string $before, string $updated, array $calls, bool $dryRun): array
    {
        $changes = [];

        foreach ($calls as $method => $key) {
            $wrapped = "->{$method}(__('{$key}'))";

            if (! str_contains($updated, $wrapped) || str_contains($before, $wrapped)) {
                continue;
            }

            $type = str_contains($before, "->{$method}('{$key}')") ? ChangeType::Updated : ChangeType::Created;
            $changes[] = new SourceChange($file, $target, (string) $method, $key, $type, $dryRun);
        }

        return $changes;
    }

    /**
     * Only the application's own PHP is edited: never vendor code, never a
     * file outside the project (the system temp directory aside, for tests).
     */
    private function isWritable(string $path): bool
    {
        $real = realpath($path);

        if ($real === false || ! str_ends_with($real, '.php')) {
            return false;
        }

        $base = realpath(base_path());

        if (is_string($base) && str_starts_with($real, $base.DIRECTORY_SEPARATOR)) {
            return ! str_contains($real, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR);
        }

        $temp = realpath(sys_get_temp_dir());

        return is_string($temp) && str_starts_with($real, $temp.DIRECTORY_SEPARATOR);
    }
}
