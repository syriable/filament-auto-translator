<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Discovery;

use Syriable\MessageCatalog\Binding\MessageOverrides;
use Syriable\MessageCatalog\Exceptions\DomainPrefixOverlapException;
use Syriable\MessageCatalog\Support\NameNormalizer;

class DomainPrefixResolver
{
    public function __construct(
        private MessageOverrides $registry,
    ) {}

    public function idFor(string $class): string
    {
        $prefix = $this->prefixFor($class);
        $group = NameNormalizer::kebabClassBasename($class);

        // A prefix ending in :: names a translation namespace rather than a
        // folder, so the group follows it directly: a module keeps its copy in
        // its own lang directory instead of the application's.
        return str_ends_with($prefix, '::')
            ? $prefix.$group
            : $prefix.'.'.$group;
    }

    public function prefixFor(string $class): string
    {
        $prefixes = $this->prefixes();
        $matches = [];

        foreach ($prefixes as $namespace => $prefix) {
            if ($class === $namespace || str_starts_with($class, $namespace.'\\')) {
                $matches[$namespace] = $prefix;
            }
        }

        if ($matches === []) {
            return (string) config('messages.default_domain_prefix', 'filament');
        }

        $longest = '';

        foreach (array_keys($matches) as $namespace) {
            if (strlen($namespace) > strlen($longest)) {
                $longest = $namespace;
            }
        }

        foreach (array_keys($matches) as $namespace) {
            if ($namespace === $longest) {
                continue;
            }

            if (strlen($namespace) === strlen($longest)) {
                throw DomainPrefixOverlapException::make($class, $matches[$longest], $matches[$namespace]);
            }
        }

        return $matches[$longest];
    }

    /**
     * @return array<string, string>
     */
    public function prefixes(): array
    {
        /** @var array<string, string> $configured */
        $configured = config('messages.domain_prefixes', []);

        return [...$configured, ...$this->registry->prefixes];
    }
}
