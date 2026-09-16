<?php

declare(strict_types=1);

use Syriable\Translation\Attributes\TranslationDomain;
use Syriable\Translation\Discovery\DomainResolver;

it('reads the domain from the attribute', function () {
    $class = new #[TranslationDomain('identity::user-edit')] class {};

    expect(app(DomainResolver::class)->for($class))->toBe('identity::user-edit');
});

it('inherits the domain from a parent class', function () {
    expect(app(DomainResolver::class)->for(ChildOfDomainedParent::class))->toBe('identity::parent');
});

it('reads the domain from a translationDomain method when there is no attribute', function () {
    $class = new class
    {
        public static function translationDomain(): string
        {
            return 'identity::from-method';
        }
    };

    expect(app(DomainResolver::class)->for($class))->toBe('identity::from-method');
});

it('prefers the attribute over the method', function () {
    $class = new #[TranslationDomain('identity::from-attribute')] class
    {
        public static function translationDomain(): string
        {
            return 'identity::from-method';
        }
    };

    expect(app(DomainResolver::class)->for($class))->toBe('identity::from-attribute');
});

it('returns nothing for a class that declares no domain', function () {
    expect(app(DomainResolver::class)->for(new class {}))->toBeNull();
});

/**
 * The reason the domain is an attribute rather than an interface.
 *
 * An interface declaring a static method makes every subclass that does not
 * implement it unloadable, which is fatal for the anonymous classes Livewire
 * single-file components are built from. An attribute cannot do that.
 */
it('lets a base class carry a domain without breaking anonymous subclasses', function () {
    $child = new class extends DomainedBase {};

    expect($child)->toBeInstanceOf(DomainedBase::class)
        ->and(app(DomainResolver::class)->for($child))->toBe('identity::base');
});

#[TranslationDomain('identity::parent')]
abstract class DomainedParent {}

class ChildOfDomainedParent extends DomainedParent {}

#[TranslationDomain('identity::base')]
abstract class DomainedBase {}

it('reports the declared domain, ignoring a translationDomain method', function () {
    $class = new #[TranslationDomain('identity::declared')] class
    {
        public static function translationDomain(): string
        {
            return 'identity::from-method';
        }
    };

    expect(app(DomainResolver::class)->declaredOn($class::class))->toBe('identity::declared');
});

it('reports no declared domain for a class that only has a translationDomain method', function () {
    $class = new class
    {
        public static function translationDomain(): string
        {
            return 'identity::from-method';
        }
    };

    expect(app(DomainResolver::class)->declaredOn($class::class))->toBeNull();
});
