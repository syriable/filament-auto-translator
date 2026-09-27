<?php

declare(strict_types=1);

use Syriable\FilamentAutoTranslator\Inlining\ChainEditor;

it('inserts label and placeholder after make on a multiline chain', function () {
    $source = <<<'PHP'
<?php

TextInput::make('name')
                ->required(),
PHP;

    $updated = app(ChainEditor::class)->onMake($source, 'name', [
        ['method' => 'label', 'key' => 'filament/user-resource.form.components.name.label'],
        ['method' => 'placeholder', 'key' => 'filament/user-resource.form.components.name.placeholder'],
    ]);

    expect($updated)->toBe(<<<'PHP'
<?php

TextInput::make('name')
                ->label(__('filament/user-resource.form.components.name.label'))
                ->placeholder(__('filament/user-resource.form.components.name.placeholder'))
                ->required(),
PHP);
});

it('skips a setter that is already on the chain', function () {
    $source = <<<'PHP'
<?php

TextInput::make('name')
    ->label('Name')
    ->required();
PHP;

    $updated = app(ChainEditor::class)->onMake($source, 'name', [
        ['method' => 'label', 'key' => 'filament/user-resource.form.components.name.label'],
        ['method' => 'placeholder', 'key' => 'filament/user-resource.form.components.name.placeholder'],
    ]);

    expect($updated)->toBe(<<<'PHP'
<?php

TextInput::make('name')
    ->placeholder(__('filament/user-resource.form.components.name.placeholder'))
    ->label('Name')
    ->required();
PHP);
});

it('inserts setters on a single-line make chain', function () {
    $source = "<?php\n\nTextInput::make('name')->required(),";

    $updated = app(ChainEditor::class)->onMake($source, 'name', [
        ['method' => 'label', 'key' => 'filament/user-resource.form.components.name.label'],
    ]);

    expect($updated)->toBe("<?php\n\nTextInput::make('name')->label(__('filament/user-resource.form.components.name.label'))->required(),");
});

it('rewrites a bare key argument to __()', function () {
    $source = <<<'PHP'
<?php

TextInput::make('name')
    ->label('filament/user-resource.form.components.name.label')
    ->required(),
PHP;

    $updated = app(ChainEditor::class)->onMake($source, 'name', [
        ['method' => 'label', 'key' => 'filament/user-resource.form.components.name.label'],
        ['method' => 'placeholder', 'key' => 'filament/user-resource.form.components.name.placeholder'],
    ]);

    expect($updated)->toBe(<<<'PHP'
<?php

TextInput::make('name')
    ->placeholder(__('filament/user-resource.form.components.name.placeholder'))
    ->label(__('filament/user-resource.form.components.name.label'))
    ->required(),
PHP);
});

it('does not modify a different make name', function () {
    $source = "<?php\n\nTextInput::make('email')->required(),";

    $updated = app(ChainEditor::class)->onMake($source, 'name', [
        ['method' => 'label', 'key' => 'filament/user-resource.form.components.name.label'],
    ]);

    expect($updated)->toBe($source);
});

it('inserts title on Notification::make inside the matching action', function () {
    $source = <<<'PHP'
<?php

Action::make('action')
    ->action(function (): void {
        Notification::make()
            ->success()
            ->send();
    }),
PHP;

    $updated = app(ChainEditor::class)->onNotification($source, 'action', 'success', [
        ['method' => 'title', 'key' => 'filament/user-resource.form.components.actions.action.notifications.success.title'],
    ]);

    expect($updated)->toBe(<<<'PHP'
<?php

Action::make('action')
    ->action(function (): void {
        Notification::make()
            ->title(__('filament/user-resource.form.components.actions.action.notifications.success.title'))
            ->success()
            ->send();
    }),
PHP);
});

it('does not write a success title onto a danger notification', function () {
    $source = <<<'PHP'
<?php

Action::make('action')
    ->action(function (): void {
        Notification::make()
            ->danger()
            ->send();
    }),
PHP;

    $updated = app(ChainEditor::class)->onNotification($source, 'action', 'success', [
        ['method' => 'title', 'key' => 'filament/user-resource.form.components.actions.action.notifications.success.title'],
    ]);

    expect($updated)->toBe($source);
});

it('does not treat a field named action as a notification owner', function () {
    $source = <<<'PHP'
<?php

TextInput::make('action')
    ->required();
PHP;

    $updated = app(ChainEditor::class)->onNotification($source, 'action', 'success', [
        ['method' => 'title', 'key' => 'filament/user-resource.form.components.actions.action.notifications.success.title'],
    ]);

    expect($updated)->toBe($source);
});
