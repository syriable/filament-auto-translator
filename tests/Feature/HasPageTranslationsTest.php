<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;
use Syriable\Translation\Binding\MessageOverrides;
use Syriable\Translation\Enums\MissingMessagePolicy;
use Syriable\Translation\Tests\Fixtures\CatalogBoundPage;
use Syriable\Translation\Tests\Fixtures\CatalogOwner;
use Syriable\Translation\Tests\Fixtures\DeclaredDomainPage;

beforeEach(function () {
    config()->set('translations.on_missing', 'debug');
    config()->set('translations.default_domain_prefix', 'filament');
    config()->set('translations.domain_prefixes', []);
    app(MessageOverrides::class)->mode = null;
});

it('shares the resource catalog id on a bound page', function () {
    expect(CatalogBoundPage::translationDomain())->toBe(CatalogOwner::translationDomain());
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

it('surfaces the compiled page title key when the message is missing in debug mode', function () {
    expect((new CatalogBoundPage)->getTitle())->toBe('filament/catalog-owner.pages.catalog-bound-page.title');
});

it('keeps parent page chrome when messages are missing under the keep-vendor-label policy', function () {
    app(MessageOverrides::class)->mode = MissingMessagePolicy::KeepVendorLabel;

    $page = new CatalogBoundPage;

    expect($page->getTitle())->toBe('parent title')
        ->and($page->getSubheading())->toBe('parent subheading')
        ->and(CatalogBoundPage::getNavigationLabel())->toBe('parent navigation');
});

it('keeps a domain the page declares itself instead of the resource one', function () {
    expect(DeclaredDomainPage::translationDomain())->toBe('identity::people-edit')
        ->and(DeclaredDomainPage::translationDomain())->not->toBe(CatalogOwner::translationDomain());
});

it('fills page chrome from the domain the page declares', function () {
    Lang::addLines(['people-edit.pages.declared-domain-page.title' => 'Edit person'], 'en', 'identity');

    expect((new DeclaredDomainPage)->getTitle())->toBe('Edit person');
});
