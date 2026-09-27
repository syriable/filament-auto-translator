<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Exceptions;

use InvalidArgumentException;

final class UnknownTranslationNamespaceException extends InvalidArgumentException
{
    public static function forDomain(string $domain, string $namespace): self
    {
        return new self(
            "The translation domain [{$domain}] is namespaced under [{$namespace}], but no translation namespace by that "
            ."name is registered and no module named [{$namespace}] exists. Register it with loadTranslationsFrom(\$path, '{$namespace}')."
        );
    }
}
