<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;
use Syriable\Translation\Binding\MessageOverrides;
use Syriable\Translation\Enums\MissingMessagePolicy;
use Syriable\Translation\Tests\Fixtures\CatalogOwner;
use Syriable\Translation\Tests\Fixtures\DeclaredDomainOwner;

beforeEach(function () {
    config()->set('translations.on_missing', 'debug');
    config()->set('translations.default_domain_prefix', 'filament');
    config()->set('translations.domain_prefixes', []);
    app(MessageOverrides::class)->mode = null;
});

it('fills resource chrome from the message catalog', function () {
    Lang::addLines([
        'filament/catalog-owner.model_label' => 'User',
        'filament/catalog-owner.plural_model_label' => 'Users',
        'filament/catalog-owner.plural_label' => 'People',
        'filament/catalog-owner.navigation_label' => 'Team members',
        'filament/catalog-owner.navigation_group' => 'Access',
    ], 'en');

    expect(CatalogOwner::getModelLabel())->toBe('User')
        ->and(CatalogOwner::getPluralModelLabel())->toBe('Users')
        ->and(CatalogOwner::getPluralLabel())->toBe('People')
        ->and(CatalogOwner::getNavigationLabel())->toBe('Team members')
        ->and(CatalogOwner::getNavigationGroup())->toBe('Access');
});

it('surfaces compiled chrome keys when required messages are missing in debug mode', function () {
    expect(CatalogOwner::getModelLabel())->toBe('filament/catalog-owner.model_label');
});

it('keeps the vendor label when required messages are missing under that policy', function () {
    app(MessageOverrides::class)->mode = MissingMessagePolicy::KeepVendorLabel;

    expect(CatalogOwner::getModelLabel())->toBe('parent model')
        ->and(CatalogOwner::getPluralModelLabel())->toBe('parent models')
        ->and(CatalogOwner::getPluralLabel())->toBe('parent plurals')
        ->and(CatalogOwner::getNavigationLabel())->toBe('parent nav')
        ->and(CatalogOwner::getNavigationGroup())->toBe('parent group');
});

it('prefers a declared domain over the prefix map', function () {
    config()->set('translations.domain_prefixes', [
        'Syriable\\Translation\\Tests\\Fixtures' => 'fixtures',
    ]);

    expect(DeclaredDomainOwner::translationDomain())->toBe('identity::people')
        ->and(CatalogOwner::translationDomain())->toBe('fixtures.catalog-owner');
});

it('fills chrome from the module lang directory a declared domain points at', function () {
    Lang::addLines(['people.model_label' => 'Person'], 'en', 'identity');

    expect(DeclaredDomainOwner::getModelLabel())->toBe('Person');
});
