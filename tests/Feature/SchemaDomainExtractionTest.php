<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Syriable\Filament\Plugins\AutoTranslator\Domains\SchemaDomainRegistry;
use Syriable\Filament\Plugins\AutoTranslator\Enums\ChangeType;
use Syriable\Filament\Plugins\AutoTranslator\Enums\MessageScope;
use Syriable\Filament\Plugins\AutoTranslator\Enums\MessageSlot;
use Syriable\Filament\Plugins\AutoTranslator\Extraction\LanguageFiles;
use Syriable\Filament\Plugins\AutoTranslator\Extraction\MessageExtractor;
use Syriable\Filament\Plugins\AutoTranslator\Messages\MessageIdentity;
use Syriable\Filament\Plugins\AutoTranslator\Scanning\MessageScanner;

beforeEach(function () {
    $this->langPath = sys_get_temp_dir().'/messages-schemas-'.uniqid('', true);
    File::ensureDirectoryExists($this->langPath);
    app()->useLangPath($this->langPath);
});

afterEach(function () {
    File::deleteDirectory($this->langPath);
});

function discoverSchemaFixtures(): void
{
    app(SchemaDomainRegistry::class)->register(dirname(__DIR__).'/Fixtures/Schemas', 'Syriable\\Filament\\Plugins\\AutoTranslator\\Tests\\Fixtures\\Schemas');
}

it('audits a discovered schema domain', function () {
    discoverSchemaFixtures();

    $keys = array_column(app(MessageScanner::class)->scan('en')->findings, 'key');

    expect($keys)
        ->toContain('identity/user-edit.form.components.name.label')
        ->toContain('identity/user-edit.form.components.email.label')
        ->toContain('identity/user-edit.form.components.country_id.label')
        ->toContain('identity/user-profile.form.components.timezone.label');
});

it('writes a language file for a discovered schema domain', function () {
    discoverSchemaFixtures();

    app(MessageExtractor::class)->extract('en');

    $path = lang_path('en/identity/user-edit.php');

    expect(is_file($path))->toBeTrue()
        ->and(include $path)->toMatchArray([
            'form' => [
                'components' => [
                    'name' => ['label' => 'Name'],
                    'email' => ['label' => 'Email'],
                    'country_id' => ['label' => 'Country id'],
                ],
            ],
        ]);
});

it('writes a language file per locale', function () {
    discoverSchemaFixtures();

    app(MessageExtractor::class)->extract('en');
    app(MessageExtractor::class)->extract('ar');

    expect(is_file(lang_path('en/identity/user-edit.php')))->toBeTrue()
        ->and(is_file(lang_path('ar/identity/user-edit.php')))->toBeTrue();
});

it('does not overwrite copy that is already translated', function () {
    discoverSchemaFixtures();

    app(LanguageFiles::class)->write(lang_path('ar/identity/user-edit.php'), [
        'form' => [
            'components' => [
                'name' => ['label' => 'الاسم'],
            ],
        ],
    ]);

    app(MessageExtractor::class)->extract('ar');

    $loaded = include lang_path('ar/identity/user-edit.php');

    expect($loaded['form']['components']['name']['label'])->toBe('الاسم')
        ->and($loaded['form']['components']['email']['label'])->toBe('Email');
});

it('prunes a key whose component left the schema', function () {
    discoverSchemaFixtures();

    app(LanguageFiles::class)->write(lang_path('en/identity/user-edit.php'), [
        'form' => [
            'components' => [
                'removed_field' => ['label' => 'Removed field'],
            ],
        ],
    ]);

    $writes = app(MessageExtractor::class)->extract('en');
    $loaded = include lang_path('en/identity/user-edit.php');

    expect($loaded['form']['components'])->not->toHaveKey('removed_field')
        ->and(array_map(fn ($change) => $change->type, $writes))->toContain(ChangeType::Deleted);
});

it('writes nothing when no discovery path is registered', function () {
    expect(app(MessageExtractor::class)->extract('en'))->toBe([])
        ->and(is_file(lang_path('en/identity/user-edit.php')))->toBeFalse();
});

it('includes discovered schema domains in the auto-translator:extract command', function () {
    discoverSchemaFixtures();

    $this->artisan('auto-translator:extract', ['--locale' => 'en,ar'])
        ->assertSuccessful();

    $english = include lang_path('en/identity/user-edit.php');
    $arabic = include lang_path('ar/identity/user-edit.php');

    expect($english['form']['components']['email']['label'])->toBe('Email')
        ->and($arabic['form']['components']['email']['label'])->toBe('Email');
});

it('walks the chrome a schema domain builds around its schema', function () {
    discoverSchemaFixtures();

    $keys = array_column(app(MessageScanner::class)->scan('en')->findings, 'key');

    // the section's own heading is an optional slot, so it is remembered for
    // pruning but never stubbed; its footer's copy is required and is
    expect($keys)
        ->toContain('identity/user-chrome.form.components.account.schema.actions.register.label')
        ->toContain('identity/user-chrome.form.components.account.schema.terms.body');
});

it('lends a keyed wrapper its segment to the schema it embeds', function () {
    discoverSchemaFixtures();

    $keys = array_column(app(MessageScanner::class)->scan('en')->findings, 'key');

    // the field is inside the section as surely as the footer button beside
    // it is, so it reads the same path; being reached through an embedded
    // schema is how Filament renders it, not where it lives
    expect($keys)
        ->toContain('identity/user-chrome.form.components.account.schema.nickname.label')
        ->not->toContain('identity/user-chrome.form.components.nickname.label');
});

it('keeps a validation attribute the walk never stubs', function () {
    discoverSchemaFixtures();

    $writer = app(LanguageFiles::class);
    $path = $writer->pathFor('identity.user-edit', 'en');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, '<?php return '.var_export([
        'form' => ['components' => [
            'name' => ['label' => 'Name', 'validation_attribute' => 'full name'],
        ]],
    ], true).';');

    app(MessageExtractor::class)->extract('en');

    $written = require $path;

    // an optional slot is never stubbed, so it must not be pruned either:
    // the key sits under a field the walk reached, which keeps it alive
    expect($written['form']['components']['name']['validation_attribute'])->toBe('full name');
});

it('prunes a validation attribute whose field left the schema', function () {
    discoverSchemaFixtures();

    $writer = app(LanguageFiles::class);
    $path = $writer->pathFor('identity.user-edit', 'en');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, '<?php return '.var_export([
        'form' => ['components' => [
            'deleted_field' => ['label' => 'Gone', 'validation_attribute' => 'gone'],
        ]],
    ], true).';');

    app(MessageExtractor::class)->extract('en');

    $written = require $path;

    expect($written['form']['components'])->not->toHaveKey('deleted_field');
});

it('writes a first language file into a module that has none', function () {
    File::ensureDirectoryExists(base_path('modules/billing'));

    try {
        $extractor = app(MessageExtractor::class);
        $writes = $extractor->extractIdentities([
            new MessageIdentity('billing::invoice', MessageScope::Form, [], 'total', MessageSlot::Label),
        ], 'en');

        $written = base_path('modules/billing/resources/lang/en/invoice.php');

        expect($writes)->toHaveCount(1)
            ->and(is_file($written))->toBeTrue()
            ->and(include $written)->toBe(['form' => ['components' => ['total' => ['label' => 'Total']]]])
            ->and($extractor->registeredNamespaces())
            ->toBe(['billing' => base_path('modules/billing').'/resources/lang']);
    } finally {
        File::deleteDirectory(base_path('modules'));
    }
});

it('reports the namespace it had to register itself', function () {
    File::ensureDirectoryExists(base_path('modules/billing'));

    try {
        // the namespace is registered by the files the command itself uses
        $files = app(LanguageFiles::class);
        $files->ensureNamespace('billing::invoice');
        app()->instance(LanguageFiles::class, $files);

        $this->artisan('auto-translator:extract')
            ->expectsOutputToContain('Registered the translation namespace [billing]')
            ->assertSuccessful();
    } finally {
        File::deleteDirectory(base_path('modules'));
    }
});
