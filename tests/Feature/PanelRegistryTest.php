<?php

declare(strict_types=1);

use Filament\Panel;
use Syriable\Filament\Plugins\AutoTranslator\AutoTranslatorPlugin;
use Syriable\Filament\Plugins\AutoTranslator\Domains\DomainResolver;
use Syriable\Filament\Plugins\AutoTranslator\Domains\PanelRegistry as TranslatedPanels;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\Clusters\PanelSettingsCluster;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\PanelDashboardPage;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\PanelUserResource;

/**
 * A console command has no request, so nothing boots a panel for it. These
 * register panels the way a panel provider does and then walk them the way
 * auto-translator:extract does — without ever serving a request.
 */
it('boots registered panels so plugin configuration reaches a console walk', function () {
    $this->registerPanel(
        Panel::make()
            ->id('dashboard')
            ->path('dashboard')
            ->plugin(AutoTranslatorPlugin::make()->domainPrefixes([
                'Syriable\\Filament\\Plugins\\AutoTranslator\\Tests\\Fixtures' => 'fixtures::',
            ])),
    );

    expect(app(DomainResolver::class)->derive(PanelUserResource::class))->toBe('filament.panel-user-resource');

    app(TranslatedPanels::class)->boot();

    expect(app(DomainResolver::class)->derive(PanelUserResource::class))->toBe('fixtures::panel-user-resource');
});

it('collects the resources that carry a translation domain', function () {
    $this->registerPanel(
        Panel::make()
            ->id('dashboard')
            ->path('dashboard')
            ->resources([PanelUserResource::class]),
    );

    expect(app(TranslatedPanels::class)->resources())->toBe([PanelUserResource::class]);
});

it('collects the clusters that carry a translation domain', function () {
    $this->registerPanel(
        Panel::make()
            ->id('dashboard')
            ->path('dashboard')
            ->discoverClusters(
                in: __DIR__.'/../Fixtures/Clusters',
                for: 'Syriable\\Filament\\Plugins\\AutoTranslator\\Tests\\Fixtures\\Clusters',
            ),
    );

    expect(app(TranslatedPanels::class)->clusters())->toBe([PanelSettingsCluster::class]);
});

it('collects standalone panel pages that carry a translation domain', function () {
    $this->registerPanel(
        Panel::make()
            ->id('dashboard')
            ->path('dashboard')
            ->pages([PanelDashboardPage::class]),
    );

    expect(app(TranslatedPanels::class)->pages())->toBe([PanelDashboardPage::class]);
});

it('leaves the current panel as it found it', function () {
    $this->registerPanel(Panel::make()->id('dashboard')->path('dashboard'));

    app(TranslatedPanels::class)->boot();

    expect(Filament\Facades\Filament::getCurrentPanel())->toBeNull();
});
