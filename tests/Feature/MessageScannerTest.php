<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Lang;
use Syriable\Translation\Binding\MessageBinder;
use Syriable\Translation\Enums\MessageSlot;
use Syriable\Translation\Enums\MessageSurface;
use Syriable\Translation\Enums\ResolutionOutcome;
use Syriable\Translation\Extraction\ExtractionHost;
use Syriable\Translation\Extraction\MessageScanner;
use Syriable\Translation\MessageIdentity;

it('reports a missing required message as a finding', function () {
    $findings = app(MessageScanner::class)->auditIdentities([
        new MessageIdentity(
            catalogId: 'filament.user-resource',
            scope: MessageSurface::Form,
            path: [],
            name: 'email',
            slot: MessageSlot::Label,
        ),
    ]);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]['decision'])->toBe(ResolutionOutcome::Missing->value)
        ->and($findings[0]['key'])->toBe('filament/user-resource.form.components.email.label');
});

it('does not report a present message as a finding', function () {
    Lang::addLines([
        'filament/user-resource.form.components.email.label' => 'Email address',
    ], 'en');

    $findings = app(MessageScanner::class)->auditIdentities([
        new MessageIdentity(
            catalogId: 'filament.user-resource',
            scope: MessageSurface::Form,
            path: [],
            name: 'email',
            slot: MessageSlot::Label,
        ),
    ]);

    expect($findings)->toBeEmpty();
});

it('reports a missing action notification title from the action closure', function () {
    $owner = new ExtractionHost;
    app(MessageBinder::class)->setCatalogId($owner, 'filament.user-resource');

    $schema = Schema::make($owner)->components([
        Action::make('edit')
            ->action(function (): void {
                Notification::make()
                    ->success()
                    ->send();
            }),
    ]);

    $findings = app(MessageScanner::class)->auditComponents(
        $schema->getComponents(),
        'filament.user-resource',
    );

    expect(array_column($findings, 'key'))
        ->toContain('filament/user-resource.form.components.actions.edit.label')
        ->toContain('filament/user-resource.form.components.actions.edit.notifications.success.title');
});

it('reports a missing schema text body inside a keyed fieldset', function () {
    $owner = new ExtractionHost;
    app(MessageBinder::class)->setCatalogId($owner, 'filament.user-resource');

    $schema = Schema::make($owner)->components([
        Fieldset::make()
            ->key('authorization', isInheritable: false)
            ->schema([
                Text::make('or'),
                TextEntry::make('new'),
            ]),
    ]);

    $findings = app(MessageScanner::class)->auditComponents(
        $schema->getComponents(),
        'filament.user-resource',
    );

    expect(array_column($findings, 'key'))
        ->toContain('filament/user-resource.form.components.authorization.schema.or.body')
        ->toContain('filament/user-resource.infolist.components.authorization.schema.new.label');
});

it('does not invent a notification title when the action does not send one', function () {
    $owner = new ExtractionHost;
    app(MessageBinder::class)->setCatalogId($owner, 'filament.user-resource');

    $schema = Schema::make($owner)->components([
        Action::make('edit')
            ->action(function (): void {}),
    ]);

    $findings = app(MessageScanner::class)->auditComponents(
        $schema->getComponents(),
        'filament.user-resource',
    );

    expect(array_column($findings, 'key'))
        ->toContain('filament/user-resource.form.components.actions.edit.label')
        ->not->toContain('filament/user-resource.form.components.actions.edit.notifications.success.title');
});
