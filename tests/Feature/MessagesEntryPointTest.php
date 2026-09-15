<?php

declare(strict_types=1);

use Syriable\MessageCatalog\Binding\MessageBinder;
use Syriable\MessageCatalog\Discovery\DomainRegistry;
use Syriable\MessageCatalog\Messages;
use Syriable\MessageCatalog\Tests\Fixtures\Schemas\User\EditForm;

it('discovers a directory and starts binding in one call', function () {
    Messages::discoverIn(
        dirname(__DIR__).'/Fixtures/Schemas',
        'Syriable\\MessageCatalog\\Tests\\Fixtures\\Schemas',
    );

    expect(array_keys(app(DomainRegistry::class)->catalogs()))->toContain(EditForm::class);
});

it('is safe to call more than once', function () {
    $path = dirname(__DIR__).'/Fixtures/Schemas';
    $namespace = 'Syriable\\MessageCatalog\\Tests\\Fixtures\\Schemas';

    Messages::discoverIn($path, $namespace);
    $first = app(DomainRegistry::class)->catalogs();

    Messages::discoverIn($path, $namespace);

    expect(array_keys(app(DomainRegistry::class)->catalogs()))->toBe(array_keys($first));
});

it('reports the domain a class belongs to', function () {
    expect(Messages::domainFor(EditForm::class))->toBe('identity.user-edit')
        ->and(Messages::domainFor(new class {}))->toBeNull();
});

it('works without a Filament panel', function () {
    Messages::discoverIn(
        dirname(__DIR__).'/Fixtures/Schemas',
        'Syriable\\MessageCatalog\\Tests\\Fixtures\\Schemas',
    );

    expect(app(MessageBinder::class))->toBeInstanceOf(MessageBinder::class)
        ->and(app(DomainRegistry::class)->catalogs())->not->toBeEmpty();
});
