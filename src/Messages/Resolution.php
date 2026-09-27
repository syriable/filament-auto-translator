<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Messages;

use Syriable\Filament\Plugins\AutoTranslator\Enums\ResolutionOutcome;

/**
 * How one message resolved, and why. Returned by `AutoTranslator::explain()`.
 */
final readonly class Resolution
{
    public function __construct(
        public MessageIdentity $identity,
        public string $key,
        public ResolutionOutcome $outcome,
        public ?string $text,
        public string $locale,
        public bool $presentInCurrentLocale,
        public bool $presentInFallbackLocale,
        public string $reason,
    ) {}

    /**
     * A resolution that never reached the translator: the component has no
     * domain or no machine name.
     */
    public static function unresolved(MessageIdentity $identity, ResolutionOutcome $outcome, string $reason): self
    {
        return new self($identity, '', $outcome, null, '', false, false, $reason);
    }

    public function isPresent(): bool
    {
        return $this->outcome->isPresent();
    }
}
