<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Apply;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource as FilamentResource;
use Filament\Schemas\Components\Component as SchemaComponent;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Syriable\Filament\Plugins\AutoTranslator\Audit\ActionNotificationScanner;
use Syriable\Filament\Plugins\AutoTranslator\Contracts\PhraseCatalog;
use Syriable\Filament\Plugins\AutoTranslator\Discovery\SchemaCatalog;
use Syriable\Filament\Plugins\AutoTranslator\Discovery\SchemaCatalogRegistry;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseDecision;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseSlot;
use Syriable\Filament\Plugins\AutoTranslator\PhraseBinder;
use Syriable\Filament\Plugins\AutoTranslator\Support\NameNormalizer;
use Syriable\Filament\Plugins\AutoTranslator\Sync\CatalogWalkLivewire;
use Syriable\Filament\Plugins\AutoTranslator\Sync\PhraseLangWriter;
use Throwable;

class PhrasePhpApplier
{
    public function __construct(
        private PhraseBinder $binder,
        private PhraseLangWriter $writer,
        private ComponentChainEditor $editor,
        private ClassMethodEditor $classMethods,
        private SlotMethodMap $methods,
        private ActionNotificationScanner $notifications,
        private SchemaCatalogRegistry $schemaCatalogs,
    ) {}

    /**
     * @return array<int, PhraseApplyWrite>
     */
    public function apply(?string $locale = null, bool $dryRun = false): array
    {
        $locale ??= app()->getLocale();
        $writes = [];

        if (! $this->writer->isSafeLocale($locale)) {
            return [];
        }

        $resources = $this->catalogResources();

        foreach ($resources as $resource) {
            $catalogId = $resource::phraseCatalogId();

            if (! $this->writer->isSafeCatalogId($catalogId)) {
                continue;
            }

            $owner = new CatalogWalkLivewire;
            $this->binder->setCatalogId($owner, $catalogId);

            $writes = [
                ...$writes,
                ...$this->applyResourceChrome($resource, $catalogId, $locale, $dryRun),
                ...$this->applyPageChrome($resource, $catalogId, $locale, $dryRun),
                ...$this->applyResourceForm($resource, $owner, $catalogId, $locale, $dryRun),
                ...$this->applyResourceTable($resource, $owner, $catalogId, $locale, $dryRun),
            ];
        }

        foreach ($this->schemaCatalogs->catalogs() as $catalog) {
            $writes = [...$writes, ...$this->applySchemaCatalog($catalog, $locale, $dryRun)];
        }

        return $writes;
    }

    /**
     * @return array<int, PhraseApplyWrite>
     */
    private function applySchemaCatalog(SchemaCatalog $catalog, string $locale, bool $dryRun): array
    {
        if (! $this->writer->isSafeCatalogId($catalog->catalogId)) {
            return [];
        }

        $owner = new CatalogWalkLivewire;
        $this->binder->setCatalogId($owner, $catalog->catalogId);

        try {
            $schema = $catalog->build(Schema::make($owner));
        } catch (Throwable) {
            return [];
        }

        if (! $schema instanceof Schema) {
            return [];
        }

        $file = (new ReflectionClass($catalog->class))->getFileName();

        if (! is_string($file)) {
            return [];
        }

        return $this->applyComponents(
            $schema->getComponents(),
            [$file],
            $catalog->catalogId,
            $locale,
            $dryRun,
        );
    }

    /**
     * @param  array<int, mixed>  $components
     * @param  array<int, string>  $files
     * @return array<int, PhraseApplyWrite>
     */
    public function applyComponents(array $components, array $files, string $catalogId, string $locale, bool $dryRun = false): array
    {
        $tree = $this->writer->load($this->writer->pathFor($catalogId, $locale));
        $pending = [];

        foreach ($components as $component) {
            $pending = [...$pending, ...$this->collectComponent($component, $catalogId, $tree)];
        }

        return $this->writePending($pending, $files, $dryRun);
    }

    /**
     * @param  array<int, array{method: string, key: string, static: bool, return: string}>  $methods
     * @return array<int, PhraseApplyWrite>
     */
    public function applyClassMethods(string $path, array $methods, bool $dryRun = false): array
    {
        return $this->writeChromeMethods($path, 'class', $methods, $dryRun);
    }

    /**
     * @param  class-string<FilamentResource&PhraseCatalog>  $resource
     * @return array<int, PhraseApplyWrite>
     */
    private function applyResourceChrome(string $resource, string $catalogId, string $locale, bool $dryRun): array
    {
        $file = (new ReflectionClass($resource))->getFileName();

        if (! is_string($file)) {
            return [];
        }

        return $this->writeChromeMethods(
            $file,
            (new ReflectionClass($resource))->getShortName(),
            $this->resourceChromeMethods($catalogId, $this->writer->load($this->writer->pathFor($catalogId, $locale))),
            $dryRun,
        );
    }

    /**
     * @param  class-string<FilamentResource&PhraseCatalog>  $resource
     * @return array<int, PhraseApplyWrite>
     */
    private function applyPageChrome(string $resource, string $catalogId, string $locale, bool $dryRun): array
    {
        $tree = $this->writer->load($this->writer->pathFor($catalogId, $locale));
        $writes = [];

        try {
            $pages = $resource::getPages();
        } catch (Throwable) {
            return [];
        }

        foreach ($pages as $registration) {
            $page = $this->pageClass($registration);

            if ($page === null) {
                continue;
            }

            $pageKey = NameNormalizer::kebabClassBasename($page);
            $prefix = str_replace('.', '/', $catalogId).'.pages.'.$pageKey.'.';
            $methods = [];

            foreach ([
                'title' => ['getTitle', 'string', false],
                'subheading' => ['getSubheading', '?string', false],
                'navigation_label' => ['getNavigationLabel', 'string', true],
            ] as $key => [$method, $return, $static]) {
                $value = Arr::get($tree, 'pages.'.$pageKey.'.'.$key);

                if (! is_string($value) || $value === '') {
                    continue;
                }

                $methods[] = [
                    'method' => $method,
                    'key' => $prefix.$key,
                    'static' => $static,
                    'return' => $return,
                ];
            }

            if ($methods === []) {
                continue;
            }

            $file = (new ReflectionClass($page))->getFileName();

            if (! is_string($file)) {
                continue;
            }

            $writes = [
                ...$writes,
                ...$this->writeChromeMethods(
                    $file,
                    (new ReflectionClass($page))->getShortName(),
                    $methods,
                    $dryRun,
                ),
            ];
        }

        return $writes;
    }

    /**
     * @param  array<string, mixed>  $tree
     * @return array<int, array{method: string, key: string, static: bool, return: string}>
     */
    private function resourceChromeMethods(string $catalogId, array $tree): array
    {
        $prefix = str_replace('.', '/', $catalogId).'.';
        $methods = [];

        foreach ([
            'model_label' => ['getModelLabel', 'string'],
            'plural_model_label' => ['getPluralModelLabel', 'string'],
            'plural_label' => ['getPluralLabel', '?string'],
            'navigation_label' => ['getNavigationLabel', 'string'],
            'navigation_group' => ['getNavigationGroup', '?string'],
        ] as $key => [$method, $return]) {
            $value = Arr::get($tree, $key);

            if (! is_string($value) || $value === '') {
                continue;
            }

            $methods[] = [
                'method' => $method,
                'key' => $prefix.$key,
                'static' => true,
                'return' => $return,
            ];
        }

        return $methods;
    }

    /**
     * @param  array<int, array{method: string, key: string, static: bool, return: string}>  $methods
     * @return array<int, PhraseApplyWrite>
     */
    private function writeChromeMethods(string $path, string $make, array $methods, bool $dryRun): array
    {
        if ($methods === [] || ! is_file($path) || ! $this->isWritablePath($path)) {
            return [];
        }

        $source = File::get($path);
        $updated = $source;
        $writes = [];

        foreach ($methods as $method) {
            $before = $updated;
            $updated = $this->classMethods->ensureTranslationMethod($updated, $method);
            $wrapped = "return __('{$method['key']}');";

            if (! str_contains($updated, "function {$method['method']}") || ! str_contains($updated, $wrapped)) {
                continue;
            }

            $writes[] = new PhraseApplyWrite(
                path: $path,
                make: $make,
                method: $method['method'],
                key: $method['key'],
                action: $this->chromeAction($before, $method['method'], $wrapped, $dryRun),
            );
        }

        if (! $dryRun && $updated !== $source) {
            File::put($path, $updated);
        }

        return array_values(array_filter(
            $writes,
            fn (PhraseApplyWrite $write): bool => $write->action !== 'skipped',
        ));
    }

    private function chromeAction(string $before, string $method, string $wrapped, bool $dryRun): string
    {
        if (str_contains($before, $wrapped)) {
            return 'skipped';
        }

        if (str_contains($before, 'function '.$method)) {
            return $dryRun ? 'would_update' : 'updated';
        }

        return $dryRun ? 'would_create' : 'created';
    }

    /**
     * @return class-string|null
     */
    private function pageClass(mixed $registration): ?string
    {
        if (is_string($registration) && class_exists($registration)) {
            return $registration;
        }

        if (! is_object($registration) || ! method_exists($registration, 'getPage')) {
            return null;
        }

        $page = $registration->getPage();

        if (! is_string($page) || $page === '' || ! class_exists($page)) {
            return null;
        }

        return $page;
    }

    /**
     * @param  class-string<FilamentResource&PhraseCatalog>  $resource
     * @return array<int, PhraseApplyWrite>
     */
    private function applyResourceForm(string $resource, CatalogWalkLivewire $owner, string $catalogId, string $locale, bool $dryRun): array
    {
        try {
            $schema = $resource::form(Schema::make($owner));
        } catch (Throwable) {
            return [];
        }

        return $this->applyComponents(
            $schema->getComponents(),
            $this->phpFilesBeside($resource, 'Schemas'),
            $catalogId,
            $locale,
            $dryRun,
        );
    }

    /**
     * @param  class-string<FilamentResource&PhraseCatalog>  $resource
     * @return array<int, PhraseApplyWrite>
     */
    private function applyResourceTable(string $resource, CatalogWalkLivewire $owner, string $catalogId, string $locale, bool $dryRun): array
    {
        try {
            $table = $resource::table(Table::make($owner));
        } catch (Throwable) {
            return [];
        }

        $components = [
            ...array_values($table->getColumns()),
            ...array_values($table->getFilters(withHidden: true)),
            ...$table->getRecordActions(),
            ...$table->getToolbarActions(),
            ...$table->getHeaderActions(),
            ...$table->getEmptyStateActions(),
        ];

        return $this->applyComponents(
            $components,
            $this->phpFilesBeside($resource, 'Tables'),
            $catalogId,
            $locale,
            $dryRun,
        );
    }

    /**
     * @param  array<string, mixed>  $tree
     * @return array<int, array{make: string, method: string, key: string, target: string, status: string}>
     */
    private function collectComponent(mixed $component, string $catalogId, array $tree): array
    {
        $pending = [];

        if ($component instanceof ActionGroup) {
            foreach ($component->getFlatActions() as $flat) {
                $pending = [...$pending, ...$this->collectComponent($flat, $catalogId, $tree)];
            }

            return $pending;
        }

        if (is_object($component) && $this->isNamedComponent($component)) {
            $make = $this->makeName($component);

            if ($make !== null && $make !== '') {
                foreach (PhraseSlot::cases() as $slot) {
                    $method = $this->methods->method($slot);

                    if ($method === null || ! method_exists($component, $method)) {
                        continue;
                    }

                    $resolution = $this->binder->explain($component, $slot);
                    $key = $resolution->key;

                    if (
                        $key === ''
                        || in_array($resolution->decision, [PhraseDecision::NoCatalog, PhraseDecision::Unbound], true)
                    ) {
                        $key = $this->keyFromTree($tree, $catalogId, $component, $make, $slot) ?? '';
                    }

                    if ($key === '') {
                        continue;
                    }

                    $segments = $this->writer->segments($catalogId, $key);

                    if ($segments === []) {
                        continue;
                    }

                    $value = Arr::get($tree, implode('.', $segments));

                    if (! is_string($value) || $value === '') {
                        continue;
                    }

                    $pending[] = [
                        'make' => $make,
                        'method' => $method,
                        'key' => $key,
                        'target' => 'component',
                        'status' => '',
                    ];
                }
            }

            if ($component instanceof Action) {
                $pending = [...$pending, ...$this->collectActionNotifications($component, $catalogId, $tree)];
            }
        }

        if ($component instanceof SchemaComponent) {
            try {
                $childSchema = $component->getChildSchema();
            } catch (Throwable) {
                return $pending;
            }

            if ($childSchema instanceof Schema) {
                foreach ($childSchema->getComponents() as $child) {
                    $pending = [...$pending, ...$this->collectComponent($child, $catalogId, $tree)];
                }
            }
        }

        return $pending;
    }

    /**
     * @param  array<string, mixed>  $tree
     * @return array<int, array{make: string, method: string, key: string, target: string, status: string}>
     */
    private function collectActionNotifications(Action $action, string $catalogId, array $tree): array
    {
        $make = $this->makeName($action);
        $statuses = $this->notifications->statuses($action);

        if ($make === null || $make === '' || $statuses === []) {
            return [];
        }

        $pending = [];

        $this->binder->pushAction($action);

        try {
            foreach ($statuses as $status) {
                $notification = $this->notificationForStatus($status);

                if (! $notification instanceof Notification) {
                    continue;
                }

                foreach ([PhraseSlot::Title, PhraseSlot::Body] as $slot) {
                    $method = $this->methods->method($slot);

                    if ($method === null) {
                        continue;
                    }

                    $resolution = $this->binder->explain($notification, $slot);
                    $key = $resolution->key;

                    if (
                        $key === ''
                        || in_array($resolution->decision, [PhraseDecision::NoCatalog, PhraseDecision::Unbound], true)
                    ) {
                        $key = $this->notificationKeyFromTree($tree, $catalogId, $make, $status, $slot) ?? '';
                    }

                    if ($key === '') {
                        continue;
                    }

                    $segments = $this->writer->segments($catalogId, $key);

                    if ($segments === []) {
                        continue;
                    }

                    $value = Arr::get($tree, implode('.', $segments));

                    if (! is_string($value) || $value === '') {
                        continue;
                    }

                    $pending[] = [
                        'make' => $make,
                        'method' => $method,
                        'key' => $key,
                        'target' => 'notification',
                        'status' => $status,
                    ];
                }
            }
        } finally {
            $this->binder->popAction();
        }

        return $pending;
    }

    private function notificationForStatus(string $status): ?Notification
    {
        return match ($status) {
            'success' => Notification::make()->success(),
            'danger' => Notification::make()->danger(),
            'info' => Notification::make()->info(),
            'warning' => Notification::make()->warning(),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $tree
     */
    private function notificationKeyFromTree(
        array $tree,
        string $catalogId,
        string $make,
        string $status,
        PhraseSlot $slot,
    ): ?string {
        $suffix = '.'.$make.'.notifications.'.$status.'.'.$slot->value;
        $matches = [];

        foreach (Arr::dot($tree) as $dot => $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }

            if (str_ends_with('.'.$dot, $suffix)) {
                $matches[] = (string) $dot;
            }
        }

        if (count($matches) !== 1) {
            return null;
        }

        return str_replace('.', '/', $catalogId).'.'.$matches[0];
    }

    /**
     * @param  array<int, array{make: string, method: string, key: string, target: string, status: string}>  $pending
     * @param  array<int, string>  $files
     * @return array<int, PhraseApplyWrite>
     */
    private function writePending(array $pending, array $files, bool $dryRun): array
    {
        $byMake = [];
        $byNotification = [];

        foreach ($pending as $item) {
            if ($item['target'] === 'notification') {
                $byNotification[$item['make']."\0".$item['status']][] = $item;

                continue;
            }

            $byMake[$item['make']][] = $item;
        }

        $writes = [];

        foreach ($files as $path) {
            if (! is_file($path) || ! $this->isWritablePath($path)) {
                continue;
            }

            $source = File::get($path);
            $updated = $source;

            foreach ($byMake as $make => $calls) {
                $before = $updated;
                $updated = $this->editor->insertAfterMake($updated, $make, $this->uniqueCalls($calls));
                $writes = [...$writes, ...$this->reportWrites($path, $make, $before, $updated, $this->uniqueCalls($calls), $dryRun)];
            }

            foreach ($byNotification as $group => $calls) {
                [$make, $status] = explode("\0", $group, 2);
                $before = $updated;
                $updated = $this->editor->insertOnNotificationMake($updated, $make, $status, $this->uniqueCalls($calls));
                $writes = [...$writes, ...$this->reportWrites($path, 'Notification', $before, $updated, $this->uniqueCalls($calls), $dryRun)];
            }

            if (! $dryRun && $updated !== $source) {
                File::put($path, $updated);
            }
        }

        return array_values(array_filter(
            $writes,
            fn (PhraseApplyWrite $write): bool => $write->action !== 'skipped',
        ));
    }

    /**
     * @param  array<int, array{method: string, key: string}>  $calls
     * @return array<int, array{method: string, key: string}>
     */
    private function uniqueCalls(array $calls): array
    {
        $unique = [];

        foreach ($calls as $call) {
            $unique[$call['method']] = [
                'method' => $call['method'],
                'key' => $call['key'],
            ];
        }

        return array_values($unique);
    }

    /**
     * @param  array<int, array{method: string, key: string}>  $calls
     * @return array<int, PhraseApplyWrite>
     */
    private function reportWrites(
        string $path,
        string $make,
        string $before,
        string $updated,
        array $calls,
        bool $dryRun,
    ): array {
        $writes = [];

        foreach ($calls as $call) {
            $wrapped = "->{$call['method']}(__('{$call['key']}'))";
            $raw = "->{$call['method']}('{$call['key']}')";

            if (! str_contains($updated, $wrapped)) {
                continue;
            }

            $writes[] = new PhraseApplyWrite(
                path: $path,
                make: $make,
                method: $call['method'],
                key: $call['key'],
                action: $this->writeAction($before, $wrapped, $raw, $dryRun),
            );
        }

        return $writes;
    }

    private function writeAction(string $before, string $wrapped, string $raw, bool $dryRun): string
    {
        if (str_contains($before, $wrapped)) {
            return 'skipped';
        }

        if (str_contains($before, $raw)) {
            return $dryRun ? 'would_update' : 'updated';
        }

        return $dryRun ? 'would_create' : 'created';
    }

    private function isNamedComponent(object $component): bool
    {
        return $component instanceof Field
            || $component instanceof Entry
            || $component instanceof Column
            || $component instanceof BaseFilter
            || $component instanceof Action;
    }

    private function makeName(object $component): ?string
    {
        if (! method_exists($component, 'getName')) {
            return null;
        }

        $name = $component->getName();

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * @param  array<string, mixed>  $tree
     */
    private function keyFromTree(array $tree, string $catalogId, object $component, string $make, PhraseSlot $slot): ?string
    {
        $scope = $this->scopePrefix($component);
        $suffix = '.'.$make.'.'.$slot->value;
        $matches = [];

        foreach (Arr::dot($tree) as $dot => $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }

            if (! str_starts_with((string) $dot, $scope.'.')) {
                continue;
            }

            if (str_ends_with('.'.$dot, $suffix)) {
                $matches[] = (string) $dot;
            }
        }

        if (count($matches) !== 1) {
            return null;
        }

        return str_replace('.', '/', $catalogId).'.'.$matches[0];
    }

    private function scopePrefix(object $component): string
    {
        if ($component instanceof Column) {
            return 'table.columns';
        }

        if ($component instanceof BaseFilter) {
            return 'table.filters';
        }

        if ($component instanceof Action) {
            try {
                if ($component->getTable() instanceof Table) {
                    return 'table';
                }
            } catch (Throwable) {
                return 'schema';
            }
        }

        return 'schema';
    }

    /**
     * @param  class-string  $resource
     * @return array<int, string>
     */
    private function phpFilesBeside(string $resource, string $folder): array
    {
        $classFile = (new ReflectionClass($resource))->getFileName();

        if (! is_string($classFile)) {
            return [];
        }

        $files = [$classFile];
        $directory = dirname($classFile).DIRECTORY_SEPARATOR.$folder;

        if (! is_dir($directory)) {
            return $files;
        }

        foreach (File::allFiles($directory) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function isWritablePath(string $path): bool
    {
        $real = realpath($path);

        if ($real === false) {
            return false;
        }

        if (! str_ends_with($real, '.php')) {
            return false;
        }

        $base = realpath(base_path());

        if (is_string($base) && str_starts_with($real, $base.DIRECTORY_SEPARATOR)) {
            return ! str_contains($real, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR);
        }

        $temp = realpath(sys_get_temp_dir());

        return is_string($temp) && str_starts_with($real, $temp);
    }

    /**
     * @return array<int, class-string<FilamentResource&PhraseCatalog>>
     */
    private function catalogResources(): array
    {
        $resources = [];

        if (! app()->bound('filament')) {
            return [];
        }

        try {
            $registered = Filament::getResources();
        } catch (Throwable) {
            return [];
        }

        foreach ($registered as $resource) {
            if (! is_subclass_of($resource, FilamentResource::class)) {
                continue;
            }

            if (! is_a($resource, PhraseCatalog::class, true)) {
                continue;
            }

            $resources[] = $resource;
        }

        return $resources;
    }
}
