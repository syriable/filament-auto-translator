<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\AutoTranslator\Enums\MessageScope;
use Syriable\Filament\Plugins\AutoTranslator\Enums\MessageSlot;
use Syriable\Filament\Plugins\AutoTranslator\Exceptions\InvalidMessageNameException;
use Syriable\Filament\Plugins\AutoTranslator\Messages\MessageIdentity;

it('compiles a form field key from its identity', function () {
    $key = (new MessageIdentity(
        domain: 'billing.user-resource',
        scope: MessageScope::Form,
        path: ['authorization', 'schema'],
        name: 'role',
        slot: MessageSlot::Label,
    ))->key();

    expect($key)->toBe('billing/user-resource.form.components.authorization.schema.role.label');
});

it('compiles a root form field under form.components', function () {
    $key = (new MessageIdentity(
        domain: 'filament.user-resource',
        scope: MessageScope::Form,
        path: [],
        name: 'email',
        slot: MessageSlot::Label,
    ))->key();

    expect($key)->toBe('filament/user-resource.form.components.email.label');
});

it('compiles an infolist entry under infolist.components', function () {
    $key = (new MessageIdentity(
        domain: 'filament.user-resource',
        scope: MessageScope::Infolist,
        path: [],
        name: 'info',
        slot: MessageSlot::Label,
    ))->key();

    expect($key)->toBe('filament/user-resource.infolist.components.info.label');
});

it('compiles resource chrome as Filament method names', function (MessageScope $scope, MessageSlot $slot, string $expected) {
    $key = (new MessageIdentity(
        domain: 'filament.user-resource',
        scope: $scope,
        path: [],
        name: '',
        slot: $slot,
    ))->key();

    expect($key)->toBe($expected);
})->with([
    [MessageScope::Model, MessageSlot::Label, 'filament/user-resource.model_label'],
    [MessageScope::Model, MessageSlot::Plural, 'filament/user-resource.plural_model_label'],
    [MessageScope::Model, MessageSlot::PluralLabel, 'filament/user-resource.plural_label'],
    [MessageScope::Navigation, MessageSlot::Label, 'filament/user-resource.navigation_label'],
    [MessageScope::Navigation, MessageSlot::Group, 'filament/user-resource.navigation_group'],
    [MessageScope::Cluster, MessageSlot::Breadcrumb, 'filament/user-resource.cluster_breadcrumb'],
]);

it('compiles page chrome as Filament method names under the class kebab', function (MessageSlot $slot, string $expected) {
    $key = (new MessageIdentity(
        domain: 'filament.user-resource',
        scope: MessageScope::Pages,
        path: ['edit-user'],
        name: '',
        slot: $slot,
    ))->key();

    expect($key)->toBe($expected);
})->with([
    [MessageSlot::Title, 'filament/user-resource.pages.edit-user.title'],
    [MessageSlot::Subheading, 'filament/user-resource.pages.edit-user.subheading'],
    [MessageSlot::Label, 'filament/user-resource.pages.edit-user.navigation_label'],
]);

it('compiles standalone page chrome at the filament/pages/{page} file root', function (MessageSlot $slot, string $expected) {
    $key = (new MessageIdentity(
        domain: 'filament.pages.dashboard',
        scope: MessageScope::Pages,
        path: [],
        name: '',
        slot: $slot,
    ))->key();

    expect($key)->toBe($expected);
})->with([
    [MessageSlot::Title, 'filament/pages/dashboard.title'],
    [MessageSlot::Subheading, 'filament/pages/dashboard.subheading'],
    [MessageSlot::Label, 'filament/pages/dashboard.navigation_label'],
]);

it('compiles a page action label under the class kebab', function () {
    $key = (new MessageIdentity(
        domain: 'filament.user-resource',
        scope: MessageScope::Pages,
        path: ['edit-user', 'actions'],
        name: 'first_action',
        slot: MessageSlot::Label,
    ))->key();

    expect($key)->toBe('filament/user-resource.pages.edit-user.actions.first_action.label');
});

it('compiles a table column under table.columns', function () {
    $key = (new MessageIdentity(
        domain: 'filament.user-resource',
        scope: MessageScope::Table,
        path: ['columns'],
        name: 'email',
        slot: MessageSlot::Label,
    ))->key();

    expect($key)->toBe('filament/user-resource.table.columns.email.label');
});

it('normalizes dotted relationship names into a single segment', function () {
    $key = (new MessageIdentity(
        domain: 'filament.post-resource',
        scope: MessageScope::Table,
        path: ['columns'],
        name: 'author.name',
        slot: MessageSlot::Label,
    ))->key();

    expect($key)->toBe('filament/post-resource.table.columns.author__name.label');
});

it('rejects machine names that cannot live in a laravel translation key', function () {
    (new MessageIdentity(
        domain: 'filament.user-resource',
        scope: MessageScope::Form,
        path: [],
        name: 'email address',
        slot: MessageSlot::Label,
    ))->key();
})->throws(InvalidMessageNameException::class);
