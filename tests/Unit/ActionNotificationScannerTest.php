<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Syriable\Filament\Plugins\AutoTranslator\Audit\ActionNotificationScanner;

it('finds success and danger statuses in an action closure', function () {
    $action = Action::make('edit')
        ->action(function (): void {
            Notification::make()->success();
            Notification::make()->danger();
        });

    expect(app(ActionNotificationScanner::class)->statuses($action))
        ->toBe(['success', 'danger']);
});

it('returns no statuses when the action has a string handler', function () {
    $action = Action::make('edit')->action('save');

    expect(app(ActionNotificationScanner::class)->statuses($action))->toBe([]);
});

it('returns no statuses when the action closure has no notification', function () {
    $action = Action::make('edit')
        ->action(function (): void {});

    expect(app(ActionNotificationScanner::class)->statuses($action))->toBe([]);
});
