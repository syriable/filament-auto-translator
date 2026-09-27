<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;
use Syriable\FilamentAutoTranslator\Enums\MissingMessagePolicy;
use Syriable\FilamentAutoTranslator\Settings;
use Syriable\FilamentAutoTranslator\Tests\Fixtures\DeclaredDomainResource;
use Syriable\FilamentAutoTranslator\Tests\Fixtures\TranslatedResource;

beforeEach(function () {
    config()->set('filament-auto-translator.on_missing', 'debug');
    config()->set('filament-auto-translator.default_domain_prefix', 'filament');
    config()->set('filament-auto-translator.domain_prefixes', []);
});

it('fills resource chrome from the language file', function () {
    Lang::addLines([
        'filament/translated-resource.model_label' => 'User',
        'filament/translated-resource.plural_model_label' => 'Users',
        'filament/translated-resource.plural_label' => 'People',
        'filament/translated-resource.navigation_label' => 'Team members',
        'filament/translated-resource.navigation_group' => 'Access',
    ], 'en');

    expect(TranslatedResource::getModelLabel())->toBe('User')
        ->and(TranslatedResource::getPluralModelLabel())->toBe('Users')
        ->and(TranslatedResource::getPluralLabel())->toBe('People')
        ->and(TranslatedResource::getNavigationLabel())->toBe('Team members')
        ->and(TranslatedResource::getNavigationGroup())->toBe('Access');
});

it('surfaces compiled chrome keys when required messages are missing in debug mode', function () {
    expect(TranslatedResource::getModelLabel())->toBe('filament/translated-resource.model_label');
});

it('keeps the vendor label when required messages are missing under that policy', function () {
    app(Settings::class)->usePolicy(MissingMessagePolicy::KeepVendorLabel);

    expect(TranslatedResource::getModelLabel())->toBe('parent model')
        ->and(TranslatedResource::getPluralModelLabel())->toBe('parent models')
        ->and(TranslatedResource::getPluralLabel())->toBe('parent plurals')
        ->and(TranslatedResource::getNavigationLabel())->toBe('parent nav')
        ->and(TranslatedResource::getNavigationGroup())->toBe('parent group');
});

it('prefers a declared domain over the prefix map', function () {
    config()->set('filament-auto-translator.domain_prefixes', [
        'Syriable\\FilamentAutoTranslator\\Tests\\Fixtures' => 'fixtures',
    ]);

    expect(DeclaredDomainResource::translationDomain())->toBe('identity::people')
        ->and(TranslatedResource::translationDomain())->toBe('fixtures.translated-resource');
});

it('fills chrome from the module lang directory a declared domain points at', function () {
    Lang::addLines(['people.model_label' => 'Person'], 'en', 'identity');

    expect(DeclaredDomainResource::getModelLabel())->toBe('Person');
});
