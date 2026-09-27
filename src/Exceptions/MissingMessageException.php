<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Exceptions;

use RuntimeException;

final class MissingMessageException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("Missing required message [{$key}] in the current locale.");
    }
}
