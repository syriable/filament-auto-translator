<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator;

use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseDecision;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseMode;

class PhraseResolution
{
    public function __construct(
        public PhraseIdentity $identity,
        public string $key,
        public PhraseDecision $decision,
        public ?string $text = null,
        public string $locale = '',
        public bool $presentInCurrentLocale = false,
        public bool $presentInFallbackLocale = false,
        public PhraseMode $mode = PhraseMode::Inspect,
        public string $reason = '',
    ) {}

    public function isBound(): bool
    {
        return $this->decision === PhraseDecision::Bound
            || $this->decision === PhraseDecision::UsedFallback;
    }
}
