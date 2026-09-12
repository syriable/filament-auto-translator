<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator;

use Countable;
use Illuminate\Translation\Translator;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseSlot;

class Phrase
{
    /**
     * @param  array<string, mixed>  $replace
     */
    public static function slot(
        object $component,
        PhraseSlot $slot,
        string $relative = '',
        array $replace = [],
        Countable|float|int|null $number = null,
    ): ?string {
        $binder = app(PhraseBinder::class);
        $resolution = $binder->explain($component, $slot);

        if ($resolution->identity->catalogId === '') {
            return $resolution->text;
        }

        $identity = new PhraseIdentity(
            catalogId: $resolution->identity->catalogId,
            scope: $resolution->identity->scope,
            path: $resolution->identity->path,
            name: $resolution->identity->name,
            slot: $slot,
            relative: $relative,
        );

        $resolved = $binder->resolveIdentity($identity);

        if ($resolved->text === null || $resolved->text === $resolved->key) {
            return $resolved->text;
        }

        $translator = app(Translator::class);

        if ($number !== null) {
            return $translator->choice($resolved->key, $number, $replace, $resolved->locale);
        }

        if ($replace !== []) {
            return $translator->get($resolved->key, $replace, $resolved->locale);
        }

        return $resolved->text;
    }
}
