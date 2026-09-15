<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Exceptions;

use RuntimeException;

class MissingMessageException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("Missing required message [{$key}] for the current locale.");
    }
}
