<?php

declare(strict_types=1);

use Filament\Panel;
use Syriable\MessageCatalog\Binding\MessageOverrides;
use Syriable\MessageCatalog\Discovery\DomainRegistry;
use Syriable\MessageCatalog\Enums\MissingMessagePolicy;
use Syriable\MessageCatalog\MessageCatalogPlugin;
use Syriable\MessageCatalog\Tests\Fixtures\Schemas\User\EditForm;

it('hands registered discovery paths to the catalog registry on boot', function () {
    MessageCatalogPlugin::make()
        ->discoverIn(
            in: dirname(__DIR__).'/Fixtures/Schemas',
            for: 'Syriable\\MessageCatalog\\Tests\\Fixtures\\Schemas',
        )
        ->boot(Panel::make());

    expect(array_keys(app(DomainRegistry::class)->catalogs()))
        ->toContain(EditForm::class);
});

it('still merges catalog prefixes and mode on boot', function () {
    MessageCatalogPlugin::make()
        ->domainPrefixes(['Modules\\Identity' => 'identity'])
        ->onMissing(MissingMessagePolicy::Strict)
        ->boot(Panel::make());

    $registry = app(MessageOverrides::class);

    expect($registry->prefixes)->toBe(['Modules\\Identity' => 'identity'])
        ->and($registry->mode)->toBe(MissingMessagePolicy::Strict);
});
