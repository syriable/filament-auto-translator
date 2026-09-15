<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Tests\Fixtures;

use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Livewire\Component;
use Syriable\MessageCatalog\Contracts\PhraseCatalog;
use Syriable\MessageCatalog\Discovery\DomainPrefixResolver;

class DomainTable extends Component implements HasSchemas, HasTable, PhraseCatalog
{
    use InteractsWithSchemas;
    use InteractsWithTable;

    public static function phraseCatalogId(): string
    {
        return app(DomainPrefixResolver::class)->idFor(static::class);
    }
}
