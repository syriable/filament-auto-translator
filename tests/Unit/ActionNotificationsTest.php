<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Syriable\Filament\Plugins\AutoTranslator\Binding\MessageOptions;
use Syriable\Filament\Plugins\AutoTranslator\Scanning\ActionNotifications;

it('finds success and danger statuses in an action closure', function () {
    $action = Action::make('edit')
        ->action(function (): void {
            Notification::make()->success();
            Notification::make()->danger();
        });

    expect(app(ActionNotifications::class)->statuses($action))
        ->toBe(['success', 'danger']);
});

it('returns no statuses when the action has a string handler', function () {
    $action = Action::make('edit')->action('save');

    expect(app(ActionNotifications::class)->statuses($action))->toBe([]);
});

it('returns no statuses when the action closure has no notification', function () {
    $action = Action::make('edit')
        ->action(function (): void {});

    expect(app(ActionNotifications::class)->statuses($action))->toBe([]);
});

it('builds one notification per status, owned by the action', function () {
    $action = Action::make('edit')
        ->action(function (): void {
            Notification::make()->warning()->send();
        });

    $notifications = app(ActionNotifications::class)->of($action);

    expect($notifications)->toHaveCount(1)
        ->and($notifications[0]->getStatus())->toBe('warning')
        ->and(app(MessageOptions::class)->owner($notifications[0]))->toBe($action);
});
