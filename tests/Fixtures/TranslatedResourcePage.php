<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures;

use Syriable\Filament\Plugins\AutoTranslator\Concerns\HasPageTranslations;

class TranslatedResourcePage extends ResourcePageParent
{
    use HasPageTranslations;
}
