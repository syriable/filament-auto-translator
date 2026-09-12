<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseMode;
use Syriable\Filament\Plugins\AutoTranslator\PhraseRegistry;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\CatalogBoundPage;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\CatalogOwner;

beforeEach(function () {
    config()->set('auto-translator.mode', 'inspect');
    config()->set('auto-translator.default_prefix', 'filament');
    config()->set('auto-translator.catalog_prefixes', []);
    app(PhraseRegistry::class)->mode = null;
});

it('shares the resource catalog id on a bound page', function () {
    expect(CatalogBoundPage::phraseCatalogId())->toBe(CatalogOwner::phraseCatalogId());
});

it('fills page chrome from the shared resource catalog', function () {
    Lang::addLines([
        'filament/catalog-owner.pages.catalog-bound-page.title' => 'Edit user',
        'filament/catalog-owner.pages.catalog-bound-page.subheading' => 'Change the user.',
        'filament/catalog-owner.pages.catalog-bound-page.navigation_label' => 'Edit',
    ], 'en');

    $page = new CatalogBoundPage;

    expect($page->getTitle())->toBe('Edit user')
        ->and($page->getSubheading())->toBe('Change the user.')
        ->and(CatalogBoundPage::getNavigationLabel())->toBe('Edit');
});

it('surfaces the compiled page title key when the phrase is missing in inspect mode', function () {
    expect((new CatalogBoundPage)->getTitle())->toBe('filament/catalog-owner.pages.catalog-bound-page.title');
});

it('keeps parent page chrome when phrases are missing in lenient mode', function () {
    app(PhraseRegistry::class)->mode = PhraseMode::Lenient;

    $page = new CatalogBoundPage;

    expect($page->getTitle())->toBe('parent title')
        ->and($page->getSubheading())->toBe('parent subheading')
        ->and(CatalogBoundPage::getNavigationLabel())->toBe('parent navigation');
});
