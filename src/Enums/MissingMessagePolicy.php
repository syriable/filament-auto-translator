<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Enums;

enum MissingMessagePolicy: string
{
    case Debug = 'debug';
    case Strict = 'strict';
    case Fallback = 'fallback';

    public static function fromConfig(string $value): self
    {
        return self::tryFrom($value) ?? self::Fallback;
    }
}
