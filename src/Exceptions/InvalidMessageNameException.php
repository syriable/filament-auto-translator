<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Exceptions;

use InvalidArgumentException;

final class InvalidMessageNameException extends InvalidArgumentException
{
    public static function forName(string $name): self
    {
        return new self("A message key segment may only contain lowercase letters, numbers, hyphens and underscores; [{$name}] is invalid.");
    }
}
