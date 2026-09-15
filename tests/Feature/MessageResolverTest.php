<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;
use Syriable\MessageCatalog\Binding\MessageOverrides;
use Syriable\MessageCatalog\Catalog\MessageResolver;
use Syriable\MessageCatalog\Enums\MessageSlot;
use Syriable\MessageCatalog\Enums\MessageSurface;
use Syriable\MessageCatalog\Enums\MissingMessagePolicy;
use Syriable\MessageCatalog\Enums\ResolutionOutcome;
use Syriable\MessageCatalog\Exceptions\MissingMessageException;
use Syriable\MessageCatalog\MessageIdentity;

beforeEach(function () {
    config()->set('messages.on_missing', 'debug');
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

    expect($resolution->decision)->toBe(ResolutionOutcome::UsedFallback)
        ->and($resolution->presentInCurrentLocale)->toBeFalse()
        ->and($resolution->presentInFallbackLocale)->toBeTrue();
});

it('surfaces the compiled key when a required phrase is missing in inspect mode', function () {
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

it('throws when a required phrase is missing in strict mode', function () {
    app(MessageOverrides::class)->mode = MissingMessagePolicy::Strict;

    app(MessageResolver::class)->resolve(new MessageIdentity(
        catalogId: 'filament.user-resource',
        scope: MessageSurface::Form,
        path: [],
        name: 'email',
        slot: MessageSlot::Label,
    ));
})->throws(MissingMessageException::class);

it('leaves optional missing phrases empty in inspect mode', function () {
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

it('leaves a missing notification body empty in inspect mode', function () {
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
