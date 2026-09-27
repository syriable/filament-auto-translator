<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Concerns;

use Syriable\FilamentAutoTranslator\Domains\DomainResolver;
use Syriable\FilamentAutoTranslator\Enums\Chrome;
use Syriable\FilamentAutoTranslator\Messages\MessageResolver;

/**
 * Shared by the resource, page and cluster traits.
 */
trait ResolvesTranslationDomain
{
    /**
     * A declared `#[TranslationDomain]` wins; otherwise the domain is derived
     * from the class name and the domain prefix map.
     */
    public static function translationDomain(): string
    {
        $domains = app(DomainResolver::class);

        return $domains->declaredOn(static::class) ?? $domains->derive(static::class);
    }

    /**
     * The chrome copy, or null so the caller falls back to Filament's own.
     *
     * @param  list<string>  $path
     */
    protected static function chromeMessage(Chrome $chrome, array $path = []): ?string
    {
        return app(MessageResolver::class)
            ->resolve($chrome->identity(static::translationDomain(), $path))
            ->text;
    }

    protected static function parentChrome(string $method): mixed
    {
        $parent = get_parent_class(static::class);

        return $parent !== false && method_exists($parent, $method) ? parent::$method() : null;
    }
}
