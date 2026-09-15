<?php

declare(strict_types=1);

use Filament\Panel;
use Syriable\Filament\Plugins\AutoTranslator\Discovery\SchemaCatalogRegistry;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseMode;
use Syriable\Filament\Plugins\AutoTranslator\PhrasePlugin;
use Syriable\Filament\Plugins\AutoTranslator\PhraseRegistry;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\Schemas\User\EditForm;

it('hands registered discovery paths to the catalog registry on boot', function () {
    PhrasePlugin::make()
        ->discoverSchemaCatalogs(
            in: dirname(__DIR__).'/Fixtures/Schemas',
            for: 'Syriable\\Filament\\Plugins\\AutoTranslator\\Tests\\Fixtures\\Schemas',
        )
        ->boot(Panel::make());

    expect(array_keys(app(SchemaCatalogRegistry::class)->catalogs()))
        ->toContain(EditForm::class);
});

it('still merges catalog prefixes and mode on boot', function () {
    PhrasePlugin::make()
        ->catalogPrefixes(['Modules\\Identity' => 'identity'])
        ->mode(PhraseMode::Strict)
        ->boot(Panel::make());

    $registry = app(PhraseRegistry::class);

    expect($registry->prefixes)->toBe(['Modules\\Identity' => 'identity'])
        ->and($registry->mode)->toBe(PhraseMode::Strict);
});
