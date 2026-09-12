<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures;

use Syriable\Filament\Plugins\AutoTranslator\Concerns\BindsPagePhrases;
use Syriable\Filament\Plugins\AutoTranslator\Contracts\PhraseCatalog;

class CatalogBoundPage extends CatalogPageParent implements PhraseCatalog
{
    use BindsPagePhrases;
}
