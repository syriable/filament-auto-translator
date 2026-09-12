<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Exceptions;

use InvalidArgumentException;

class CatalogPrefixOverlapException extends InvalidArgumentException
{
    public static function make(string $namespace, string $first, string $second): self
    {
        return new self("Catalog prefixes [{$first}] and [{$second}] both match [{$namespace}]. Use non-overlapping namespace prefixes.");
    }
}
