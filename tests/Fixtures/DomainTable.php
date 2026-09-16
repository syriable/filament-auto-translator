<?php

declare(strict_types=1);

namespace Syriable\Translation\Tests\Fixtures;

use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Livewire\Component;
use Syriable\Translation\Discovery\DomainPrefixResolver;

class DomainTable extends Component implements HasSchemas, HasTable
{
    use InteractsWithSchemas;
    use InteractsWithTable;

    public static function translationDomain(): string
    {
        return app(DomainPrefixResolver::class)->idFor(static::class);
    }
}
