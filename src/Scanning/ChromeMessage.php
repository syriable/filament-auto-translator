<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Scanning;

use Syriable\FilamentAutoTranslator\Enums\Chrome;
use Syriable\FilamentAutoTranslator\Messages\MessageIdentity;

/**
 * A chrome message on a specific resource, page or cluster class.
 */
final readonly class ChromeMessage
{
    /**
     * @param  class-string  $owner  the class whose method renders it
     * @param  list<string>  $path
     */
    public function __construct(
        public Chrome $chrome,
        public string $owner,
        public string $domain,
        public array $path = [],
    ) {}

    public function identity(): MessageIdentity
    {
        return $this->chrome->identity($this->domain, $this->path);
    }
}
