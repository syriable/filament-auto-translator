<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures;

use Syriable\Filament\Plugins\AutoTranslator\Concerns\BindsPhrases;
use Syriable\Filament\Plugins\AutoTranslator\Contracts\PhraseCatalog;

class CatalogOwner extends CatalogChromeParent implements PhraseCatalog
{
    use BindsPhrases;
}
