<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Concerns;

use Syriable\Filament\Plugins\AutoTranslator\CatalogPrefixResolver;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseDecision;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseScope;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseSlot;
use Syriable\Filament\Plugins\AutoTranslator\PhraseBinder;
use Syriable\Filament\Plugins\AutoTranslator\PhraseIdentity;

trait ResolvesPhrases
{
    public static function phraseCatalogId(): string
    {
        return app(CatalogPrefixResolver::class)->idFor(static::class);
    }

    /**
     * @param  array<int, string>  $path
     */
    protected static function catalogPhrase(PhraseScope $scope, PhraseSlot $slot, array $path = []): ?string
    {
        $resolution = app(PhraseBinder::class)->resolveIdentity(new PhraseIdentity(
            catalogId: static::phraseCatalogId(),
            scope: $scope,
            path: $path,
            name: '',
            slot: $slot,
        ));

        if (in_array($resolution->decision, [PhraseDecision::Bound, PhraseDecision::UsedFallback], true)) {
            return $resolution->text;
        }

        if ($resolution->decision === PhraseDecision::Missing && $resolution->text !== null) {
            return $resolution->text;
        }

        return null;
    }

    protected static function callParentChrome(string $method): mixed
    {
        $parent = get_parent_class(static::class);

        if ($parent === false || ! method_exists($parent, $method)) {
            return null;
        }

        return parent::$method();
    }
}
