<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Scanning;

use Syriable\FilamentAutoTranslator\Enums\ResolutionOutcome;
use Syriable\FilamentAutoTranslator\Messages\Resolution;

/**
 * A message the current locale does not have its own copy for.
 */
final readonly class Finding
{
    public function __construct(
        public string $key,
        public string $domain,
        public ResolutionOutcome $outcome,
        public string $locale,
        public ?string $text,
    ) {}

    public static function from(Resolution $resolution): self
    {
        return new self($resolution->key, $resolution->identity->domain, $resolution->outcome, $resolution->locale, $resolution->text);
    }
}
