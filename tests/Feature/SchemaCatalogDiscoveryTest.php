<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\AutoTranslator\Discovery\SchemaCatalogDiscoverer;
use Syriable\Filament\Plugins\AutoTranslator\Discovery\SchemaCatalogRegistry;
use Syriable\Filament\Plugins\AutoTranslator\Exceptions\InvalidCatalogIdException;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\InvalidIdForm;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\NamespacedIdForm;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\Schemas\Plain\AbstractForm;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\Schemas\Plain\PlainForm;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\Schemas\Plain\SchemalessCatalog;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\Schemas\User\EditForm;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\Schemas\User\ProfileForm;

const SCHEMA_FIXTURE_NAMESPACE = 'Syriable\\Filament\\Plugins\\AutoTranslator\\Tests\\Fixtures\\Schemas';

function schemaFixturePath(): string
{
    return dirname(__DIR__).'/Fixtures/Schemas';
}

it('discovers a catalog that builds its schema with configure()', function () {
    $catalogs = app(SchemaCatalogDiscoverer::class)->discover(
        schemaFixturePath(),
        SCHEMA_FIXTURE_NAMESPACE,
    );

    expect($catalogs)->toHaveKey(EditForm::class)
        ->and($catalogs[EditForm::class]->method)->toBe('configure')
        ->and($catalogs[EditForm::class]->catalogId)->toBe('identity.user-edit');
});

it('discovers a catalog that builds its schema with form()', function () {
    $catalogs = app(SchemaCatalogDiscoverer::class)->discover(
        schemaFixturePath(),
        SCHEMA_FIXTURE_NAMESPACE,
    );

    expect($catalogs)->toHaveKey(ProfileForm::class)
        ->and($catalogs[ProfileForm::class]->method)->toBe('form');
});

it('skips a schema class that does not implement the catalog contract', function () {
    $catalogs = app(SchemaCatalogDiscoverer::class)->discover(
        schemaFixturePath(),
        SCHEMA_FIXTURE_NAMESPACE,
    );

    expect($catalogs)->not->toHaveKey(PlainForm::class);
});

it('skips a catalog that has no schema builder', function () {
    $catalogs = app(SchemaCatalogDiscoverer::class)->discover(
        schemaFixturePath(),
        SCHEMA_FIXTURE_NAMESPACE,
    );

    expect($catalogs)->not->toHaveKey(SchemalessCatalog::class);
});

it('skips an abstract catalog', function () {
    $catalogs = app(SchemaCatalogDiscoverer::class)->discover(
        schemaFixturePath(),
        SCHEMA_FIXTURE_NAMESPACE,
    );

    expect($catalogs)->not->toHaveKey(AbstractForm::class);
});

it('returns nothing for a directory that does not exist', function () {
    $catalogs = app(SchemaCatalogDiscoverer::class)->discover(
        schemaFixturePath().'/missing',
        SCHEMA_FIXTURE_NAMESPACE.'\\Missing',
    );

    expect($catalogs)->toBe([]);
});

it('accepts a catalog id namespaced against a translation namespace', function () {
    $catalog = app(SchemaCatalogDiscoverer::class)->catalogFor(NamespacedIdForm::class);

    expect($catalog?->catalogId)->toBe('identity::users.edit');
});

it('rejects a catalog id that is neither dotted nor namespaced', function () {
    app(SchemaCatalogDiscoverer::class)->catalogFor(InvalidIdForm::class);
})->throws(InvalidCatalogIdException::class, 'identity::users::edit');

it('collects catalogs from a registered directory', function () {
    $catalogs = app(SchemaCatalogRegistry::class)
        ->discover(in: schemaFixturePath(), for: SCHEMA_FIXTURE_NAMESPACE)
        ->catalogs();

    expect(array_keys($catalogs))
        ->toContain(EditForm::class)
        ->toContain(ProfileForm::class);
});

it('collects catalogs from configured discovery paths', function () {
    config()->set('auto-translator.schema_catalog_paths', [
        ['path' => schemaFixturePath(), 'namespace' => SCHEMA_FIXTURE_NAMESPACE],
    ]);

    expect(array_keys(app(SchemaCatalogRegistry::class)->catalogs()))
        ->toContain(EditForm::class);
});

it('ignores malformed configured discovery paths', function () {
    config()->set('auto-translator.schema_catalog_paths', [
        ['path' => schemaFixturePath()],
        'not-an-array',
        ['path' => '', 'namespace' => ''],
    ]);

    expect(app(SchemaCatalogRegistry::class)->catalogs())->toBe([]);
});

it('registers a directory only once', function () {
    $registry = app(SchemaCatalogRegistry::class)
        ->discover(in: schemaFixturePath(), for: SCHEMA_FIXTURE_NAMESPACE)
        ->discover(in: schemaFixturePath(), for: SCHEMA_FIXTURE_NAMESPACE);

    expect($registry->catalogs())->toHaveCount(2);
});
