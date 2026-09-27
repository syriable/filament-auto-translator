<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Scanning;

final readonly class ScanResult
{
    /**
     * @param  list<Finding>  $findings
     * @param  array<string, Coverage>  $coverage  by domain
     */
    public function __construct(
        public array $findings,
        public array $coverage,
    ) {}
}
