<?php

declare(strict_types=1);

use Syriable\MessageCatalog\Discovery\DomainDiscoverer;
use Syriable\MessageCatalog\Discovery\DomainRegistry;
use Syriable\MessageCatalog\Exceptions\InvalidTranslationDomainException;
use Syriable\MessageCatalog\Tests\Fixtures\InvalidIdForm;
use Syriable\MessageCatalog\Tests\Fixtures\NamespacedIdForm;
use Syriable\MessageCatalog\Tests\Fixtures\Schemas\Plain\AbstractForm;
use Syriable\MessageCatalog\Tests\Fixtures\Schemas\Plain\PlainForm;
use Syriable\MessageCatalog\Tests\Fixtures\Schemas\Plain\SchemalessCatalog;
use Syriable\MessageCatalog\Tests\Fixtures\Schemas\User\EditForm;
use Syriable\MessageCatalog\Tests\Fixtures\Schemas\User\ProfileForm;

const SCHEMA_FIXTURE_NAMESPACE = 'Syriable\\MessageCatalog\\Tests\\Fixtures\\Schemas';

function schemaFixturePath(): string
{
    return dirname(__DIR__).'/Fixtures/Schemas';
}

it('discovers a catalog that builds its schema with configure()', function () {
    $catalogs = app(DomainDiscoverer::class)->discover(
        schemaFixturePath(),
        SCHEMA_FIXTURE_NAMESPACE,
    );

    expect($catalogs)->toHaveKey(EditForm::class)
        ->and($catalogs[EditForm::class]->method)->toBe('configure')
        ->and($catalogs[EditForm::class]->catalogId)->toBe('identity.user-edit');
});

it('discovers a catalog that builds its schema with form()', function () {
    $catalogs = app(DomainDiscoverer::class)->discover(
        schemaFixturePath(),
        SCHEMA_FIXTURE_NAMESPACE,
    );

    expect($catalogs)->toHaveKey(ProfileForm::class)
        ->and($catalogs[ProfileForm::class]->method)->toBe('form');
});

it('skips a schema class that does not implement the catalog contract', function () {
    $catalogs = app(DomainDiscoverer::class)->discover(
        schemaFixturePath(),
        SCHEMA_FIXTURE_NAMESPACE,
    );

    expect($catalogs)->not->toHaveKey(PlainForm::class);
});

it('skips a catalog that has no schema builder', function () {
    $catalogs = app(DomainDiscoverer::class)->discover(
        schemaFixturePath(),
        SCHEMA_FIXTURE_NAMESPACE,
    );

    expect($catalogs)->not->toHaveKey(SchemalessCatalog::class);
});

it('skips an abstract catalog', function () {
    $catalogs = app(DomainDiscoverer::class)->discover(
        schemaFixturePath(),
        SCHEMA_FIXTURE_NAMESPACE,
    );

    expect($catalogs)->not->toHaveKey(AbstractForm::class);
});

it('returns nothing for a directory that does not exist', function () {
    $catalogs = app(DomainDiscoverer::class)->discover(
        schemaFixturePath().'/missing',
        SCHEMA_FIXTURE_NAMESPACE.'\\Missing',
    );

    expect($catalogs)->toBe([]);
});

it('accepts a catalog id namespaced against a translation namespace', function () {
    $catalog = app(DomainDiscoverer::class)->catalogFor(NamespacedIdForm::class);

    expect($catalog?->catalogId)->toBe('identity::users.edit');
});

it('rejects a catalog id that is neither dotted nor namespaced', function () {
    app(DomainDiscoverer::class)->catalogFor(InvalidIdForm::class);
})->throws(InvalidTranslationDomainException::class, 'identity::users::edit');

it('collects catalogs from a registered directory', function () {
    $catalogs = app(DomainRegistry::class)
        ->discover(in: schemaFixturePath(), for: SCHEMA_FIXTURE_NAMESPACE)
        ->catalogs();

    expect(array_keys($catalogs))
        ->toContain(EditForm::class)
        ->toContain(ProfileForm::class);
});

it('collects catalogs from configured discovery paths', function () {
    config()->set('messages.discover_paths', [
        ['path' => schemaFixturePath(), 'namespace' => SCHEMA_FIXTURE_NAMESPACE],
    ]);

    expect(array_keys(app(DomainRegistry::class)->catalogs()))
        ->toContain(EditForm::class);
});

it('ignores malformed configured discovery paths', function () {
    config()->set('messages.discover_paths', [
        ['path' => schemaFixturePath()],
        'not-an-array',
        ['path' => '', 'namespace' => ''],
    ]);

    expect(app(DomainRegistry::class)->catalogs())->toBe([]);
});

it('registers a directory only once', function () {
    $once = app(DomainRegistry::class)
        ->discover(in: schemaFixturePath(), for: SCHEMA_FIXTURE_NAMESPACE)
        ->catalogs();

    app(DomainRegistry::class)->flush();

    $twice = app(DomainRegistry::class)
        ->discover(in: schemaFixturePath(), for: SCHEMA_FIXTURE_NAMESPACE)
        ->discover(in: schemaFixturePath(), for: SCHEMA_FIXTURE_NAMESPACE)
        ->catalogs();

    expect($twice)->toHaveCount(count($once))
        ->and(array_keys($twice))->toBe(array_keys($once));
});
