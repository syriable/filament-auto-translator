<?php

declare(strict_types=1);

namespace Syriable\Translation\Exceptions;

use InvalidArgumentException;

class InvalidMessageNameException extends InvalidArgumentException
{
    public static function forName(string $name): self
    {
        return new self("Message machine names may only contain letters, numbers, hyphens, underscores, and normalized double underscores. [{$name}] is invalid.");
    }
}
