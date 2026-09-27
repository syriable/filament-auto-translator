<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Attributes;

use Attribute;

/**
 * Names the translation domain a class's messages belong to.
 *
 * `identity.user-edit` maps to lang/{locale}/identity/user-edit.php;
 * `identity::user-edit` maps to {locale}/user-edit.php in the lang directory
 * registered for the `identity` translation namespace.
 *
 * This is an attribute rather than an interface on purpose: an interface
 * declaring a static method makes every class that inherits it without
 * implementing the method unloadable — fatal for the anonymous classes
 * Livewire single-file components are built from.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class TranslationDomain
{
    public function __construct(
        public string $domain,
    ) {}
}
