<?php

declare(strict_types=1);

use Syriable\Translation\Enums\MessageSlot;
use Syriable\Translation\Enums\MessageSurface;
use Syriable\Translation\Exceptions\InvalidMessageNameException;
use Syriable\Translation\MessageIdentity;
use Syriable\Translation\MessageKeyBuilder;

it('compiles a form field key from catalog identity', function () {
    $compiler = new MessageKeyBuilder;

    $key = $compiler->compile(new MessageIdentity(
        catalogId: 'billing.user-resource',
        scope: MessageSurface::Form,
        path: ['authorization', 'schema'],
        name: 'role',
        slot: MessageSlot::Label,
    ));

    expect($key)->toBe('billing/user-resource.form.components.authorization.schema.role.label');
});

it('compiles a root form field under form.components', function () {
    $compiler = new MessageKeyBuilder;

    $key = $compiler->compile(new MessageIdentity(
        catalogId: 'filament.user-resource',
        scope: MessageSurface::Form,
        path: [],
        name: 'email',
        slot: MessageSlot::Label,
    ));

    expect($key)->toBe('filament/user-resource.form.components.email.label');
});

it('compiles an infolist entry under infolist.components', function () {
    $compiler = new MessageKeyBuilder;

    $key = $compiler->compile(new MessageIdentity(
        catalogId: 'filament.user-resource',
        scope: MessageSurface::Infolist,
        path: [],
        name: 'info',
        slot: MessageSlot::Label,
    ));

    expect($key)->toBe('filament/user-resource.infolist.components.info.label');
});

it('compiles resource chrome as Filament method names', function (MessageSurface $scope, MessageSlot $slot, string $expected) {
    $compiler = new MessageKeyBuilder;

    $key = $compiler->compile(new MessageIdentity(
        catalogId: 'filament.user-resource',
        scope: $scope,
        path: [],
        name: '',
        slot: $slot,
    ));

    expect($key)->toBe($expected);
})->with([
    [MessageSurface::Model, MessageSlot::Label, 'filament/user-resource.model_label'],
    [MessageSurface::Model, MessageSlot::Plural, 'filament/user-resource.plural_model_label'],
    [MessageSurface::Model, MessageSlot::PluralLabel, 'filament/user-resource.plural_label'],
    [MessageSurface::Navigation, MessageSlot::Label, 'filament/user-resource.navigation_label'],
    [MessageSurface::Navigation, MessageSlot::Group, 'filament/user-resource.navigation_group'],
    [MessageSurface::Cluster, MessageSlot::Breadcrumb, 'filament/user-resource.cluster_breadcrumb'],
]);

it('compiles page chrome as Filament method names under the class kebab', function (MessageSlot $slot, string $expected) {
    $compiler = new MessageKeyBuilder;

    $key = $compiler->compile(new MessageIdentity(
        catalogId: 'filament.user-resource',
        scope: MessageSurface::Pages,
        path: ['edit-user'],
        name: '',
        slot: $slot,
    ));

    expect($key)->toBe($expected);
})->with([
    [MessageSlot::Title, 'filament/user-resource.pages.edit-user.title'],
    [MessageSlot::Subheading, 'filament/user-resource.pages.edit-user.subheading'],
    [MessageSlot::Label, 'filament/user-resource.pages.edit-user.navigation_label'],
]);

it('compiles standalone page chrome at the pages/{page} catalog root', function (MessageSlot $slot, string $expected) {
    $compiler = new MessageKeyBuilder;

    $key = $compiler->compile(new MessageIdentity(
        catalogId: 'pages.dashboard',
        scope: MessageSurface::Pages,
        path: [],
        name: '',
        slot: $slot,
    ));

    expect($key)->toBe($expected);
})->with([
    [MessageSlot::Title, 'pages/dashboard.title'],
    [MessageSlot::Subheading, 'pages/dashboard.subheading'],
    [MessageSlot::Label, 'pages/dashboard.navigation_label'],
]);

it('compiles a page action label under the class kebab', function () {
    $compiler = new MessageKeyBuilder;

    $key = $compiler->compile(new MessageIdentity(
        catalogId: 'filament.user-resource',
        scope: MessageSurface::Pages,
        path: ['edit-user', 'actions'],
        name: 'first_action',
        slot: MessageSlot::Label,
    ));

    expect($key)->toBe('filament/user-resource.pages.edit-user.actions.first_action.label');
});

it('compiles a table column under table.columns', function () {
    $compiler = new MessageKeyBuilder;

    $key = $compiler->compile(new MessageIdentity(
        catalogId: 'filament.user-resource',
        scope: MessageSurface::Table,
        path: ['columns'],
        name: 'email',
        slot: MessageSlot::Label,
    ));

    expect($key)->toBe('filament/user-resource.table.columns.email.label');
});

it('normalizes dotted relationship names into a single segment', function () {
    $compiler = new MessageKeyBuilder;

    $key = $compiler->compile(new MessageIdentity(
        catalogId: 'filament.post-resource',
        scope: MessageSurface::Table,
        path: ['columns'],
        name: 'author.name',
        slot: MessageSlot::Label,
    ));

    expect($key)->toBe('filament/post-resource.table.columns.author__name.label');
});

it('rejects machine names that cannot live in a laravel translation key', function () {
    $compiler = new MessageKeyBuilder;

    $compiler->compile(new MessageIdentity(
        catalogId: 'filament.user-resource',
        scope: MessageSurface::Form,
        path: [],
        name: 'email address',
        slot: MessageSlot::Label,
    ));
})->throws(InvalidMessageNameException::class);
