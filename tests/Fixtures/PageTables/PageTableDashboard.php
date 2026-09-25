<?php

declare(strict_types=1);

namespace Syriable\Translation\Tests\Fixtures\PageTables;

use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Syriable\Translation\Concerns\HasPageTranslations;

/**
 * A standalone panel page with a table of its own.
 */
class PageTableDashboard extends Page implements HasTable
{
    use HasPageTranslations;
    use InteractsWithTable;

    protected static string $routePath = '/page-table-dashboard';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => [])
            ->columns([
                TextColumn::make('visits'),
            ]);
    }
}
