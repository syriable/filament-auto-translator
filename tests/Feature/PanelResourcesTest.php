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
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Livewire\LivewireServiceProvider;
use Syriable\Translation\Binding\MessageOverrides;
use Syriable\Translation\Discovery\PanelResources;
use Syriable\Translation\Tests\Fixtures\Clusters\PanelSettingsCluster;
use Syriable\Translation\Tests\Fixtures\PanelUserResource;
use Syriable\Translation\TranslationPlugin;

/**
 * A console command has no request, so nothing boots a panel for it. These
 * register panels the way a panel provider does and then walk them the way
 * translations:extract does — without ever serving a request.
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
});

it('boots registered panels so plugin configuration reaches a console walk', function () {
    app(PanelRegistry::class)->register(
        Panel::make()
            ->id('dashboard')
            ->path('dashboard')
            ->plugin(TranslationPlugin::make()->domainPrefixes([
                'Syriable\\Translation\\Tests\\Fixtures' => 'fixtures::',
            ])),
    );

    expect(app(MessageOverrides::class)->prefixes)->toBe([]);

    app(PanelResources::class)->boot();

    expect(app(MessageOverrides::class)->prefixes)
        ->toBe(['Syriable\\Translation\\Tests\\Fixtures' => 'fixtures::']);
});

it('collects the resources that carry a translation domain', function () {
    app(PanelRegistry::class)->register(
        Panel::make()
            ->id('dashboard')
            ->path('dashboard')
            ->resources([PanelUserResource::class]),
    );

    expect(app(PanelResources::class)->catalogs())->toBe([PanelUserResource::class]);
});

it('collects the clusters that carry a translation domain', function () {
    app(PanelRegistry::class)->register(
        Panel::make()
            ->id('dashboard')
            ->path('dashboard')
            ->discoverClusters(
                in: __DIR__.'/../Fixtures/Clusters',
                for: 'Syriable\\Translation\\Tests\\Fixtures\\Clusters',
            ),
    );

    expect(app(PanelResources::class)->clusters())->toBe([PanelSettingsCluster::class]);
});

it('leaves the current panel as it found it', function () {
    app(PanelRegistry::class)->register(Panel::make()->id('dashboard')->path('dashboard'));

    app(PanelResources::class)->boot();

    expect(Filament\Facades\Filament::getCurrentPanel())->toBeNull();
});
