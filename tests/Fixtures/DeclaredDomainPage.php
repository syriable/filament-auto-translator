<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Tests\Fixtures;

use Syriable\FilamentAutoTranslator\Attributes\TranslationDomain;
use Syriable\FilamentAutoTranslator\Concerns\HasPageTranslations;

/**
 * A page that keeps its own domain instead of sharing its resource's.
 */
#[TranslationDomain('identity::people-edit')]
class DeclaredDomainPage extends ResourcePageParent
{
    use HasPageTranslations;
}
