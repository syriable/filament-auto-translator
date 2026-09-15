<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Exceptions;

use InvalidArgumentException;

class InvalidCatalogIdException extends InvalidArgumentException
{
    public static function make(string $class, string $catalogId): self
    {
        return new self(
            "[{$class}::phraseCatalogId()] returned [{$catalogId}]. A catalog id is dotted, for example [identity.user-edit], "
            .'so it maps to lang/{locale}/identity/user-edit.php. Namespaced keys such as [identity::users.edit] are not catalog ids.'
        );
    }
}
