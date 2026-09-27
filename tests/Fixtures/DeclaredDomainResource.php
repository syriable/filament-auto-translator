<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures;

use Syriable\Filament\Plugins\AutoTranslator\Attributes\TranslationDomain;
use Syriable\Filament\Plugins\AutoTranslator\Concerns\HasResourceTranslations;

/**
 * A resource that names its own domain instead of taking one from the prefix map.
 */
#[TranslationDomain('identity::people')]
class DeclaredDomainResource extends ResourceChromeParent
{
    use HasResourceTranslations;
}
