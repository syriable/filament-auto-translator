<?php

declare(strict_types=1);

use Filament\Panel;
use Filament\Schemas\Components\Section;
use Syriable\FilamentAutoTranslator\AutoTranslatorPlugin;
use Syriable\FilamentAutoTranslator\Domains\DomainResolver;
use Syriable\FilamentAutoTranslator\Domains\SchemaDomainRegistry;
use Syriable\FilamentAutoTranslator\Enums\MissingMessagePolicy;
use Syriable\FilamentAutoTranslator\Settings;
use Syriable\FilamentAutoTranslator\Tests\Fixtures\Schemas\User\EditForm;

it('has a stable plugin id', function () {
    expect(AutoTranslatorPlugin::make()->getId())->toBe('filament-auto-translator');
});

it('hands registered discovery paths to the schema domain registry on boot', function () {
    AutoTranslatorPlugin::make()
        ->discoverIn(
            in: dirname(__DIR__).'/Fixtures/Schemas',
            for: 'Syriable\\FilamentAutoTranslator\\Tests\\Fixtures\\Schemas',
        )
        ->boot(Panel::make());

    expect(array_keys(app(SchemaDomainRegistry::class)->all()))
        ->toContain(EditForm::class);
});

it('applies its domain prefixes and missing-message policy on boot', function () {
    AutoTranslatorPlugin::make()
        ->domainPrefixes(['Modules\\Identity' => 'identity'])
        ->onMissing(MissingMessagePolicy::Strict)
        ->boot(Panel::make());

    expect(app(DomainResolver::class)->derive('Modules\\Identity\\UserResource'))->toBe('identity.user-resource')
        ->and(app(Settings::class)->policy())->toBe(MissingMessagePolicy::Strict);
});

it('starts binding on boot', function () {
    AutoTranslatorPlugin::make()->boot(Panel::make());

    expect(Section::hasMacro('messageName'))->toBeTrue();
});
