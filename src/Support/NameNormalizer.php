<?php

declare(strict_types=1);

namespace Syriable\Translation\Support;

final class NameNormalizer
{
    public static function machine(string $name): string
    {
        $normalized = str_replace('.', '__', $name);

        return strtolower($normalized);
    }

    public static function isValid(string $name): bool
    {
        if ($name === '') {
            return true;
        }

        return (bool) preg_match('/^[a-z0-9]+(?:[_-][a-z0-9]+)*(?:__[a-z0-9]+(?:[_-][a-z0-9]+)*)*$/', $name);
    }

    public static function kebabClassBasename(string $class): string
    {
        $basename = class_basename($class);

        return str($basename)->kebab()->toString();
    }
}
