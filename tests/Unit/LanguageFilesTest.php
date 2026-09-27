<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Syriable\Filament\Plugins\AutoTranslator\Domains\DomainName;
use Syriable\Filament\Plugins\AutoTranslator\Domains\DomainResolver;
use Syriable\Filament\Plugins\AutoTranslator\Exceptions\UnknownTranslationNamespaceException;
use Syriable\Filament\Plugins\AutoTranslator\Extraction\LanguageFiles;

afterEach(function () {
    File::deleteDirectory(base_path('modules'));
    File::deleteDirectory(base_path('packages'));
});

it('maps a dotted domain into the application lang path', function () {
    expect(app(LanguageFiles::class)->pathFor('identity.user-edit', 'ar'))
        ->toBe(lang_path('ar/identity/user-edit.php'));
});

it('maps a namespaced domain into that namespace own lang path', function () {
    Lang::getLoader()->addNamespace('identity', '/modules/identity/resources/lang');

    expect(app(LanguageFiles::class)->pathFor('identity::user-edit', 'ar'))
        ->toBe('/modules/identity/resources/lang/ar/user-edit.php');
});

it('keeps dots inside a namespaced domain as directories', function () {
    Lang::getLoader()->addNamespace('identity', '/modules/identity/resources/lang');

    expect(app(LanguageFiles::class)->pathFor('identity::user.edit', 'en'))
        ->toBe('/modules/identity/resources/lang/en/user/edit.php');
});

it('fails when the domain namespace is not registered', function () {
    app(LanguageFiles::class)->pathFor('nope::user-edit', 'en');
})->throws(UnknownTranslationNamespaceException::class, 'no translation namespace by that name');

it('splits a domain into namespace and name', function () {
    expect(DomainName::split('identity::user-edit'))->toBe(['identity', 'user-edit'])
        ->and(DomainName::split('identity.user-edit'))->toBe([null, 'identity.user-edit']);
});

it('segments a namespaced key against its domain', function () {
    expect(DomainName::segmentsOf('identity::user-edit', 'identity::user-edit.form.components.name.label'))
        ->toBe(['form', 'components', 'name', 'label'])
        ->and(DomainName::segmentsOf('identity.user-edit', 'other/domain.form.components.name.label'))
        ->toBe([]);
});

it('accepts dotted and namespaced domains', function () {
    expect(DomainName::isValid('identity::user-edit'))->toBeTrue()
        ->and(DomainName::isValid('identity.user-edit'))->toBeTrue();
});

it('rejects domains that could escape the lang path', function (string $domain) {
    expect(DomainName::isValid($domain))->toBeFalse();
})->with([
    '../etc/passwd',
    '/etc/passwd',
    'identity::../secrets',
    'identity::user edit',
    'identity..user',
    '',
]);

it('rejects locales that are not a single path segment', function (string $locale, bool $safe) {
    expect(LanguageFiles::isSafeLocale($locale))->toBe($safe);
})->with([
    ['en', true],
    ['pt_BR', true],
    ['../en', false],
    ['', false],
]);

it('routes a prefix-derived module domain into that module lang path', function () {
    Lang::getLoader()->addNamespace('identity', '/modules/identity/resources/lang');
    config()->set('filament-auto-translator.domain_prefixes', ['Modules\\Identity' => 'identity::']);

    $domain = app(DomainResolver::class)->derive('Modules\\Identity\\Filament\\Resources\\Users\\UserResource');

    expect(app(LanguageFiles::class)->pathFor($domain, 'ar'))
        ->toBe('/modules/identity/resources/lang/ar/user-resource.php');
});

it('registers the namespace of a module that has no language directory yet', function () {
    $module = base_path('modules/billing');
    File::ensureDirectoryExists($module);

    $files = app(LanguageFiles::class);
    $files->ensureNamespace('billing::invoice');

    expect($files->registeredNamespaces())->toBe(['billing' => $module.'/resources/lang'])
        ->and($files->pathFor('billing::invoice', 'en'))->toBe($module.'/resources/lang/en/invoice.php');
});

it('leaves the directory to be made by the first file written into it', function () {
    File::ensureDirectoryExists(base_path('modules/billing'));

    app(LanguageFiles::class)->ensureNamespace('billing::invoice');

    expect(is_dir(base_path('modules/billing/resources/lang')))->toBeFalse();
});

it('still refuses a namespace that matches no module', function () {
    $files = app(LanguageFiles::class);
    $files->ensureNamespace('nope::user-edit');

    expect($files->registeredNamespaces())->toBe([]);

    $files->pathFor('nope::user-edit', 'en');
})->throws(UnknownTranslationNamespaceException::class);

it('leaves a namespace that is already registered alone', function () {
    Lang::getLoader()->addNamespace('billing', '/somewhere/else');
    File::ensureDirectoryExists(base_path('modules/billing'));

    $files = app(LanguageFiles::class);
    $files->ensureNamespace('billing::invoice');

    expect($files->registeredNamespaces())->toBe([])
        ->and($files->pathFor('billing::invoice', 'en'))->toBe('/somewhere/else/en/invoice.php');
});

it('reads the module path from the configuration', function () {
    config()->set('filament-auto-translator.module_path', 'packages');
    File::ensureDirectoryExists(base_path('packages/billing'));

    $files = app(LanguageFiles::class);
    $files->ensureNamespace('billing::invoice');

    expect($files->registeredNamespaces())->toBe(['billing' => base_path('packages/billing').'/resources/lang']);
});

it('refuses a namespace that tries to climb out of the module path', function () {
    $files = app(LanguageFiles::class);
    $files->ensureNamespace('../../etc::passwd');

    expect($files->registeredNamespaces())->toBe([]);
});

it('writes a file that loads back as the same tree, and removes emptied parents', function () {
    $path = sys_get_temp_dir().'/auto-translator-'.uniqid().'/en/users.php';
    $files = app(LanguageFiles::class);

    $tree = ['form' => ['components' => ['name' => ['label' => "It's"], 'gone' => ['label' => 'x']]]];
    $tree = LanguageFiles::forget($tree, ['form', 'components', 'gone', 'label']);
    $files->write($path, $tree);

    expect(require $path)->toBe(['form' => ['components' => ['name' => ['label' => "It's"]]]])
        ->and(LanguageFiles::leaves($tree))->toBe([['form', 'components', 'name', 'label']])
        ->and(LanguageFiles::canSet($tree, ['form', 'components', 'name', 'label']))->toBeFalse()
        ->and(LanguageFiles::canSet($tree, ['form', 'components', 'name', 'label', 'deeper']))->toBeFalse()
        ->and(LanguageFiles::canSet($tree, ['form', 'components', 'email', 'label']))->toBeTrue();

    File::deleteDirectory(dirname($path, 2));
});
