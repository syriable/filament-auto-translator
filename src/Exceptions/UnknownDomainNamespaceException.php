<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Exceptions;

use InvalidArgumentException;

class UnknownDomainNamespaceException extends InvalidArgumentException
{
    public static function make(string $catalogId, string $namespace): self
    {
        return new self(
            "Catalog id [{$catalogId}] is namespaced under [{$namespace}], but no translation namespace by that name "
            ."is registered. Register it from a service provider with loadTranslationsFrom(\$path, '{$namespace}')."
        );
    }
}
