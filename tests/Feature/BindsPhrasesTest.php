<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseMode;
use Syriable\Filament\Plugins\AutoTranslator\PhraseRegistry;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\CatalogOwner;

beforeEach(function () {
    config()->set('auto-translator.mode', 'inspect');
    config()->set('auto-translator.default_prefix', 'filament');
    config()->set('auto-translator.catalog_prefixes', []);
    app(PhraseRegistry::class)->mode = null;
});

it('fills resource chrome from the phrase catalog', function () {
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

it('surfaces compiled chrome keys when required phrases are missing in inspect mode', function () {
    expect(CatalogOwner::getModelLabel())->toBe('filament/catalog-owner.model_label');
});

it('keeps parent chrome when required phrases are missing in lenient mode', function () {
    app(PhraseRegistry::class)->mode = PhraseMode::Lenient;

    expect(CatalogOwner::getModelLabel())->toBe('parent model')
        ->and(CatalogOwner::getPluralModelLabel())->toBe('parent models')
        ->and(CatalogOwner::getPluralLabel())->toBe('parent plurals')
        ->and(CatalogOwner::getNavigationLabel())->toBe('parent nav')
        ->and(CatalogOwner::getNavigationGroup())->toBe('parent group');
});
