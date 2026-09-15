<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog;

use Countable;
use Illuminate\Translation\Translator;
use Syriable\MessageCatalog\Binding\MessageBinder;
use Syriable\MessageCatalog\Enums\MessageSlot;

class Messages
{
    /**
     * @param  array<string, mixed>  $replace
     */
    public static function slot(
        object $component,
        MessageSlot $slot,
        string $relative = '',
        array $replace = [],
        Countable|float|int|null $number = null,
    ): ?string {
        $binder = app(MessageBinder::class);
        $resolution = $binder->explain($component, $slot);

        if ($resolution->identity->catalogId === '') {
            return $resolution->text;
        }

        $identity = new MessageIdentity(
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
