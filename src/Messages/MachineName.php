<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Messages;

/**
 * The rules a machine name follows to become one segment of a message key.
 */
final class MachineName
{
    /**
     * Lowercases the name and turns dots into `__`, so a relationship name
     * such as `author.name` stays one segment instead of nesting the key.
     */
    public static function normalize(string $name): string
    {
        return strtolower(str_replace('.', '__', $name));
    }

    public static function isValid(string $name): bool
    {
        if ($name === '') {
            return true;
        }

        return preg_match('/^[a-z0-9]+(?:[_-][a-z0-9]+)*(?:__[a-z0-9]+(?:[_-][a-z0-9]+)*)*$/', $name) === 1;
    }

    /**
     * The machine name a `make()` argument stands for, or null when the
     * argument is visible copy rather than an identifier.
     */
    public static function fromArgument(mixed $argument): ?string
    {
        if (! is_string($argument) || $argument === '') {
            return null;
        }

        $name = self::normalize($argument);

        return self::isValid($name) ? $name : null;
    }

    /**
     * `EditUser` becomes `edit-user`.
     */
    public static function ofClass(string $class): string
    {
        return str(class_basename($class))->kebab()->toString();
    }
}
