<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Exceptions;

use RuntimeException;

class ParentDepthExceededException extends RuntimeException
{
    public static function make(int $depth): self
    {
        return new self("Phrase parent walk exceeded the maximum depth of [{$depth}].");
    }
}
