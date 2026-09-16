<?php

declare(strict_types=1);

use Syriable\Translation\Binding\MessageOverrides;
use Syriable\Translation\Discovery\DomainPrefixResolver;

it('builds a catalog id from the longest matching namespace prefix', function () {
    config()->set('translations.domain_prefixes', [
        'App\\Filament' => 'filament',
        'Modules\\Billing' => 'billing',
    ]);

    $resolver = new DomainPrefixResolver(new MessageOverrides);

    expect($resolver->idFor('Modules\\Billing\\Resources\\InvoiceResource'))
        ->toBe('billing.invoice-resource')
        ->and($resolver->idFor('App\\Filament\\Resources\\UserResource'))
        ->toBe('filament.user-resource');
});

it('does not change the catalog id when the class is renamed but translationDomain stays the same', function () {
    config()->set('translations.default_domain_prefix', 'filament');
    config()->set('translations.domain_prefixes', []);

    $resolver = new DomainPrefixResolver(new MessageOverrides);

    expect($resolver->idFor('App\\Filament\\Resources\\UserResource'))
        ->not->toBe($resolver->idFor('App\\Filament\\Resources\\MemberResource'));
});

it('uses the configured default prefix when no namespace matches', function () {
    config()->set('translations.default_domain_prefix', 'filament');
    config()->set('translations.domain_prefixes', []);

    $resolver = new DomainPrefixResolver(new MessageOverrides);

    expect($resolver->idFor('App\\Livewire\\Settings'))
        ->toBe('filament.settings');
});

it('prefers the longest namespace prefix when one namespace contains another', function () {
    config()->set('translations.domain_prefixes', [
        'App\\Filament' => 'filament',
        'App\\Filament\\Admin' => 'admin',
    ]);

    $resolver = new DomainPrefixResolver(new MessageOverrides);

    expect($resolver->idFor('App\\Filament\\Admin\\Resources\\UserResource'))
        ->toBe('admin.user-resource')
        ->and($resolver->idFor('App\\Filament\\Resources\\UserResource'))
        ->toBe('filament.user-resource');
});

it('keeps a module resource copy in that module when the prefix names a namespace', function () {
    config()->set('translations.domain_prefixes', [
        'Modules\\Identity' => 'identity::',
        'Modules\\Billing' => 'billing',
    ]);

    $resolver = new DomainPrefixResolver(new MessageOverrides);

    expect($resolver->idFor('Modules\\Identity\\Filament\\Resources\\Users\\UserResource'))
        ->toBe('identity::user-resource')
        ->and($resolver->idFor('Modules\\Billing\\Resources\\InvoiceResource'))
        ->toBe('billing.invoice-resource');
});

it('accepts a namespaced default prefix', function () {
    config()->set('translations.domain_prefixes', []);
    config()->set('translations.default_domain_prefix', 'app::');

    $resolver = new DomainPrefixResolver(new MessageOverrides);

    expect($resolver->idFor('App\\Filament\\Resources\\UserResource'))->toBe('app::user-resource');
});

it('builds an id that is a valid translation domain', function (string $prefix, string $expected) {
    config()->set('translations.domain_prefixes', ['Modules\\Identity' => $prefix]);

    $id = (new DomainPrefixResolver(new MessageOverrides))
        ->idFor('Modules\\Identity\\Filament\\Resources\\Users\\UserResource');

    $segment = '[A-Za-z0-9][A-Za-z0-9_-]*';

    expect($id)->toBe($expected)
        ->and(preg_match("/^({$segment}::)?{$segment}(\.{$segment})*$/", $id))->toBe(1);
})->with([
    ['identity::', 'identity::user-resource'],
    ['identity', 'identity.user-resource'],
]);
