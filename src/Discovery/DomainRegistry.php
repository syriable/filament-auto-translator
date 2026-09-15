<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Discovery;

/**
 * Holds the directories phrase catalogs are discovered in. Register a module
 * once — from a service provider, or from the panel plugin — never one class
 * at a time.
 */
class DomainRegistry
{
    /**
     * @var array<string, array{path: string, namespace: string}>
     */
    private array $paths = [];

    /**
     * @var array<class-string, DiscoveredDomain>|null
     */
    private ?array $memo = null;

    public function __construct(
        private DomainDiscoverer $discoverer,
    ) {}

    public function discover(string $in, string $for): self
    {
        $this->paths[$in.'|'.$for] = ['path' => $in, 'namespace' => $for];
        $this->memo = null;

        return $this;
    }

    /**
     * @return array<class-string, DiscoveredDomain>
     */
    public function catalogs(): array
    {
        return $this->memo ??= $this->resolve();
    }

    public function flush(): void
    {
        $this->memo = null;
    }

    /**
     * @return array<class-string, DiscoveredDomain>
     */
    private function resolve(): array
    {
        $catalogs = [];

        foreach ($this->definitions() as $definition) {
            foreach ($this->discoverer->discover($definition['path'], $definition['namespace']) as $class => $catalog) {
                $catalogs[$class] = $catalog;
            }
        }

        return $catalogs;
    }

    /**
     * @return array<int, array{path: string, namespace: string}>
     */
    private function definitions(): array
    {
        return [...$this->configured(), ...array_values($this->paths)];
    }

    /**
     * @return array<int, array{path: string, namespace: string}>
     */
    private function configured(): array
    {
        $entries = config('auto-translator.schema_catalog_paths', []);
        $definitions = [];

        if (! is_array($entries)) {
            return [];
        }

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $path = $entry['path'] ?? null;
            $namespace = $entry['namespace'] ?? null;

            if (! is_string($path) || ! is_string($namespace) || blank($path) || blank($namespace)) {
                continue;
            }

            $definitions[] = ['path' => $path, 'namespace' => $namespace];
        }

        return $definitions;
    }
}
