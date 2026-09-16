<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;
use Syriable\Translation\Binding\MessageOverrides;
use Syriable\Translation\Catalog\MessageResolver;
use Syriable\Translation\Enums\MessageSlot;
use Syriable\Translation\Enums\MessageSurface;
use Syriable\Translation\Enums\MissingMessagePolicy;
use Syriable\Translation\Enums\ResolutionOutcome;
use Syriable\Translation\Exceptions\MissingMessageException;
use Syriable\Translation\MessageIdentity;

beforeEach(function () {
    config()->set('translations.on_missing', 'debug');
    app(MessageOverrides::class)->mode = null;
});

it('binds copy from the current locale', function () {
    Lang::addLines([
        'filament/user-resource.form.components.email.label' => 'Email address',
    ], 'en');

    $resolution = app(MessageResolver::class)->resolve(new MessageIdentity(
        catalogId: 'filament.user-resource',
        scope: MessageSurface::Form,
        path: [],
        name: 'email',
        slot: MessageSlot::Label,
    ));

    expect($resolution->decision)->toBe(ResolutionOutcome::Bound)
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
        catalogId: 'filament.user-resource',
        scope: MessageSurface::Form,
        path: [],
        name: 'email',
        slot: MessageSlot::Label,
    ));

    expect($resolution->decision)->toBe(ResolutionOutcome::UsedFallbackLocale)
        ->and($resolution->presentInCurrentLocale)->toBeFalse()
        ->and($resolution->presentInFallbackLocale)->toBeTrue();
});

it('surfaces the compiled key when a required message is missing in debug mode', function () {
    $resolution = app(MessageResolver::class)->resolve(new MessageIdentity(
        catalogId: 'filament.user-resource',
        scope: MessageSurface::Form,
        path: [],
        name: 'email',
        slot: MessageSlot::Label,
    ));

    expect($resolution->decision)->toBe(ResolutionOutcome::Missing)
        ->and($resolution->text)->toBe('filament/user-resource.form.components.email.label');
});

it('throws when a required message is missing in strict mode', function () {
    app(MessageOverrides::class)->mode = MissingMessagePolicy::Strict;

    app(MessageResolver::class)->resolve(new MessageIdentity(
        catalogId: 'filament.user-resource',
        scope: MessageSurface::Form,
        path: [],
        name: 'email',
        slot: MessageSlot::Label,
    ));
})->throws(MissingMessageException::class);

it('leaves optional missing messages empty in debug mode', function () {
    $resolution = app(MessageResolver::class)->resolve(new MessageIdentity(
        catalogId: 'filament.user-resource',
        scope: MessageSurface::Form,
        path: [],
        name: 'email',
        slot: MessageSlot::HelperText,
    ));

    expect($resolution->decision)->toBe(ResolutionOutcome::Missing)
        ->and($resolution->text)->toBeNull();
});

it('leaves a missing notification body empty in debug mode', function () {
    $resolution = app(MessageResolver::class)->resolve(new MessageIdentity(
        catalogId: 'filament.user-resource',
        scope: MessageSurface::Form,
        path: ['actions', 'action', 'notifications'],
        name: 'success',
        slot: MessageSlot::Body,
    ));

    expect($resolution->decision)->toBe(ResolutionOutcome::Missing)
        ->and($resolution->text)->toBeNull();
});

it('does not throw when a notification body is missing in strict mode', function () {
    app(MessageOverrides::class)->mode = MissingMessagePolicy::Strict;

    $resolution = app(MessageResolver::class)->resolve(new MessageIdentity(
        catalogId: 'filament.user-resource',
        scope: MessageSurface::Form,
        path: ['actions', 'action', 'notifications'],
        name: 'success',
        slot: MessageSlot::Body,
    ));

    expect($resolution->decision)->toBe(ResolutionOutcome::Missing)
        ->and($resolution->text)->toBeNull();
});
