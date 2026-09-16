<?php

declare(strict_types=1);

namespace Syriable\Translation;

use Syriable\Translation\Enums\MissingMessagePolicy;
use Syriable\Translation\Enums\ResolutionOutcome;

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
}
