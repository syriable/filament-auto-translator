<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Domains;

use ReflectionClass;
use Syriable\Filament\Plugins\AutoTranslator\Attributes\TranslationDomain;
use Syriable\Filament\Plugins\AutoTranslator\Messages\MachineName;
use Syriable\Filament\Plugins\AutoTranslator\Settings;
use Throwable;

/**
 * Answers which translation domain a class belongs to.
 *
 * A class either declares its domain with `#[TranslationDomain]`, or exposes
 * a static `translationDomain()` — which the package's traits implement by
 * deriving one from the domain prefix map. Runtime binding and every console
 * walk ask this one class, so a domain means the same thing everywhere.
 */
final class DomainResolver
{
    /**
     * Declared domains by class. Attributes never change at runtime, and
     * reading them through reflection is the costly part of a lookup; a
     * derived domain is not cached, because a panel may add prefixes on boot.
     *
     * @var array<string, string|null>
     */
    private array $declared = [];

    public function __construct(
        private readonly Settings $settings,
    ) {}

    public function for(object|string $subject): ?string
    {
        $class = is_object($subject) ? $subject::class : $subject;

        if (! class_exists($class)) {
            return null;
        }

        return $this->declaredOn($class) ?? $this->fromMethod($class);
    }

    /**
     * The domain a class declares with `#[TranslationDomain]`, on itself or
     * a parent class.
     *
     * @param  class-string  $class
     */
    public function declaredOn(string $class): ?string
    {
        if (array_key_exists($class, $this->declared)) {
            return $this->declared[$class];
        }

        return $this->declared[$class] = $this->readAttribute($class);
    }

    /**
     * @param  class-string  $class
     */
    private function readAttribute(string $class): ?string
    {
        for ($reflection = new ReflectionClass($class); $reflection !== false; $reflection = $reflection->getParentClass()) {
            $attribute = $reflection->getAttributes(TranslationDomain::class)[0] ?? null;

            if ($attribute !== null) {
                return $attribute->newInstance()->domain;
            }
        }

        return null;
    }

    /**
     * `{prefix}.{class-kebab}`: App\Filament\Resources\UserResource becomes
     * `filament.user-resource`.
     */
    public function derive(string $class): string
    {
        return $this->join($this->prefixFor($class), MachineName::ofClass($class));
    }

    /**
     * `{prefix}.pages.{class-kebab}`, for a panel page outside any resource.
     */
    public function derivePage(string $class): string
    {
        return $this->join($this->prefixFor($class), 'pages.'.MachineName::ofClass($class));
    }

    /**
     * The prefix of the longest namespace in the prefix map that contains the
     * class, or the default prefix.
     */
    private function prefixFor(string $class): string
    {
        $prefixes = $this->settings->domainPrefixes();
        $matched = null;

        foreach (array_keys($prefixes) as $namespace) {
            $contains = $class === $namespace || str_starts_with($class, $namespace.'\\');

            if ($contains && ($matched === null || strlen($namespace) > strlen($matched))) {
                $matched = $namespace;
            }
        }

        return $matched === null ? $this->settings->defaultDomainPrefix() : $prefixes[$matched];
    }

    /**
     * A prefix ending in `::` names a translation namespace rather than a
     * folder, so a module keeps its copy in its own lang directory.
     */
    private function join(string $prefix, string $name): string
    {
        return str_ends_with($prefix, '::') ? $prefix.$name : $prefix.'.'.$name;
    }

    /**
     * @param  class-string  $class
     */
    private function fromMethod(string $class): ?string
    {
        if (! method_exists($class, 'translationDomain')) {
            return null;
        }

        try {
            $domain = $class::translationDomain();
        } catch (Throwable) {
            return null;
        }

        return is_string($domain) && $domain !== '' ? $domain : null;
    }
}
