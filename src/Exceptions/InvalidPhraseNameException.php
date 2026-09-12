<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Exceptions;

use InvalidArgumentException;

class InvalidPhraseNameException extends InvalidArgumentException
{
    public static function forName(string $name): self
    {
        return new self("Phrase machine names may only contain letters, numbers, hyphens, underscores, and normalized double underscores. [{$name}] is invalid.");
    }
}
