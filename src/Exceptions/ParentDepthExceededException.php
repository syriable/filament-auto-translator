<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Exceptions;

use RuntimeException;

class ParentDepthExceededException extends RuntimeException
{
    public static function make(int $depth): self
    {
        return new self("Parent walk exceeded the maximum depth of [{$depth}].");
    }
}
