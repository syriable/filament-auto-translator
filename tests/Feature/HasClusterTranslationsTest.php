<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;
use Syriable\Translation\Binding\MessageOverrides;
use Syriable\Translation\Enums\MissingMessagePolicy;
use Syriable\Translation\Tests\Fixtures\CatalogCluster;

beforeEach(function () {
    config()->set('translations.on_missing', 'debug');
    config()->set('translations.default_domain_prefix', 'filament');
    config()->set('translations.domain_prefixes', []);
    app(MessageOverrides::class)->mode = null;
});

it('fills cluster chrome from the message catalog', function () {
    Lang::addLines([
        'filament/catalog-cluster.cluster_breadcrumb' => 'Settings',
        'filament/catalog-cluster.navigation_label' => 'Site settings',
    ], 'en');

    expect(CatalogCluster::getClusterBreadcrumb())->toBe('Settings')
        ->and(CatalogCluster::getNavigationLabel())->toBe('Site settings');
});

it('surfaces compiled cluster breadcrumb keys when required messages are missing in debug mode', function () {
    expect(CatalogCluster::getClusterBreadcrumb())->toBe('filament/catalog-cluster.cluster_breadcrumb');
});

it('keeps the vendor cluster labels when required messages are missing under that policy', function () {
    app(MessageOverrides::class)->mode = MissingMessagePolicy::KeepVendorLabel;

    expect(CatalogCluster::getClusterBreadcrumb())->toBe('parent breadcrumb')
        ->and(CatalogCluster::getNavigationLabel())->toBe('parent cluster nav');
});
