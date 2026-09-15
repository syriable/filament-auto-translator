<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog;

use Syriable\MessageCatalog\Enums\MissingMessagePolicy;
use Syriable\MessageCatalog\Enums\ResolutionOutcome;

class Resolution
{
    public function __construct(
        public MessageIdentity $identity,
        public string $key,
        public ResolutionOutcome $decision,
        public ?string $text = null,
        public string $locale = '',
        public bool $presentInCurrentLocale = false,
        public bool $presentInFallbackLocale = false,
        public MissingMessagePolicy $mode = MissingMessagePolicy::Debug,
        public string $reason = '',
    ) {}

    public function isBound(): bool
    {
        return $this->decision === ResolutionOutcome::Bound
            || $this->decision === ResolutionOutcome::UsedFallback;
    }
}
