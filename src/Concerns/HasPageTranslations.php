<?php

declare(strict_types=1);

namespace Syriable\Translation\Concerns;

use Illuminate\Contracts\Support\Htmlable;
use Syriable\Translation\Discovery\DomainResolver;
use Syriable\Translation\Enums\MessageSlot;
use Syriable\Translation\Enums\MessageSurface;
use Syriable\Translation\Support\NameNormalizer;

trait HasPageTranslations
{
    use ResolvesTranslationDomain;

    /**
     * A page declaring its own #[TranslationDomain] keeps it. Resource pages
     * without one share the resource catalog. Standalone panel pages default to
     * `pages.{class-kebab}` so copy lives in lang/{locale}/pages/{page}.php.
     */
    public static function translationDomain(): string
    {
        $declared = app(DomainResolver::class)->declaredOn(static::class);

        if ($declared !== null) {
            return $declared;
        }

        if (static::sharesResourceCatalog()) {
            $resource = call_user_func([static::class, 'getResource']);

            return $resource::translationDomain();
        }

        return 'pages.'.NameNormalizer::kebabClassBasename(static::class);
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
     * Resource pages nest under `pages.{kebab}` inside the resource catalog.
     * Standalone pages own `pages/{kebab}.php`, so chrome sits at the catalog root.
     *
     * @return array<int, string>
     */
    protected static function messagePagePath(): array
    {
        if (static::sharesResourceCatalog()) {
            return [NameNormalizer::kebabClassBasename(static::class)];
        }

        return [];
    }

    /**
     * Whether this page shares a Filament resource's translation domain.
     */
    protected static function sharesResourceCatalog(): bool
    {
        if (! method_exists(static::class, 'getResource')) {
            return false;
        }

        $resource = call_user_func([static::class, 'getResource']);

        return is_string($resource) && method_exists($resource, 'translationDomain');
    }
}
