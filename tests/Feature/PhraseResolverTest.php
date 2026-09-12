<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseDecision;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseMode;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseScope;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseSlot;
use Syriable\Filament\Plugins\AutoTranslator\Exceptions\MissingPhraseException;
use Syriable\Filament\Plugins\AutoTranslator\PhraseIdentity;
use Syriable\Filament\Plugins\AutoTranslator\PhraseRegistry;
use Syriable\Filament\Plugins\AutoTranslator\PhraseResolver;

beforeEach(function () {
    config()->set('auto-translator.mode', 'inspect');
    app(PhraseRegistry::class)->mode = null;
});

it('binds copy from the current locale', function () {
    Lang::addLines([
        'filament/user-resource.form.components.email.label' => 'Email address',
    ], 'en');

    $resolution = app(PhraseResolver::class)->resolve(new PhraseIdentity(
        catalogId: 'filament.user-resource',
        scope: PhraseScope::Form,
        path: [],
        name: 'email',
        slot: PhraseSlot::Label,
    ));

    expect($resolution->decision)->toBe(PhraseDecision::Bound)
        ->and($resolution->text)->toBe('Email address')
        ->and($resolution->presentInCurrentLocale)->toBeTrue();
});

it('does not treat an english fallback as a present arabic translation', function () {
    app()->setLocale('ar');
    config()->set('app.fallback_locale', 'en');

    Lang::addLines([
        'filament/user-resource.form.components.email.label' => 'Email address',
    ], 'en');

    $resolution = app(PhraseResolver::class)->resolve(new PhraseIdentity(
        catalogId: 'filament.user-resource',
        scope: PhraseScope::Form,
        path: [],
        name: 'email',
        slot: PhraseSlot::Label,
    ));

    expect($resolution->decision)->toBe(PhraseDecision::UsedFallback)
        ->and($resolution->presentInCurrentLocale)->toBeFalse()
        ->and($resolution->presentInFallbackLocale)->toBeTrue();
});

it('surfaces the compiled key when a required phrase is missing in inspect mode', function () {
    $resolution = app(PhraseResolver::class)->resolve(new PhraseIdentity(
        catalogId: 'filament.user-resource',
        scope: PhraseScope::Form,
        path: [],
        name: 'email',
        slot: PhraseSlot::Label,
    ));

    expect($resolution->decision)->toBe(PhraseDecision::Missing)
        ->and($resolution->text)->toBe('filament/user-resource.form.components.email.label');
});

it('throws when a required phrase is missing in strict mode', function () {
    app(PhraseRegistry::class)->mode = PhraseMode::Strict;

    app(PhraseResolver::class)->resolve(new PhraseIdentity(
        catalogId: 'filament.user-resource',
        scope: PhraseScope::Form,
        path: [],
        name: 'email',
        slot: PhraseSlot::Label,
    ));
})->throws(MissingPhraseException::class);

it('leaves optional missing phrases empty in inspect mode', function () {
    $resolution = app(PhraseResolver::class)->resolve(new PhraseIdentity(
        catalogId: 'filament.user-resource',
        scope: PhraseScope::Form,
        path: [],
        name: 'email',
        slot: PhraseSlot::HelperText,
    ));

    expect($resolution->decision)->toBe(PhraseDecision::Missing)
        ->and($resolution->text)->toBeNull();
});

it('leaves a missing notification body empty in inspect mode', function () {
    $resolution = app(PhraseResolver::class)->resolve(new PhraseIdentity(
        catalogId: 'filament.user-resource',
        scope: PhraseScope::Form,
        path: ['actions', 'action', 'notifications'],
        name: 'success',
        slot: PhraseSlot::Body,
    ));

    expect($resolution->decision)->toBe(PhraseDecision::Missing)
        ->and($resolution->text)->toBeNull();
});

it('does not throw when a notification body is missing in strict mode', function () {
    app(PhraseRegistry::class)->mode = PhraseMode::Strict;

    $resolution = app(PhraseResolver::class)->resolve(new PhraseIdentity(
        catalogId: 'filament.user-resource',
        scope: PhraseScope::Form,
        path: ['actions', 'action', 'notifications'],
        name: 'success',
        slot: PhraseSlot::Body,
    ));

    expect($resolution->decision)->toBe(PhraseDecision::Missing)
        ->and($resolution->text)->toBeNull();
});
