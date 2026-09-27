<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Exceptions;

use RuntimeException;

final class ParentDepthExceededException extends RuntimeException
{
    public static function atDepth(int $depth): self
    {
        return new self("Walking a component's parents exceeded the maximum depth of [{$depth}]. Raise max_parent_depth if the schema is genuinely this deep.");
    }
}
