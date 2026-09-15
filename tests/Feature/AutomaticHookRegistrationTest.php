<?php

declare(strict_types=1);

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Lang;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\PhraseCatalogForm;

it('binds a field label without registerHooks() or PhrasePlugin ever being called manually', function () {
    Lang::addLines([
        'filament/phrase-catalog-form.form.components.email.label' => 'Email address',
    ], 'en');

    $livewire = app(PhraseCatalogForm::class);
    $schema = Schema::make($livewire)->components([
        TextInput::make('email'),
    ]);

    $field = $schema->getComponents()[0];

    expect($field)->toBeInstanceOf(TextInput::class)
        ->and($field->getLabel())->toBe('Email address');
});
