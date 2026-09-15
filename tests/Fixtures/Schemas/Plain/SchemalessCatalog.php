<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\Schemas\Plain;

use Syriable\Filament\Plugins\AutoTranslator\Contracts\PhraseCatalog;

class SchemalessCatalog implements PhraseCatalog
{
    public static function phraseCatalogId(): string
    {
        return 'identity.schemaless';
    }
}
