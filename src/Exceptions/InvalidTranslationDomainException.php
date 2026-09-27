<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Exceptions;

use InvalidArgumentException;

final class InvalidTranslationDomainException extends InvalidArgumentException
{
    public static function forClass(string $class, string $domain): self
    {
        return new self(
            "[{$class}] declares the translation domain [{$domain}]. A domain is dotted, like [identity.user-edit] "
            .'for lang/{locale}/identity/user-edit.php, optionally namespaced, like [identity::user-edit] for '
            .'{locale}/user-edit.php in the lang directory registered for [identity].'
        );
    }
}
