<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;
use Syriable\Filament\Plugins\AutoTranslator\Enums\MissingMessagePolicy;
use Syriable\Filament\Plugins\AutoTranslator\Settings;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\TranslatedCluster;

beforeEach(function () {
    config()->set('filament-auto-translator.on_missing', 'debug');
    config()->set('filament-auto-translator.default_domain_prefix', 'filament');
    config()->set('filament-auto-translator.domain_prefixes', []);
});

it('fills cluster chrome from the language file', function () {
    Lang::addLines([
        'filament/translated-cluster.cluster_breadcrumb' => 'Settings',
        'filament/translated-cluster.navigation_label' => 'Site settings',
    ], 'en');

    expect(TranslatedCluster::getClusterBreadcrumb())->toBe('Settings')
        ->and(TranslatedCluster::getNavigationLabel())->toBe('Site settings');
});

it('surfaces compiled cluster breadcrumb keys when required messages are missing in debug mode', function () {
    expect(TranslatedCluster::getClusterBreadcrumb())->toBe('filament/translated-cluster.cluster_breadcrumb');
});

it('keeps the vendor cluster labels when required messages are missing under that policy', function () {
    app(Settings::class)->usePolicy(MissingMessagePolicy::KeepVendorLabel);

    expect(TranslatedCluster::getClusterBreadcrumb())->toBe('parent breadcrumb')
        ->and(TranslatedCluster::getNavigationLabel())->toBe('parent cluster nav');
});
