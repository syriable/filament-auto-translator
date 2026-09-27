<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Tests\Fixtures;

use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Livewire\Component;
use Syriable\FilamentAutoTranslator\Domains\DomainResolver;

class DomainTable extends Component implements HasSchemas, HasTable
{
    use InteractsWithSchemas;
    use InteractsWithTable;

    public static function translationDomain(): string
    {
        return app(DomainResolver::class)->derive(static::class);
    }
}
