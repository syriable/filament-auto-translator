<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Enums;

enum MissingMessagePolicy: string
{
    case Inspect = 'inspect';
    case Strict = 'strict';
    case Lenient = 'lenient';

    public static function fromConfig(string $value): self
    {
        return self::tryFrom($value) ?? self::Inspect;
    }
}
