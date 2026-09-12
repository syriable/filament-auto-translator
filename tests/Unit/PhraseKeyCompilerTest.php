<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseScope;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseSlot;
use Syriable\Filament\Plugins\AutoTranslator\Exceptions\InvalidPhraseNameException;
use Syriable\Filament\Plugins\AutoTranslator\PhraseIdentity;
use Syriable\Filament\Plugins\AutoTranslator\PhraseKeyCompiler;

it('compiles a form field key from catalog identity', function () {
    $compiler = new PhraseKeyCompiler;

    $key = $compiler->compile(new PhraseIdentity(
        catalogId: 'billing.user-resource',
        scope: PhraseScope::Form,
        path: ['authorization', 'schema'],
        name: 'role',
        slot: PhraseSlot::Label,
    ));

    expect($key)->toBe('billing/user-resource.form.components.authorization.schema.role.label');
});

it('compiles a root form field under form.components', function () {
    $compiler = new PhraseKeyCompiler;

    $key = $compiler->compile(new PhraseIdentity(
        catalogId: 'filament.user-resource',
        scope: PhraseScope::Form,
        path: [],
        name: 'email',
        slot: PhraseSlot::Label,
    ));

    expect($key)->toBe('filament/user-resource.form.components.email.label');
});

it('compiles an infolist entry under infolist.components', function () {
    $compiler = new PhraseKeyCompiler;

    $key = $compiler->compile(new PhraseIdentity(
        catalogId: 'filament.user-resource',
        scope: PhraseScope::Infolist,
        path: [],
        name: 'info',
        slot: PhraseSlot::Label,
    ));

    expect($key)->toBe('filament/user-resource.infolist.components.info.label');
});

it('compiles resource chrome as Filament method names', function (PhraseScope $scope, PhraseSlot $slot, string $expected) {
    $compiler = new PhraseKeyCompiler;

    $key = $compiler->compile(new PhraseIdentity(
        catalogId: 'filament.user-resource',
        scope: $scope,
        path: [],
        name: '',
        slot: $slot,
    ));

    expect($key)->toBe($expected);
})->with([
    [PhraseScope::Model, PhraseSlot::Label, 'filament/user-resource.model_label'],
    [PhraseScope::Model, PhraseSlot::Plural, 'filament/user-resource.plural_model_label'],
    [PhraseScope::Model, PhraseSlot::PluralLabel, 'filament/user-resource.plural_label'],
    [PhraseScope::Navigation, PhraseSlot::Label, 'filament/user-resource.navigation_label'],
    [PhraseScope::Navigation, PhraseSlot::Group, 'filament/user-resource.navigation_group'],
]);

it('compiles page chrome as Filament method names under the class kebab', function (PhraseSlot $slot, string $expected) {
    $compiler = new PhraseKeyCompiler;

    $key = $compiler->compile(new PhraseIdentity(
        catalogId: 'filament.user-resource',
        scope: PhraseScope::Pages,
        path: ['edit-user'],
        name: '',
        slot: $slot,
    ));

    expect($key)->toBe($expected);
})->with([
    [PhraseSlot::Title, 'filament/user-resource.pages.edit-user.title'],
    [PhraseSlot::Subheading, 'filament/user-resource.pages.edit-user.subheading'],
    [PhraseSlot::Label, 'filament/user-resource.pages.edit-user.navigation_label'],
]);

it('compiles a page action label under the class kebab', function () {
    $compiler = new PhraseKeyCompiler;

    $key = $compiler->compile(new PhraseIdentity(
        catalogId: 'filament.user-resource',
        scope: PhraseScope::Pages,
        path: ['edit-user', 'actions'],
        name: 'first_action',
        slot: PhraseSlot::Label,
    ));

    expect($key)->toBe('filament/user-resource.pages.edit-user.actions.first_action.label');
});

it('compiles a table column under table.columns', function () {
    $compiler = new PhraseKeyCompiler;

    $key = $compiler->compile(new PhraseIdentity(
        catalogId: 'filament.user-resource',
        scope: PhraseScope::Table,
        path: ['columns'],
        name: 'email',
        slot: PhraseSlot::Label,
    ));

    expect($key)->toBe('filament/user-resource.table.columns.email.label');
});

it('normalizes dotted relationship names into a single segment', function () {
    $compiler = new PhraseKeyCompiler;

    $key = $compiler->compile(new PhraseIdentity(
        catalogId: 'filament.post-resource',
        scope: PhraseScope::Table,
        path: ['columns'],
        name: 'author.name',
        slot: PhraseSlot::Label,
    ));

    expect($key)->toBe('filament/post-resource.table.columns.author__name.label');
});

it('rejects machine names that cannot live in a laravel translation key', function () {
    $compiler = new PhraseKeyCompiler;

    $compiler->compile(new PhraseIdentity(
        catalogId: 'filament.user-resource',
        scope: PhraseScope::Form,
        path: [],
        name: 'email address',
        slot: PhraseSlot::Label,
    ));
})->throws(InvalidPhraseNameException::class);
