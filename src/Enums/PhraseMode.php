<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Enums;

enum PhraseMode: string
{
    case Inspect = 'inspect';
    case Strict = 'strict';
    case Lenient = 'lenient';

    public static function fromConfig(string $value): self
    {
        return self::tryFrom($value) ?? self::Inspect;
    }
}
