<?php

declare(strict_types=1);

namespace Syriable\Translation\Tests\Fixtures\PageTables;

use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Syriable\Translation\Concerns\HasModelTranslations;

/**
 * A resource whose index is not a list of its records: the index page builds
 * a table of its own, and the list of records lives on another page.
 */
class PageTableResource extends Resource
{
    use HasModelTranslations;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => PageTableIndex::route('/'),
            'records' => PageTableRecords::route('/records'),
        ];
    }
}
