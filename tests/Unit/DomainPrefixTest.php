<?php

declare(strict_types=1);

use Syriable\FilamentAutoTranslator\Domains\DomainResolver;
use Syriable\FilamentAutoTranslator\Settings;

it('builds a domain from the longest matching namespace prefix', function () {
    config()->set('filament-auto-translator.domain_prefixes', [
        'App\\Filament' => 'filament',
        'Modules\\Billing' => 'billing',
    ]);

    $resolver = app(DomainResolver::class);

    expect($resolver->derive('Modules\\Billing\\Resources\\InvoiceResource'))
        ->toBe('billing.invoice-resource')
        ->and($resolver->derive('App\\Filament\\Resources\\UserResource'))
        ->toBe('filament.user-resource');
});

it('does not change the domain when the class is renamed but translationDomain stays the same', function () {
    config()->set('filament-auto-translator.default_domain_prefix', 'filament');
    config()->set('filament-auto-translator.domain_prefixes', []);

    $resolver = app(DomainResolver::class);

    expect($resolver->derive('App\\Filament\\Resources\\UserResource'))
        ->not->toBe($resolver->derive('App\\Filament\\Resources\\MemberResource'));
});

it('uses the configured default prefix when no namespace matches', function () {
    config()->set('filament-auto-translator.default_domain_prefix', 'filament');
    config()->set('filament-auto-translator.domain_prefixes', []);

    $resolver = app(DomainResolver::class);

    expect($resolver->derive('App\\Livewire\\Settings'))
        ->toBe('filament.settings');
});

it('prefers the longest namespace prefix when one namespace contains another', function () {
    config()->set('filament-auto-translator.domain_prefixes', [
        'App\\Filament' => 'filament',
        'App\\Filament\\Admin' => 'admin',
    ]);

    $resolver = app(DomainResolver::class);

    expect($resolver->derive('App\\Filament\\Admin\\Resources\\UserResource'))
        ->toBe('admin.user-resource')
        ->and($resolver->derive('App\\Filament\\Resources\\UserResource'))
        ->toBe('filament.user-resource');
});

it('keeps a module resource copy in that module when the prefix names a namespace', function () {
    config()->set('filament-auto-translator.domain_prefixes', [
        'Modules\\Identity' => 'identity::',
        'Modules\\Billing' => 'billing',
    ]);

    $resolver = app(DomainResolver::class);

    expect($resolver->derive('Modules\\Identity\\Filament\\Resources\\Users\\UserResource'))
        ->toBe('identity::user-resource')
        ->and($resolver->derive('Modules\\Billing\\Resources\\InvoiceResource'))
        ->toBe('billing.invoice-resource');
});

it('accepts a namespaced default prefix', function () {
    config()->set('filament-auto-translator.domain_prefixes', []);
    config()->set('filament-auto-translator.default_domain_prefix', 'app::');

    $resolver = app(DomainResolver::class);

    expect($resolver->derive('App\\Filament\\Resources\\UserResource'))->toBe('app::user-resource');
});

it('builds an id that is a valid translation domain', function (string $prefix, string $expected) {
    config()->set('filament-auto-translator.domain_prefixes', ['Modules\\Identity' => $prefix]);

    $domain = app(DomainResolver::class)
        ->derive('Modules\\Identity\\Filament\\Resources\\Users\\UserResource');

    $segment = '[A-Za-z0-9][A-Za-z0-9_-]*';

    expect($domain)->toBe($expected)
        ->and(preg_match("/^({$segment}::)?{$segment}(\.{$segment})*$/", $domain))->toBe(1);
})->with([
    ['identity::', 'identity::user-resource'],
    ['identity', 'identity.user-resource'],
]);

it('derives a standalone page domain under pages', function () {
    config()->set('filament-auto-translator.domain_prefixes', []);
    config()->set('filament-auto-translator.default_domain_prefix', 'filament');

    expect(app(DomainResolver::class)->derivePage('App\\Filament\\Pages\\Dashboard'))->toBe('filament.pages.dashboard');
});

it('merges prefixes a panel registers over the configured ones', function () {
    config()->set('filament-auto-translator.domain_prefixes', ['Modules\\Billing' => 'billing']);
    app(Settings::class)->addDomainPrefixes(['Modules\\Billing' => 'invoicing']);

    expect(app(DomainResolver::class)->derive('Modules\\Billing\\InvoiceResource'))->toBe('invoicing.invoice-resource');
});
