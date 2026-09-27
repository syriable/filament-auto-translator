<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Tests\Fixtures\PageTables;

use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Syriable\FilamentAutoTranslator\Concerns\HasResourceTranslations;

/**
 * A resource whose index is not a list of its records: the index page builds
 * a table of its own, and the list of records lives on another page.
 */
class PageTableResource extends Resource
{
    use HasResourceTranslations;

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
