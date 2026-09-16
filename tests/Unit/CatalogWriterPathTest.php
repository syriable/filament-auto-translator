<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Syriable\Translation\Binding\MessageOverrides;
use Syriable\Translation\Catalog\CatalogWriter;
use Syriable\Translation\Discovery\DomainPrefixResolver;
use Syriable\Translation\Exceptions\UnknownDomainNamespaceException;

it('writes a dotted catalog into the application lang path', function () {
    $writer = new CatalogWriter;

    expect($writer->pathFor('identity.user-edit', 'ar'))
        ->toBe(lang_path('ar/identity/user-edit.php'));
});

it('writes a namespaced catalog into that namespace own lang path', function () {
    Lang::getLoader()->addNamespace('identity', '/modules/identity/resources/lang');

    $writer = new CatalogWriter;

    expect($writer->pathFor('identity::user-edit', 'ar'))
        ->toBe('/modules/identity/resources/lang/ar/user-edit.php');
});

it('keeps dots inside a namespaced group as directories', function () {
    Lang::getLoader()->addNamespace('identity', '/modules/identity/resources/lang');

    $writer = new CatalogWriter;

    expect($writer->pathFor('identity::user.edit', 'en'))
        ->toBe('/modules/identity/resources/lang/en/user/edit.php');
});

it('fails when the catalog namespace is not registered', function () {
    (new CatalogWriter)->pathFor('nope::user-edit', 'en');
})->throws(UnknownDomainNamespaceException::class, 'no translation namespace by that name');

it('splits catalog ids into namespace and group', function () {
    $writer = new CatalogWriter;

    expect($writer->split('identity::user-edit'))->toBe(['identity', 'user-edit'])
        ->and($writer->split('identity.user-edit'))->toBe([null, 'identity.user-edit']);
});

it('segments a namespaced compiled key against its catalog', function () {
    $writer = new CatalogWriter;

    expect($writer->segments('identity::user-edit', 'identity::user-edit.form.components.name.label'))
        ->toBe(['form', 'components', 'name', 'label']);
});

it('treats a namespaced catalog id as safe to write', function () {
    $writer = new CatalogWriter;

    expect($writer->isSafeCatalogId('identity::user-edit'))->toBeTrue()
        ->and($writer->isSafeCatalogId('identity.user-edit'))->toBeTrue();
});

it('still rejects catalog ids that could escape the lang path', function (string $catalogId) {
    expect((new CatalogWriter)->isSafeCatalogId($catalogId))->toBeFalse();
})->with([
    '../etc/passwd',
    '/etc/passwd',
    'identity::../secrets',
    'identity::user edit',
    '',
]);

it('routes a prefix-derived module domain into that module lang path', function () {
    Lang::getLoader()->addNamespace('identity', '/modules/identity/resources/lang');
    config()->set('translations.domain_prefixes', ['Modules\\Identity' => 'identity::']);

    $domain = (new DomainPrefixResolver(
        new MessageOverrides
    ))->idFor('Modules\\Identity\\Filament\\Resources\\Users\\UserResource');

    expect((new CatalogWriter)->pathFor($domain, 'ar'))
        ->toBe('/modules/identity/resources/lang/ar/user-resource.php');
});

afterEach(function () {
    File::deleteDirectory(base_path('modules'));
});

it('registers the namespace of a module that has no language directory yet', function () {
    $module = base_path('modules/billing');
    File::ensureDirectoryExists($module);

    $writer = new CatalogWriter;

    expect($writer->ensureNamespace('billing::invoice'))->toBe($module.'/resources/lang')
        ->and($writer->pathFor('billing::invoice', 'en'))->toBe($module.'/resources/lang/en/invoice.php')
        ->and($writer->registeredNamespaces())->toBe(['billing' => $module.'/resources/lang']);
});

it('leaves the directory to be made by the first file written into it', function () {
    File::ensureDirectoryExists(base_path('modules/billing'));

    (new CatalogWriter)->ensureNamespace('billing::invoice');

    expect(is_dir(base_path('modules/billing/resources/lang')))->toBeFalse();
});

it('still refuses a namespace that matches no module', function () {
    $writer = new CatalogWriter;

    expect($writer->ensureNamespace('nope::user-edit'))->toBeNull()
        ->and($writer->registeredNamespaces())->toBe([]);

    $writer->pathFor('nope::user-edit', 'en');
})->throws(UnknownDomainNamespaceException::class);

it('leaves a namespace that is already registered alone', function () {
    Lang::getLoader()->addNamespace('billing', '/somewhere/else');
    File::ensureDirectoryExists(base_path('modules/billing'));

    $writer = new CatalogWriter;

    expect($writer->ensureNamespace('billing::invoice'))->toBeNull()
        ->and($writer->pathFor('billing::invoice', 'en'))->toBe('/somewhere/else/en/invoice.php');
});

it('reads the module path from the configuration', function () {
    config()->set('translations.module_path', 'packages');
    File::ensureDirectoryExists(base_path('packages/billing'));

    $writer = new CatalogWriter;

    expect($writer->ensureNamespace('billing::invoice'))
        ->toBe(base_path('packages/billing').'/resources/lang');

    File::deleteDirectory(base_path('packages'));
});

it('refuses a namespace that tries to climb out of the module path', function () {
    expect((new CatalogWriter)->ensureNamespace('../../etc::passwd'))->toBeNull();
});
