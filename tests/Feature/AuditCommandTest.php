<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Syriable\Filament\Plugins\AutoTranslator\AutoTranslator;

beforeEach(function () {
    $this->langPath = sys_get_temp_dir().'/auto-translator-audit-'.uniqid('', true);
    File::ensureDirectoryExists($this->langPath);
    app()->useLangPath($this->langPath);

    AutoTranslator::discoverIn(dirname(__DIR__).'/Fixtures/Schemas/User', 'Syriable\\Filament\\Plugins\\AutoTranslator\\Tests\\Fixtures\\Schemas\\User');
});

afterEach(function () {
    File::deleteDirectory($this->langPath);
});

it('lists missing messages without failing by default', function () {
    $this->artisan('auto-translator:audit', ['--locale' => 'en'])
        ->expectsOutputToContain('identity/user-edit.form.components.email.label')
        ->assertSuccessful();
});

it('fails on missing messages when asked to', function () {
    $this->artisan('auto-translator:audit', ['--locale' => 'en', '--fail-on-missing' => true])
        ->assertFailed();
});

it('passes once extraction has written every message', function () {
    $this->artisan('auto-translator:extract', ['--locale' => 'en'])->assertSuccessful();

    $this->artisan('auto-translator:audit', ['--locale' => 'en', '--fail-on-missing' => true])
        ->expectsOutputToContain('No missing messages.')
        ->assertSuccessful();
});

it('writes nothing on a dry run', function () {
    $this->artisan('auto-translator:extract', ['--locale' => 'en', '--dry-run' => true])
        ->expectsOutputToContain('would create')
        ->assertSuccessful();

    expect(File::exists(lang_path('en/identity/user-edit.php')))->toBeFalse();
});

it('writes several locales in one run', function () {
    $this->artisan('auto-translator:extract', ['--locale' => 'en,ar'])->assertSuccessful();

    expect(File::exists(lang_path('en/identity/user-edit.php')))->toBeTrue()
        ->and(File::exists(lang_path('ar/identity/user-edit.php')))->toBeTrue();
});
