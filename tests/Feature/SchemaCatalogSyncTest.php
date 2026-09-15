<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Syriable\Filament\Plugins\AutoTranslator\Audit\PhraseAuditor;
use Syriable\Filament\Plugins\AutoTranslator\Discovery\SchemaCatalogRegistry;
use Syriable\Filament\Plugins\AutoTranslator\Sync\PhraseCatalogSyncer;
use Syriable\Filament\Plugins\AutoTranslator\Sync\PhraseLangWriter;

beforeEach(function () {
    $this->langPath = sys_get_temp_dir().'/auto-translator-schemas-'.uniqid('', true);
    File::ensureDirectoryExists($this->langPath);
    app()->useLangPath($this->langPath);
});

afterEach(function () {
    File::deleteDirectory($this->langPath);
});

function discoverSchemaFixtures(): void
{
    app(SchemaCatalogRegistry::class)->discover(
        in: dirname(__DIR__).'/Fixtures/Schemas',
        for: 'Syriable\\Filament\\Plugins\\AutoTranslator\\Tests\\Fixtures\\Schemas',
    );
}

it('audits a discovered schema catalog', function () {
    discoverSchemaFixtures();

    $keys = array_column(app(PhraseAuditor::class)->audit('en'), 'key');

    expect($keys)
        ->toContain('identity/user-edit.form.components.name.label')
        ->toContain('identity/user-edit.form.components.email.label')
        ->toContain('identity/user-edit.form.components.country_id.label')
        ->toContain('identity/user-profile.form.components.timezone.label');
});

it('writes a language file for a discovered schema catalog', function () {
    discoverSchemaFixtures();

    app(PhraseCatalogSyncer::class)->sync('en');

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

    app(PhraseCatalogSyncer::class)->sync('en');
    app(PhraseCatalogSyncer::class)->sync('ar');

    expect(is_file(lang_path('en/identity/user-edit.php')))->toBeTrue()
        ->and(is_file(lang_path('ar/identity/user-edit.php')))->toBeTrue();
});

it('does not overwrite copy that is already translated', function () {
    discoverSchemaFixtures();

    app(PhraseLangWriter::class)->persist(lang_path('ar/identity/user-edit.php'), [
        'form' => [
            'components' => [
                'name' => ['label' => 'الاسم'],
            ],
        ],
    ]);

    app(PhraseCatalogSyncer::class)->sync('ar');

    $loaded = include lang_path('ar/identity/user-edit.php');

    expect($loaded['form']['components']['name']['label'])->toBe('الاسم')
        ->and($loaded['form']['components']['email']['label'])->toBe('Email');
});

it('prunes a key whose component left the schema', function () {
    discoverSchemaFixtures();

    app(PhraseLangWriter::class)->persist(lang_path('en/identity/user-edit.php'), [
        'form' => [
            'components' => [
                'removed_field' => ['label' => 'Removed field'],
            ],
        ],
    ]);

    $writes = app(PhraseCatalogSyncer::class)->sync('en');
    $loaded = include lang_path('en/identity/user-edit.php');

    expect($loaded['form']['components'])->not->toHaveKey('removed_field')
        ->and(array_column($writes, 'action'))->toContain('deleted');
});

it('writes nothing when no discovery path is registered', function () {
    expect(app(PhraseCatalogSyncer::class)->sync('en'))->toBe([])
        ->and(is_file(lang_path('en/identity/user-edit.php')))->toBeFalse();
});

it('includes discovered schema catalogs in the phrases:sync command', function () {
    discoverSchemaFixtures();

    $this->artisan('phrases:sync', ['--locale' => 'en,ar'])
        ->assertSuccessful();

    $english = include lang_path('en/identity/user-edit.php');
    $arabic = include lang_path('ar/identity/user-edit.php');

    expect($english['form']['components']['email']['label'])->toBe('Email')
        ->and($arabic['form']['components']['email']['label'])->toBe('Email');
});
