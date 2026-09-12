<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator;

use Syriable\Filament\Plugins\AutoTranslator\Exceptions\CatalogPrefixOverlapException;
use Syriable\Filament\Plugins\AutoTranslator\Support\NameNormalizer;

class CatalogPrefixResolver
{
    public function __construct(
        private PhraseRegistry $registry,
    ) {}

    public function idFor(string $class): string
    {
        $prefix = $this->prefixFor($class);

        return "{$prefix}.".NameNormalizer::kebabClassBasename($class);
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
            return (string) config('auto-translator.default_prefix', 'filament');
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
                throw CatalogPrefixOverlapException::make($class, $matches[$longest], $matches[$namespace]);
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
        $configured = config('auto-translator.catalog_prefixes', []);

        return [...$configured, ...$this->registry->prefixes];
    }
}
