<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures;

use Syriable\Filament\Plugins\AutoTranslator\Attributes\TranslationDomain;
use Syriable\Filament\Plugins\AutoTranslator\Concerns\HasPageTranslations;

/**
 * A page that keeps its own domain instead of sharing its resource's.
 */
#[TranslationDomain('identity::people-edit')]
class DeclaredDomainPage extends ResourcePageParent
{
    use HasPageTranslations;
}
