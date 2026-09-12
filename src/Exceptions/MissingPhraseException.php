<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Exceptions;

use RuntimeException;

class MissingPhraseException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("Missing required phrase [{$key}] for the current locale.");
    }
}
