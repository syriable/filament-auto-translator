<?php

declare(strict_types=1);

use Filament\Panel;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\File;
use Syriable\FilamentAutoTranslator\AutoTranslator;
use Syriable\FilamentAutoTranslator\Enums\MessageSlot;
use Syriable\FilamentAutoTranslator\Extraction\LanguageFiles;
use Syriable\FilamentAutoTranslator\Extraction\MessageExtractor;
use Syriable\FilamentAutoTranslator\Scanning\MessageScanner;
use Syriable\FilamentAutoTranslator\Tests\Fixtures\PageTables\PageTableDashboard;
use Syriable\FilamentAutoTranslator\Tests\Fixtures\PageTables\PageTableIndex;
use Syriable\FilamentAutoTranslator\Tests\Fixtures\PageTables\PageTableResource;

/**
 * A page that builds a table in its own table() method binds that table's
 * copy at runtime through the page's domain. Extraction has to walk the same
 * table, or it never writes those keys and prunes any written by hand.
 */
beforeEach(function () {
    config()->set('filament-auto-translator.default_domain_prefix', 'filament');
    config()->set('filament-auto-translator.domain_prefixes', []);

    $this->langPath = sys_get_temp_dir().'/translations-page-tables-'.uniqid('', true);
    File::ensureDirectoryExists($this->langPath);
    app()->useLangPath($this->langPath);

    $this->registerPanel(
        Panel::make()
            ->id('dashboard')
            ->path('dashboard')
            ->resources([PageTableResource::class])
            ->pages([PageTableDashboard::class]),
    );
});

afterEach(function () {
    File::deleteDirectory($this->langPath);
});

/**
 * @return array<int, string>
 */
function pageTableKeys(): array
{
    return array_column(app(MessageScanner::class)->scan('en')->findings, 'key');
}

it('walks the table a resource page builds itself into the resource domain', function () {
    expect(pageTableKeys())
        ->toContain('filament/page-table-resource.table.columns.slides_count.label')
        ->toContain('filament/page-table-resource.table.record_actions.open.label')
        ->toContain('filament/page-table-resource.table.columns.title.label');
});

it('writes the key the binder reads at runtime', function () {
    $page = app(PageTableIndex::class);
    $column = TextColumn::make('slides_count');
    Table::make($page)->columns([$column]);

    $runtimeKey = AutoTranslator::explain($column, MessageSlot::Label)->key;

    expect(pageTableKeys())->toContain($runtimeKey);
});

it('walks the table a standalone panel page builds itself into its own domain', function () {
    expect(pageTableKeys())->toContain('filament/pages/page-table-dashboard.table.columns.visits.label');
});

it('does not prune the copy of a page table', function () {
    $path = lang_path('en/filament/page-table-resource.php');
    app(LanguageFiles::class)->write($path, [
        'table' => [
            'columns' => [
                'title' => ['label' => 'Title'],
                'slides_count' => ['label' => 'Slides'],
                'removed' => ['label' => 'Removed'],
            ],
        ],
    ]);

    $writes = app(MessageExtractor::class)->prune(app(MessageScanner::class)->scan('en'), 'en');
    $loaded = include $path;

    expect(array_column($writes, 'key'))
        ->toBe(['filament/page-table-resource.table.columns.removed.label'])
        ->and($loaded['table']['columns'])->toHaveKeys(['title', 'slides_count']);
});
