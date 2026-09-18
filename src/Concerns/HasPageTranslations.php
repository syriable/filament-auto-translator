<?php

declare(strict_types=1);

namespace Syriable\Translation\Concerns;

use Illuminate\Contracts\Support\Htmlable;
use Syriable\Translation\Discovery\DomainPrefixResolver;
use Syriable\Translation\Discovery\DomainResolver;
use Syriable\Translation\Enums\MessageSlot;
use Syriable\Translation\Enums\MessageSurface;
use Syriable\Translation\Support\NameNormalizer;

trait HasPageTranslations
{
    use ResolvesTranslationDomain;

    /**
     * A page declaring its own #[TranslationDomain] keeps it. Resource pages
     * without one share the resource catalog. Standalone panel pages
     * (Dashboard, settings, …) fall back to the prefix map.
     */
    public static function translationDomain(): string
    {
        $declared = app(DomainResolver::class)->declaredOn(static::class);

        if ($declared !== null) {
            return $declared;
        }

        if (method_exists(static::class, 'getResource')) {
            $resource = call_user_func([static::class, 'getResource']);

            if (is_string($resource) && method_exists($resource, 'translationDomain')) {
                return $resource::translationDomain();
            }
        }

        return app(DomainPrefixResolver::class)->idFor(static::class);
    }

    public function getTitle(): string|Htmlable
    {
        $message = static::catalogMessage(MessageSurface::Pages, MessageSlot::Title, path: static::messagePagePath());

        if ($message !== null) {
            return $message;
        }

        return parent::getTitle();
    }

    public function getSubheading(): string|Htmlable|null
    {
        $message = static::catalogMessage(MessageSurface::Pages, MessageSlot::Subheading, path: static::messagePagePath());

        if ($message !== null) {
            return $message;
        }

        return parent::getSubheading();
    }

    public static function getNavigationLabel(): string
    {
        $label = static::catalogMessage(MessageSurface::Pages, MessageSlot::Label, path: static::messagePagePath());

        if (is_string($label)) {
            return $label;
        }

        return parent::getNavigationLabel();
    }

    /**
     * @return array<int, string>
     */
    protected static function messagePagePath(): array
    {
        return [NameNormalizer::kebabClassBasename(static::class)];
    }
}
