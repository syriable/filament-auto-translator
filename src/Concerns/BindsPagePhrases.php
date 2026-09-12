<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Concerns;

use Illuminate\Contracts\Support\Htmlable;
use Syriable\Filament\Plugins\AutoTranslator\CatalogPrefixResolver;
use Syriable\Filament\Plugins\AutoTranslator\Contracts\PhraseCatalog;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseScope;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseSlot;
use Syriable\Filament\Plugins\AutoTranslator\Support\NameNormalizer;

trait BindsPagePhrases
{
    use ResolvesPhrases;

    public static function phraseCatalogId(): string
    {
        $resource = call_user_func([static::class, 'getResource']);

        if (is_a($resource, PhraseCatalog::class, true)) {
            return $resource::phraseCatalogId();
        }

        return app(CatalogPrefixResolver::class)->idFor(static::class);
    }

    public function getTitle(): string|Htmlable
    {
        $phrase = static::catalogPhrase(PhraseScope::Pages, PhraseSlot::Title, path: static::phrasePagePath());

        if ($phrase !== null) {
            return $phrase;
        }

        return parent::getTitle();
    }

    public function getSubheading(): string|Htmlable|null
    {
        $phrase = static::catalogPhrase(PhraseScope::Pages, PhraseSlot::Subheading, path: static::phrasePagePath());

        if ($phrase !== null) {
            return $phrase;
        }

        return parent::getSubheading();
    }

    public static function getNavigationLabel(): string
    {
        $label = static::catalogPhrase(PhraseScope::Pages, PhraseSlot::Label, path: static::phrasePagePath());

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
