<?php

declare(strict_types=1);

use Syriable\MessageCatalog\Binding\MessageOverrides;
use Syriable\MessageCatalog\Discovery\DomainPrefixResolver;

it('builds a catalog id from the longest matching namespace prefix', function () {
    config()->set('messages.domain_prefixes', [
        'App\\Filament' => 'filament',
        'Modules\\Billing' => 'billing',
    ]);

    $resolver = new DomainPrefixResolver(new MessageOverrides);

    expect($resolver->idFor('Modules\\Billing\\Resources\\InvoiceResource'))
        ->toBe('billing.invoice-resource')
        ->and($resolver->idFor('App\\Filament\\Resources\\UserResource'))
        ->toBe('filament.user-resource');
});

it('does not change the catalog id when the class is renamed but phraseCatalogId stays the same', function () {
    config()->set('messages.default_domain_prefix', 'filament');
    config()->set('messages.domain_prefixes', []);

    $resolver = new DomainPrefixResolver(new MessageOverrides);

    expect($resolver->idFor('App\\Filament\\Resources\\UserResource'))
        ->not->toBe($resolver->idFor('App\\Filament\\Resources\\MemberResource'));
});

it('uses the configured default prefix when no namespace matches', function () {
    config()->set('messages.default_domain_prefix', 'filament');
    config()->set('messages.domain_prefixes', []);

    $resolver = new DomainPrefixResolver(new MessageOverrides);

    expect($resolver->idFor('App\\Livewire\\Settings'))
        ->toBe('filament.settings');
});

it('prefers the longest namespace prefix when one namespace contains another', function () {
    config()->set('messages.domain_prefixes', [
        'App\\Filament' => 'filament',
        'App\\Filament\\Admin' => 'admin',
    ]);

    $resolver = new DomainPrefixResolver(new MessageOverrides);

    expect($resolver->idFor('App\\Filament\\Admin\\Resources\\UserResource'))
        ->toBe('admin.user-resource')
        ->and($resolver->idFor('App\\Filament\\Resources\\UserResource'))
        ->toBe('filament.user-resource');
});
