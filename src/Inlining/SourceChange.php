<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Inlining;

use Syriable\FilamentAutoTranslator\Enums\ChangeType;

/**
 * A setter or method `auto-translator:inline` wrote into a PHP file.
 */
final readonly class SourceChange
{
    /**
     * @param  string  $target  the `make()` name, or the class that received a method
     */
    public function __construct(
        public string $path,
        public string $target,
        public string $method,
        public string $key,
        public ChangeType $type,
        public bool $dryRun,
    ) {}
}
