<?php

declare(strict_types=1);

use Syriable\Translation\Binding\MessageBinder;
use Syriable\Translation\Discovery\DomainRegistry;
use Syriable\Translation\Translations;
use Syriable\Translation\Tests\Fixtures\Schemas\User\EditForm;

it('discovers a directory and starts binding in one call', function () {
    Translations::discoverIn(
        dirname(__DIR__).'/Fixtures/Schemas',
        'Syriable\\Translation\\Tests\\Fixtures\\Schemas',
    );

    expect(array_keys(app(DomainRegistry::class)->catalogs()))->toContain(EditForm::class);
});

it('is safe to call more than once', function () {
    $path = dirname(__DIR__).'/Fixtures/Schemas';
    $namespace = 'Syriable\\Translation\\Tests\\Fixtures\\Schemas';

    Translations::discoverIn($path, $namespace);
    $first = app(DomainRegistry::class)->catalogs();

    Translations::discoverIn($path, $namespace);

    expect(array_keys(app(DomainRegistry::class)->catalogs()))->toBe(array_keys($first));
});

it('reports the domain a class belongs to', function () {
    expect(Translations::domainFor(EditForm::class))->toBe('identity.user-edit')
        ->and(Translations::domainFor(new class {}))->toBeNull();
});

it('works without a Filament panel', function () {
    Translations::discoverIn(
        dirname(__DIR__).'/Fixtures/Schemas',
        'Syriable\\Translation\\Tests\\Fixtures\\Schemas',
    );

    expect(app(MessageBinder::class))->toBeInstanceOf(MessageBinder::class)
        ->and(app(DomainRegistry::class)->catalogs())->not->toBeEmpty();
});
