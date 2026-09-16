<?php

declare(strict_types=1);

namespace Syriable\Translation\Attributes;

use Attribute;

/**
 * Names the translation domain a class's messages belong to.
 *
 * A domain is either dotted, like `identity.user-edit`, which maps to
 * lang/{locale}/identity/user-edit.php, or namespaced against a registered
 * translation namespace, like `identity::user-edit`, which maps to that
 * namespace's own {locale}/user-edit.php.
 *
 * This is an attribute rather than an interface on purpose: an interface
 * declaring a static method makes every class that inherits it, and does not
 * implement the method, unloadable — which is fatal for the anonymous classes
 * Livewire single-file components are built from.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class TranslationDomain
{
    public function __construct(
        public string $domain,
    ) {}
}
