<?php

declare(strict_types=1);

namespace Syriable\Translation\Tests\Fixtures\PageTables;

use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Syriable\Translation\Concerns\HasPageTranslations;

/**
 * A resource page with a table of its own, built in an instance method.
 */
class PageTableIndex extends Page implements HasTable
{
    use HasPageTranslations;
    use InteractsWithTable;

    protected static string $resource = PageTableResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => [])
            ->columns([
                TextColumn::make('slides_count'),
            ])
            ->recordActions([
                Action::make('open'),
            ]);
    }
}
