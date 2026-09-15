<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Exceptions;

use InvalidArgumentException;

class InvalidTranslationDomainException extends InvalidArgumentException
{
    public static function make(string $class, string $catalogId): self
    {
        return new self(
            "[{$class}::phraseCatalogId()] returned [{$catalogId}]. A catalog id is either dotted, for example "
            .'[identity.user-edit], mapping to lang/{locale}/identity/user-edit.php, or namespaced against a '
            .'registered translation namespace, for example [identity::user-edit], mapping to that namespace\'s '
            .'own {locale}/user-edit.php.'
        );
    }
}
