<?php

declare(strict_types=1);

use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Panel;
use Filament\PanelRegistry;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Support\Facades\File;
use Livewire\LivewireServiceProvider;
use Syriable\Translation\Binding\MessageBinder;
use Syriable\Translation\Binding\MessageOverrides;
use Syriable\Translation\Catalog\CatalogWriter;
use Syriable\Translation\Enums\MessageSlot;
use Syriable\Translation\Extraction\MessageExtractor;
use Syriable\Translation\Extraction\MessageScanner;
use Syriable\Translation\Tests\Fixtures\PageTables\PageTableDashboard;
use Syriable\Translation\Tests\Fixtures\PageTables\PageTableIndex;
use Syriable\Translation\Tests\Fixtures\PageTables\PageTableResource;

/**
 * A page that builds a table in its own table() method binds that table's
 * copy at runtime through the page's catalog. Extraction has to walk the same
 * table, or it never writes those keys and prunes any written by hand.
 */
beforeEach(function () {
    foreach ([
        LivewireServiceProvider::class,
        SupportServiceProvider::class,
        ActionsServiceProvider::class,
        FormsServiceProvider::class,
        InfolistsServiceProvider::class,
        NotificationsServiceProvider::class,
        SchemasServiceProvider::class,
        TablesServiceProvider::class,
        WidgetsServiceProvider::class,
        FilamentServiceProvider::class,
    ] as $provider) {
        $this->app->register($provider);
    }

    config()->set('translations.default_domain_prefix', 'filament');
    config()->set('translations.domain_prefixes', []);
    app(MessageOverrides::class)->mode = null;

    $this->langPath = sys_get_temp_dir().'/translations-page-tables-'.uniqid('', true);
    File::ensureDirectoryExists($this->langPath);
    app()->useLangPath($this->langPath);

    app(PanelRegistry::class)->register(
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
    return array_column(app(MessageScanner::class)->audit('en'), 'key');
}

it('walks the table a resource page builds itself into the resource catalog', function () {
    expect(pageTableKeys())
        ->toContain('filament/page-table-resource.table.columns.slides_count.label')
        ->toContain('filament/page-table-resource.table.record_actions.open.label')
        ->toContain('filament/page-table-resource.table.columns.title.label');
});

it('writes the key the binder reads at runtime', function () {
    $page = app(PageTableIndex::class);
    $column = TextColumn::make('slides_count');
    Table::make($page)->columns([$column]);

    $runtimeKey = app(MessageBinder::class)->explain($column, MessageSlot::Label)->key;

    expect(pageTableKeys())->toContain($runtimeKey);
});

it('walks the table a standalone panel page builds itself into its own catalog', function () {
    expect(pageTableKeys())->toContain('filament/pages/page-table-dashboard.table.columns.visits.label');
});

it('does not prune the copy of a page table', function () {
    $path = lang_path('en/filament/page-table-resource.php');
    app(CatalogWriter::class)->persist($path, [
        'table' => [
            'columns' => [
                'title' => ['label' => 'Title'],
                'slides_count' => ['label' => 'Slides'],
                'removed' => ['label' => 'Removed'],
            ],
        ],
    ]);

    app(MessageScanner::class)->audit('en');
    $writes = app(MessageExtractor::class)->pruneOrphans('en');
    $loaded = include $path;

    expect(array_column($writes, 'key'))
        ->toBe(['filament/page-table-resource.table.columns.removed.label'])
        ->and($loaded['table']['columns'])->toHaveKeys(['title', 'slides_count']);
});
