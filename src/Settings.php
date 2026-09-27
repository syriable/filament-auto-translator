<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator;

use Syriable\FilamentAutoTranslator\Enums\MissingMessagePolicy;

/**
 * The one place configuration is read: the published config file, with
 * whatever a panel plugin registered on boot layered on top.
 */
final class Settings
{
    /**
     * @var array<string, string>
     */
    private array $prefixes = [];

    private ?MissingMessagePolicy $policy = null;

    /**
     * @param  array<string, string>  $prefixes  namespace => domain prefix
     */
    public function addDomainPrefixes(array $prefixes): void
    {
        $this->prefixes = [...$this->prefixes, ...$prefixes];
    }

    public function usePolicy(MissingMessagePolicy $policy): void
    {
        $this->policy = $policy;
    }

    public function policy(): MissingMessagePolicy
    {
        return $this->policy ?? MissingMessagePolicy::fromConfig(config('filament-auto-translator.on_missing'));
    }

    /**
     * @return array<string, string>
     */
    public function domainPrefixes(): array
    {
        $configured = config('filament-auto-translator.domain_prefixes', []);

        return [...(is_array($configured) ? array_filter($configured, is_string(...)) : []), ...$this->prefixes];
    }

    public function defaultDomainPrefix(): string
    {
        $prefix = config('filament-auto-translator.default_domain_prefix', 'filament');

        return is_string($prefix) && $prefix !== '' ? $prefix : 'filament';
    }

    /**
     * @return list<array{path: string, namespace: string}>
     */
    public function discoverPaths(): array
    {
        $entries = config('filament-auto-translator.discover_paths', []);
        $paths = [];

        foreach (is_array($entries) ? $entries : [] as $entry) {
            $path = is_array($entry) ? ($entry['path'] ?? null) : null;
            $namespace = is_array($entry) ? ($entry['namespace'] ?? null) : null;

            if (is_string($path) && is_string($namespace) && $path !== '' && $namespace !== '') {
                $paths[] = ['path' => $path, 'namespace' => $namespace];
            }
        }

        return $paths;
    }

    public function modulePath(): string
    {
        $path = config('filament-auto-translator.module_path', 'modules');

        return trim(is_string($path) ? $path : 'modules', '/\\');
    }

    public function maxParentDepth(): int
    {
        $depth = config('filament-auto-translator.max_parent_depth', 32);

        return is_int($depth) && $depth > 0 ? $depth : 32;
    }
}
