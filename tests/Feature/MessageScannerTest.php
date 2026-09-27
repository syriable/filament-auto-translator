<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Lang;
use Syriable\Filament\Plugins\AutoTranslator\Binding\MessageOptions;
use Syriable\Filament\Plugins\AutoTranslator\Enums\MessageScope;
use Syriable\Filament\Plugins\AutoTranslator\Enums\MessageSlot;
use Syriable\Filament\Plugins\AutoTranslator\Enums\ResolutionOutcome;
use Syriable\Filament\Plugins\AutoTranslator\Messages\MessageIdentity;
use Syriable\Filament\Plugins\AutoTranslator\Scanning\MessageScanner;
use Syriable\Filament\Plugins\AutoTranslator\Scanning\ScanHost;

it('reports a missing required message as a finding', function () {
    $findings = app(MessageScanner::class)->scanIdentities([
        new MessageIdentity(
            domain: 'filament.user-resource',
            scope: MessageScope::Form,
            path: [],
            name: 'email',
            slot: MessageSlot::Label,
        ),
    ]);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->outcome)->toBe(ResolutionOutcome::Missing)
        ->and($findings[0]->key)->toBe('filament/user-resource.form.components.email.label');
});

it('does not report a present message as a finding', function () {
    Lang::addLines([
        'filament/user-resource.form.components.email.label' => 'Email address',
    ], 'en');

    $findings = app(MessageScanner::class)->scanIdentities([
        new MessageIdentity(
            domain: 'filament.user-resource',
            scope: MessageScope::Form,
            path: [],
            name: 'email',
            slot: MessageSlot::Label,
        ),
    ]);

    expect($findings)->toBeEmpty();
});

it('reports a missing action notification title from the action closure', function () {
    $owner = new ScanHost;
    app(MessageOptions::class)->setDomain($owner, 'filament.user-resource');

    $schema = Schema::make($owner)->components([
        Action::make('edit')
            ->action(function (): void {
                Notification::make()
                    ->success()
                    ->send();
            }),
    ]);

    $findings = app(MessageScanner::class)->scanComponents($schema->getComponents())->findings;

    expect(array_column($findings, 'key'))
        ->toContain('filament/user-resource.form.components.actions.edit.label')
        ->toContain('filament/user-resource.form.components.actions.edit.notifications.success.title');
});

it('reports a missing schema text body inside a keyed fieldset', function () {
    $owner = new ScanHost;
    app(MessageOptions::class)->setDomain($owner, 'filament.user-resource');

    $schema = Schema::make($owner)->components([
        Fieldset::make()
            ->key('authorization', isInheritable: false)
            ->schema([
                Text::make('or'),
                TextEntry::make('new'),
            ]),
    ]);

    $findings = app(MessageScanner::class)->scanComponents($schema->getComponents())->findings;

    expect(array_column($findings, 'key'))
        ->toContain('filament/user-resource.form.components.authorization.schema.or.body')
        ->toContain('filament/user-resource.infolist.components.authorization.schema.new.label');
});

it('does not invent a notification title when the action does not send one', function () {
    $owner = new ScanHost;
    app(MessageOptions::class)->setDomain($owner, 'filament.user-resource');

    $schema = Schema::make($owner)->components([
        Action::make('edit')
            ->action(function (): void {}),
    ]);

    $findings = app(MessageScanner::class)->scanComponents($schema->getComponents())->findings;

    expect(array_column($findings, 'key'))
        ->toContain('filament/user-resource.form.components.actions.edit.label')
        ->not->toContain('filament/user-resource.form.components.actions.edit.notifications.success.title');
});
