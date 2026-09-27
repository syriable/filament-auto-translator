<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\PageTables;

use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Syriable\Filament\Plugins\AutoTranslator\Concerns\HasPageTranslations;

/**
 * A list page that only narrows the resource's own table, the common case of
 * overriding table() on ListRecords. It adds no copy of its own.
 */
class PageTableRecords extends ListRecords
{
    use HasPageTranslations;

    protected static string $resource = PageTableResource::class;

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query);
    }
}
