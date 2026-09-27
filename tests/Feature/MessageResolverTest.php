<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;
use Syriable\FilamentAutoTranslator\Enums\MessageScope;
use Syriable\FilamentAutoTranslator\Enums\MessageSlot;
use Syriable\FilamentAutoTranslator\Enums\MissingMessagePolicy;
use Syriable\FilamentAutoTranslator\Enums\ResolutionOutcome;
use Syriable\FilamentAutoTranslator\Exceptions\MissingMessageException;
use Syriable\FilamentAutoTranslator\Messages\MessageIdentity;
use Syriable\FilamentAutoTranslator\Messages\MessageResolver;
use Syriable\FilamentAutoTranslator\Settings;

beforeEach(function () {
    config()->set('filament-auto-translator.on_missing', 'debug');
});

it('binds copy from the current locale', function () {
    Lang::addLines([
        'filament/user-resource.form.components.email.label' => 'Email address',
    ], 'en');

    $resolution = app(MessageResolver::class)->resolve(new MessageIdentity(
        domain: 'filament.user-resource',
        scope: MessageScope::Form,
        path: [],
        name: 'email',
        slot: MessageSlot::Label,
    ));

    expect($resolution->outcome)->toBe(ResolutionOutcome::Bound)
        ->and($resolution->text)->toBe('Email address')
        ->and($resolution->presentInCurrentLocale)->toBeTrue();
});

it('does not treat an english fallback as a present arabic translation', function () {
    app()->setLocale('ar');
    config()->set('app.fallback_locale', 'en');

    Lang::addLines([
        'filament/user-resource.form.components.email.label' => 'Email address',
    ], 'en');

    $resolution = app(MessageResolver::class)->resolve(new MessageIdentity(
        domain: 'filament.user-resource',
        scope: MessageScope::Form,
        path: [],
        name: 'email',
        slot: MessageSlot::Label,
    ));

    expect($resolution->outcome)->toBe(ResolutionOutcome::UsedFallbackLocale)
        ->and($resolution->presentInCurrentLocale)->toBeFalse()
        ->and($resolution->presentInFallbackLocale)->toBeTrue();
});

it('surfaces the compiled key when a required message is missing in debug mode', function () {
    $resolution = app(MessageResolver::class)->resolve(new MessageIdentity(
        domain: 'filament.user-resource',
        scope: MessageScope::Form,
        path: [],
        name: 'email',
        slot: MessageSlot::Label,
    ));

    expect($resolution->outcome)->toBe(ResolutionOutcome::Missing)
        ->and($resolution->text)->toBe('filament/user-resource.form.components.email.label');
});

it('throws when a required message is missing in strict mode', function () {
    app(Settings::class)->usePolicy(MissingMessagePolicy::Strict);

    app(MessageResolver::class)->resolve(new MessageIdentity(
        domain: 'filament.user-resource',
        scope: MessageScope::Form,
        path: [],
        name: 'email',
        slot: MessageSlot::Label,
    ));
})->throws(MissingMessageException::class);

it('leaves optional missing messages empty in debug mode', function () {
    $resolution = app(MessageResolver::class)->resolve(new MessageIdentity(
        domain: 'filament.user-resource',
        scope: MessageScope::Form,
        path: [],
        name: 'email',
        slot: MessageSlot::HelperText,
    ));

    expect($resolution->outcome)->toBe(ResolutionOutcome::Missing)
        ->and($resolution->text)->toBeNull();
});

it('leaves a missing notification body empty in debug mode', function () {
    $resolution = app(MessageResolver::class)->resolve(new MessageIdentity(
        domain: 'filament.user-resource',
        scope: MessageScope::Form,
        path: ['actions', 'action', 'notifications'],
        name: 'success',
        slot: MessageSlot::Body,
    ));

    expect($resolution->outcome)->toBe(ResolutionOutcome::Missing)
        ->and($resolution->text)->toBeNull();
});

it('does not throw when a notification body is missing in strict mode', function () {
    app(Settings::class)->usePolicy(MissingMessagePolicy::Strict);

    $resolution = app(MessageResolver::class)->resolve(new MessageIdentity(
        domain: 'filament.user-resource',
        scope: MessageScope::Form,
        path: ['actions', 'action', 'notifications'],
        name: 'success',
        slot: MessageSlot::Body,
    ));

    expect($resolution->outcome)->toBe(ResolutionOutcome::Missing)
        ->and($resolution->text)->toBeNull();
});
