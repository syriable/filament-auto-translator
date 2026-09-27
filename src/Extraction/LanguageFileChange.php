<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Extraction;

use Syriable\Filament\Plugins\AutoTranslator\Enums\ChangeType;

/**
 * A key `auto-translator:extract` created or deleted in a language file.
 */
final readonly class LanguageFileChange
{
    public function __construct(
        public string $path,
        public string $key,
        public ChangeType $type,
        public string $value,
        public bool $dryRun,
    ) {}
}
