<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Sync;

class PhraseSyncWrite
{
    public function __construct(
        public string $path,
        public string $key,
        public string $action,
        public string $value,
    ) {}
}
