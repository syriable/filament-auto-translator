<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Syriable\Translation\Catalog\CatalogWriter;
use Syriable\Translation\Discovery\DomainRegistry;
use Syriable\Translation\Extraction\MessageExtractor;
use Syriable\Translation\Extraction\MessageScanner;

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
    app(DomainRegistry::class)->discover(
        in: dirname(__DIR__).'/Fixtures/Schemas',
        for: 'Syriable\\Translation\\Tests\\Fixtures\\Schemas',
    );
}

it('audits a discovered schema catalog', function () {
    discoverSchemaFixtures();

    $keys = array_column(app(MessageScanner::class)->audit('en'), 'key');

    expect($keys)
        ->toContain('identity/user-edit.form.components.name.label')
        ->toContain('identity/user-edit.form.components.email.label')
        ->toContain('identity/user-edit.form.components.country_id.label')
        ->toContain('identity/user-profile.form.components.timezone.label');
});

it('writes a language file for a discovered schema catalog', function () {
    discoverSchemaFixtures();

    app(MessageExtractor::class)->sync('en');

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

    app(MessageExtractor::class)->sync('en');
    app(MessageExtractor::class)->sync('ar');

    expect(is_file(lang_path('en/identity/user-edit.php')))->toBeTrue()
        ->and(is_file(lang_path('ar/identity/user-edit.php')))->toBeTrue();
});

it('does not overwrite copy that is already translated', function () {
    discoverSchemaFixtures();

    app(CatalogWriter::class)->persist(lang_path('ar/identity/user-edit.php'), [
        'form' => [
            'components' => [
                'name' => ['label' => 'الاسم'],
            ],
        ],
    ]);

    app(MessageExtractor::class)->sync('ar');

    $loaded = include lang_path('ar/identity/user-edit.php');

    expect($loaded['form']['components']['name']['label'])->toBe('الاسم')
        ->and($loaded['form']['components']['email']['label'])->toBe('Email');
});

it('prunes a key whose component left the schema', function () {
    discoverSchemaFixtures();

    app(CatalogWriter::class)->persist(lang_path('en/identity/user-edit.php'), [
        'form' => [
            'components' => [
                'removed_field' => ['label' => 'Removed field'],
            ],
        ],
    ]);

    $writes = app(MessageExtractor::class)->sync('en');
    $loaded = include lang_path('en/identity/user-edit.php');

    expect($loaded['form']['components'])->not->toHaveKey('removed_field')
        ->and(array_column($writes, 'action'))->toContain('deleted');
});

it('writes nothing when no discovery path is registered', function () {
    expect(app(MessageExtractor::class)->sync('en'))->toBe([])
        ->and(is_file(lang_path('en/identity/user-edit.php')))->toBeFalse();
});

it('includes discovered schema catalogs in the translations:extract command', function () {
    discoverSchemaFixtures();

    $this->artisan('translations:extract', ['--locale' => 'en,ar'])
        ->assertSuccessful();

    $english = include lang_path('en/identity/user-edit.php');
    $arabic = include lang_path('ar/identity/user-edit.php');

    expect($english['form']['components']['email']['label'])->toBe('Email')
        ->and($arabic['form']['components']['email']['label'])->toBe('Email');
});

it('walks the chrome a catalog builds around its schema', function () {
    discoverSchemaFixtures();

    $keys = array_column(app(MessageScanner::class)->audit('en'), 'key');

    // the section's own heading is an optional slot, so it is remembered for
    // pruning but never stubbed; its footer's copy is required and is
    expect($keys)
        ->toContain('identity/user-chrome.form.components.account.schema.actions.register.label')
        ->toContain('identity/user-chrome.form.components.account.schema.terms.body');
});

it('keeps the schema keys where they were when a catalog gains chrome', function () {
    discoverSchemaFixtures();

    $keys = array_column(app(MessageScanner::class)->audit('en'), 'key');

    // the embedded schema node is skipped, so the field keeps its own path
    // rather than moving under the wrapping section
    expect($keys)
        ->toContain('identity/user-chrome.form.components.nickname.label')
        ->not->toContain('identity/user-chrome.form.components.account.schema.nickname.label');
});
