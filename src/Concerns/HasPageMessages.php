<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Concerns;

use Illuminate\Contracts\Support\Htmlable;
use Syriable\MessageCatalog\Discovery\DomainPrefixResolver;
use Syriable\MessageCatalog\Enums\MessageSlot;
use Syriable\MessageCatalog\Enums\MessageSurface;
use Syriable\MessageCatalog\Support\NameNormalizer;

trait HasPageMessages
{
    use ResolvesTranslationDomain;

    public static function translationDomain(): string
    {
        $resource = call_user_func([static::class, 'getResource']);

        if (method_exists($resource, 'translationDomain')) {
            return $resource::translationDomain();
        }

        return app(DomainPrefixResolver::class)->idFor(static::class);
    }

    public function getTitle(): string|Htmlable
    {
        $phrase = static::catalogPhrase(MessageSurface::Pages, MessageSlot::Title, path: static::phrasePagePath());

        if ($phrase !== null) {
            return $phrase;
        }

        return parent::getTitle();
    }

    public function getSubheading(): string|Htmlable|null
    {
        $phrase = static::catalogPhrase(MessageSurface::Pages, MessageSlot::Subheading, path: static::phrasePagePath());

        if ($phrase !== null) {
            return $phrase;
        }

        return parent::getSubheading();
    }

    public static function getNavigationLabel(): string
    {
        $label = static::catalogPhrase(MessageSurface::Pages, MessageSlot::Label, path: static::phrasePagePath());

        if (is_string($label)) {
            return $label;
        }

        return parent::getNavigationLabel();
    }

    /**
     * @return array<int, string>
     */
    protected static function phrasePagePath(): array
    {
        return [NameNormalizer::kebabClassBasename(static::class)];
    }
}
