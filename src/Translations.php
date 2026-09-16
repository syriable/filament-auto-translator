<?php

declare(strict_types=1);

namespace Syriable\Translation;

use Countable;
use Illuminate\Translation\Translator;
use Syriable\Translation\Binding\MessageBinder;
use Syriable\Translation\Binding\ResolutionExplainer;
use Syriable\Translation\Discovery\DomainRegistry;
use Syriable\Translation\Discovery\DomainResolver;
use Syriable\Translation\Enums\MessageSlot;

class Translations
{
    /**
     * Registers a directory of schema classes, and starts binding.
     *
     * This is the entry point wherever messages are used. It works with or
     * without a Filament panel, because a schema may render on a public page
     * where no panel ever boots. Binding starts here rather than through a
     * separate call, so there is one thing to remember instead of two that
     * fail silently when only one is done.
     */
    public static function discoverIn(string $path, string $namespace): void
    {
        app(DomainRegistry::class)->discover(in: $path, for: $namespace);

        app(MessageBinder::class)->registerHooks();
    }

    /**
     * The translation domain a class belongs to, or null when it declares none.
     */
    public static function domainFor(object|string $subject): ?string
    {
        return app(DomainResolver::class)->for($subject);
    }

    /**
     * Explains how a component's message resolved, for debugging.
     */
    public static function explain(object $component, MessageSlot $slot = MessageSlot::Label): Resolution
    {
        return app(ResolutionExplainer::class)->inspect($component, $slot);
    }

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
