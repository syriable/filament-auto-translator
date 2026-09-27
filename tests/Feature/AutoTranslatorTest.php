<?php

declare(strict_types=1);

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Lang;
use Syriable\Filament\Plugins\AutoTranslator\AutoTranslator;
use Syriable\Filament\Plugins\AutoTranslator\Binding\ComponentBinder;
use Syriable\Filament\Plugins\AutoTranslator\Domains\SchemaDomainRegistry;
use Syriable\Filament\Plugins\AutoTranslator\Enums\ResolutionOutcome;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\DomainForm;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\Schemas\User\EditForm;

it('discovers a directory and starts binding in one call', function () {
    AutoTranslator::discoverIn(
        dirname(__DIR__).'/Fixtures/Schemas',
        'Syriable\\Filament\\Plugins\\AutoTranslator\\Tests\\Fixtures\\Schemas',
    );

    expect(array_keys(app(SchemaDomainRegistry::class)->all()))->toContain(EditForm::class);
});

it('is safe to call more than once', function () {
    $path = dirname(__DIR__).'/Fixtures/Schemas';
    $namespace = 'Syriable\\Filament\\Plugins\\AutoTranslator\\Tests\\Fixtures\\Schemas';

    AutoTranslator::discoverIn($path, $namespace);
    $first = app(SchemaDomainRegistry::class)->all();

    AutoTranslator::discoverIn($path, $namespace);

    expect(array_keys(app(SchemaDomainRegistry::class)->all()))->toBe(array_keys($first));
});

it('reports the domain a class belongs to', function () {
    expect(AutoTranslator::domainFor(EditForm::class))->toBe('identity.user-edit')
        ->and(AutoTranslator::domainFor(new class {}))->toBeNull();
});

it('works without a Filament panel', function () {
    AutoTranslator::discoverIn(
        dirname(__DIR__).'/Fixtures/Schemas',
        'Syriable\\Filament\\Plugins\\AutoTranslator\\Tests\\Fixtures\\Schemas',
    );

    expect(TextInput::hasMacro('messageName'))->toBeTrue()
        ->and(app(SchemaDomainRegistry::class)->all())->not->toBeEmpty();
});

it('looks up a sub-key at a component identity by hand', function () {
    Lang::addLines([
        'filament/domain-form.form.components.role.options.admin' => 'Administrator',
        'filament/domain-form.form.components.role.greeting' => 'Hello :name',
        'filament/domain-form.form.components.role.count' => '{1} One role|[2,*] :count roles',
    ], 'en');

    app(ComponentBinder::class)->register();
    $field = Schema::make(app(DomainForm::class))->components([Select::make('role')])->getComponents()[0];

    expect(AutoTranslator::message($field, relative: 'options.admin'))->toBe('Administrator')
        ->and(AutoTranslator::message($field, relative: 'greeting', replace: ['name' => 'Ada']))->toBe('Hello Ada')
        ->and(AutoTranslator::message($field, relative: 'count', number: 3))->toBe('3 roles')
        ->and(AutoTranslator::message($field, relative: 'options.missing'))->toBeNull();
});

it('explains how a component resolved', function () {
    app(ComponentBinder::class)->register();
    $field = Schema::make(app(DomainForm::class))->components([Select::make('role')])->getComponents()[0];

    $resolution = AutoTranslator::explain($field);

    expect($resolution->key)->toBe('filament/domain-form.form.components.role.label')
        ->and($resolution->outcome)->toBe(ResolutionOutcome::Missing)
        ->and($resolution->reason)->not->toBeEmpty();
});
