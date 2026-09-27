<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\AutoTranslator\Domains\SchemaDomainRegistry;
use Syriable\Filament\Plugins\AutoTranslator\Exceptions\InvalidTranslationDomainException;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\InvalidIdForm;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\NamespacedIdForm;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\Schemas\Plain\AbstractForm;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\Schemas\Plain\DomainWithoutSchema;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\Schemas\Plain\PlainForm;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\Schemas\User\ChromeForm;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\Schemas\User\EditForm;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\Schemas\User\ProfileForm;

const SCHEMA_FIXTURE_NAMESPACE = 'Syriable\\Filament\\Plugins\\AutoTranslator\\Tests\\Fixtures\\Schemas';

function schemaFixturePath(): string
{
    return dirname(__DIR__).'/Fixtures/Schemas';
}

function discoveredSchemaDomains(): array
{
    $registry = app(SchemaDomainRegistry::class);
    $registry->register(schemaFixturePath(), SCHEMA_FIXTURE_NAMESPACE);

    return $registry->all();
}

it('discovers a schema domain that builds its schema with configure()', function () {
    $domains = discoveredSchemaDomains();

    expect($domains)->toHaveKey(EditForm::class)
        ->and($domains[EditForm::class]->schemaMethod)->toBe('configure')
        ->and($domains[EditForm::class]->domain)->toBe('identity.user-edit')
        ->and($domains[EditForm::class]->chromeMethod)->toBeNull();
});

it('discovers a schema domain that builds its schema with form()', function () {
    expect(discoveredSchemaDomains()[ProfileForm::class]->schemaMethod)->toBe('form');
});

it('records the chrome builder a schema domain wraps its schema in', function () {
    expect(discoveredSchemaDomains()[ChromeForm::class]->chromeMethod)->toBe('make');
});

it('skips classes that are not schema domains', function (string $class) {
    expect(discoveredSchemaDomains())->not->toHaveKey($class);
})->with([
    'no declared domain' => [PlainForm::class],
    'no schema builder' => [DomainWithoutSchema::class],
    'abstract' => [AbstractForm::class],
]);

it('finds nothing in a directory that does not exist', function () {
    app(SchemaDomainRegistry::class)->register(schemaFixturePath().'/missing', SCHEMA_FIXTURE_NAMESPACE.'\\Missing');

    expect(app(SchemaDomainRegistry::class)->all())->toBe([]);
});

it('accepts a domain namespaced against a translation namespace', function () {
    expect(app(SchemaDomainRegistry::class)->inspect(NamespacedIdForm::class)?->domain)->toBe('identity::users.edit');
});

it('rejects a domain that is neither dotted nor namespaced', function () {
    app(SchemaDomainRegistry::class)->inspect(InvalidIdForm::class);
})->throws(InvalidTranslationDomainException::class, 'identity::users::edit');

it('collects schema domains from configured discovery paths', function () {
    config()->set('filament-auto-translator.discover_paths', [
        ['path' => schemaFixturePath(), 'namespace' => SCHEMA_FIXTURE_NAMESPACE],
    ]);

    expect(array_keys(app(SchemaDomainRegistry::class)->all()))->toContain(EditForm::class);
});

it('ignores malformed configured discovery paths', function () {
    config()->set('filament-auto-translator.discover_paths', [
        ['path' => schemaFixturePath()],
        'not-an-array',
        ['path' => '', 'namespace' => ''],
    ]);

    expect(app(SchemaDomainRegistry::class)->all())->toBe([]);
});

it('registers a directory only once', function () {
    $once = discoveredSchemaDomains();
    $twice = discoveredSchemaDomains();

    expect(array_keys($twice))->toBe(array_keys($once));
});
