<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures;

use Syriable\Filament\Plugins\AutoTranslator\Concerns\HasResourceTranslations;

class TranslatedResource extends ResourceChromeParent
{
    use HasResourceTranslations;
}
