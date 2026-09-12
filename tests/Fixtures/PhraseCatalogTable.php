<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures;

use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Livewire\Component;
use Syriable\Filament\Plugins\AutoTranslator\CatalogPrefixResolver;
use Syriable\Filament\Plugins\AutoTranslator\Contracts\PhraseCatalog;

class PhraseCatalogTable extends Component implements HasSchemas, HasTable, PhraseCatalog
{
    use InteractsWithSchemas;
    use InteractsWithTable;

    public static function phraseCatalogId(): string
    {
        return app(CatalogPrefixResolver::class)->idFor(static::class);
    }
}
