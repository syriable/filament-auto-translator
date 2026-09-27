<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;
use Syriable\FilamentAutoTranslator\Enums\MissingMessagePolicy;
use Syriable\FilamentAutoTranslator\Settings;
use Syriable\FilamentAutoTranslator\Tests\Fixtures\DeclaredDomainPage;
use Syriable\FilamentAutoTranslator\Tests\Fixtures\StandaloneDashboardPage;
use Syriable\FilamentAutoTranslator\Tests\Fixtures\TranslatedResource;
use Syriable\FilamentAutoTranslator\Tests\Fixtures\TranslatedResourcePage;

beforeEach(function () {
    config()->set('filament-auto-translator.on_missing', 'debug');
    config()->set('filament-auto-translator.default_domain_prefix', 'filament');
    config()->set('filament-auto-translator.domain_prefixes', []);
});

it('shares the resource domain on a bound page', function () {
    expect(TranslatedResourcePage::translationDomain())->toBe(TranslatedResource::translationDomain());
});

it('fills page chrome from the shared resource domain', function () {
    Lang::addLines([
        'filament/translated-resource.pages.translated-resource-page.title' => 'Edit user',
        'filament/translated-resource.pages.translated-resource-page.subheading' => 'Change the user.',
        'filament/translated-resource.pages.translated-resource-page.navigation_label' => 'Edit',
    ], 'en');

    $page = new TranslatedResourcePage;

    expect($page->getTitle())->toBe('Edit user')
        ->and($page->getSubheading())->toBe('Change the user.')
        ->and(TranslatedResourcePage::getNavigationLabel())->toBe('Edit');
});

it('surfaces the compiled page title key when the message is missing in debug mode', function () {
    expect((new TranslatedResourcePage)->getTitle())->toBe('filament/translated-resource.pages.translated-resource-page.title');
});

it('keeps parent page chrome when messages are missing under the keep-vendor-label policy', function () {
    app(Settings::class)->usePolicy(MissingMessagePolicy::KeepVendorLabel);

    $page = new TranslatedResourcePage;

    expect($page->getTitle())->toBe('parent title')
        ->and($page->getSubheading())->toBe('parent subheading')
        ->and(TranslatedResourcePage::getNavigationLabel())->toBe('parent navigation');
});

it('keeps a domain the page declares itself instead of the resource one', function () {
    expect(DeclaredDomainPage::translationDomain())->toBe('identity::people-edit')
        ->and(DeclaredDomainPage::translationDomain())->not->toBe(TranslatedResource::translationDomain());
});

it('fills page chrome from the domain the page declares', function () {
    Lang::addLines(['people-edit.pages.declared-domain-page.title' => 'Edit person'], 'en', 'identity');

    expect((new DeclaredDomainPage)->getTitle())->toBe('Edit person');
});

it('owns its domain when it is not a resource page', function () {
    expect(StandaloneDashboardPage::translationDomain())->toBe('filament.pages.standalone-dashboard-page');
});

it('fills standalone page chrome from filament/pages/{page} root keys', function () {
    Lang::addLines([
        'filament/pages/standalone-dashboard-page.title' => 'Home board',
        'filament/pages/standalone-dashboard-page.navigation_label' => 'Home',
    ], 'en');

    $page = new StandaloneDashboardPage;

    expect($page->getTitle())->toBe('Home board')
        ->and(StandaloneDashboardPage::getNavigationLabel())->toBe('Home');
});
