<?php

declare(strict_types=1);

use Filament\Panel;
use Illuminate\Support\Facades\File;
use Syriable\Filament\Plugins\AutoTranslator\AutoTranslator;
use Syriable\Filament\Plugins\AutoTranslator\Extraction\LanguageFiles;
use Syriable\Filament\Plugins\AutoTranslator\Extraction\MessageExtractor;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\Resources\InfolistResource;
use Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\Resources\ThrowingFormResource;

/**
 * Extraction may delete copy, so these pin down when it must not.
 */
beforeEach(function () {
    config()->set('filament-auto-translator.default_domain_prefix', 'filament');

    $this->langPath = sys_get_temp_dir().'/auto-translator-resources-'.uniqid('', true);
    File::ensureDirectoryExists($this->langPath);
    app()->useLangPath($this->langPath);

    $this->registerPanel(
        Panel::make()
            ->id('admin')
            ->path('admin')
            ->resources([InfolistResource::class, ThrowingFormResource::class]),
    );
});

afterEach(function () {
    File::deleteDirectory($this->langPath);
});

it('writes the keys of a resource infolist', function () {
    app(MessageExtractor::class)->extract('en');

    expect(require lang_path('en/filament/infolist-resource.php'))->toMatchArray([
        'form' => ['components' => ['name' => ['label' => 'Name']]],
        'infolist' => ['components' => ['status' => ['label' => 'Status']]],
    ]);
});

it('keeps infolist copy that is live and prunes only what is gone', function () {
    app(LanguageFiles::class)->write(lang_path('en/filament/infolist-resource.php'), [
        'infolist' => ['components' => [
            'status' => ['label' => 'State', 'helper_text' => 'Where it stands'],
            'removed' => ['label' => 'Removed'],
        ]],
    ]);

    app(MessageExtractor::class)->extract('en');
    $written = require lang_path('en/filament/infolist-resource.php');

    expect($written['infolist']['components'])->toBe([
        'status' => ['label' => 'State', 'helper_text' => 'Where it stands'],
    ]);
});

it('never prunes the form of a resource whose form cannot be built', function () {
    $path = lang_path('en/filament/throwing-form-resource.php');
    app(LanguageFiles::class)->write($path, [
        'form' => ['components' => ['email' => ['label' => 'Email']]],
    ]);

    app(MessageExtractor::class)->extract('en');

    expect((require $path)['form'])->toBe(['components' => ['email' => ['label' => 'Email']]]);
});

it('prunes the pages of a resource that no longer registers them', function () {
    $path = lang_path('en/filament/infolist-resource.php');
    app(LanguageFiles::class)->write($path, [
        'pages' => ['deleted-page' => ['title' => 'Gone']],
    ]);

    app(MessageExtractor::class)->extract('en');

    expect(require $path)->not->toHaveKey('pages');
});

it('never prunes the form of a schema domain whose chrome builder throws', function () {
    AutoTranslator::discoverIn(dirname(__DIR__).'/Fixtures/ThrowingChrome', 'Syriable\\Filament\\Plugins\\AutoTranslator\\Tests\\Fixtures\\ThrowingChrome');

    $path = lang_path('en/identity/throwing-chrome.php');
    app(LanguageFiles::class)->write($path, [
        'form' => ['components' => ['account' => ['heading' => 'Your account']]],
    ]);

    app(MessageExtractor::class)->extract('en');

    expect((require $path)['form']['components'])
        ->toHaveKey('account')
        ->toHaveKey('nickname');
});
