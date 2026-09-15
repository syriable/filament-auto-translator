<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;
use Syriable\Filament\Plugins\AutoTranslator\Exceptions\UnknownCatalogNamespaceException;
use Syriable\Filament\Plugins\AutoTranslator\Sync\PhraseLangWriter;

it('writes a dotted catalog into the application lang path', function () {
    $writer = new PhraseLangWriter;

    expect($writer->pathFor('identity.user-edit', 'ar'))
        ->toBe(lang_path('ar/identity/user-edit.php'));
});

it('writes a namespaced catalog into that namespace own lang path', function () {
    Lang::getLoader()->addNamespace('identity', '/modules/identity/resources/lang');

    $writer = new PhraseLangWriter;

    expect($writer->pathFor('identity::user-edit', 'ar'))
        ->toBe('/modules/identity/resources/lang/ar/user-edit.php');
});

it('keeps dots inside a namespaced group as directories', function () {
    Lang::getLoader()->addNamespace('identity', '/modules/identity/resources/lang');

    $writer = new PhraseLangWriter;

    expect($writer->pathFor('identity::user.edit', 'en'))
        ->toBe('/modules/identity/resources/lang/en/user/edit.php');
});

it('fails when the catalog namespace is not registered', function () {
    (new PhraseLangWriter)->pathFor('nope::user-edit', 'en');
})->throws(UnknownCatalogNamespaceException::class, 'no translation namespace by that name');

it('splits catalog ids into namespace and group', function () {
    $writer = new PhraseLangWriter;

    expect($writer->split('identity::user-edit'))->toBe(['identity', 'user-edit'])
        ->and($writer->split('identity.user-edit'))->toBe([null, 'identity.user-edit']);
});

it('segments a namespaced compiled key against its catalog', function () {
    $writer = new PhraseLangWriter;

    expect($writer->segments('identity::user-edit', 'identity::user-edit.form.components.name.label'))
        ->toBe(['form', 'components', 'name', 'label']);
});
