<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Tests\Fixtures;

use Syriable\FilamentAutoTranslator\Attributes\TranslationDomain;
use Syriable\FilamentAutoTranslator\Concerns\HasResourceTranslations;

/**
 * A resource that names its own domain instead of taking one from the prefix map.
 */
#[TranslationDomain('identity::people')]
class DeclaredDomainResource extends ResourceChromeParent
{
    use HasResourceTranslations;
}
