<?php

declare(strict_types=1);

use Filament\Panel;
use Syriable\Translation\Binding\MessageOverrides;
use Syriable\Translation\Discovery\DomainRegistry;
use Syriable\Translation\Enums\MissingMessagePolicy;
use Syriable\Translation\TranslationPlugin;
use Syriable\Translation\Tests\Fixtures\Schemas\User\EditForm;

it('hands registered discovery paths to the catalog registry on boot', function () {
    TranslationPlugin::make()
        ->discoverIn(
            in: dirname(__DIR__).'/Fixtures/Schemas',
            for: 'Syriable\\Translation\\Tests\\Fixtures\\Schemas',
        )
        ->boot(Panel::make());

    expect(array_keys(app(DomainRegistry::class)->catalogs()))
        ->toContain(EditForm::class);
});

it('still merges catalog prefixes and mode on boot', function () {
    TranslationPlugin::make()
        ->domainPrefixes(['Modules\\Identity' => 'identity'])
        ->onMissing(MissingMessagePolicy::Strict)
        ->boot(Panel::make());

    $registry = app(MessageOverrides::class);

    expect($registry->prefixes)->toBe(['Modules\\Identity' => 'identity'])
        ->and($registry->mode)->toBe(MissingMessagePolicy::Strict);
});
