<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator;

use Countable;
use Illuminate\Translation\Translator;
use Syriable\Filament\Plugins\AutoTranslator\Binding\ComponentBinder;
use Syriable\Filament\Plugins\AutoTranslator\Binding\ComponentIdentifier;
use Syriable\Filament\Plugins\AutoTranslator\Domains\DomainResolver;
use Syriable\Filament\Plugins\AutoTranslator\Domains\SchemaDomainRegistry;
use Syriable\Filament\Plugins\AutoTranslator\Enums\MessageSlot;
use Syriable\Filament\Plugins\AutoTranslator\Messages\MessageResolver;
use Syriable\Filament\Plugins\AutoTranslator\Messages\Resolution;

/**
 * The package's entry points outside the panel plugin.
 */
final class AutoTranslator
{
    /**
     * Registers a directory of schema domains and starts binding.
     *
     * For schemas that render where no panel boots, such as a Livewire form on
     * a public page. It starts binding itself, so it is the only call a module
     * needs, and it is idempotent, so every module can make it.
     */
    public static function discoverIn(string $path, string $namespace): void
    {
        app(SchemaDomainRegistry::class)->register($path, $namespace);
        app(ComponentBinder::class)->register();
    }

    /**
     * The translation domain a class belongs to, or null when it has none.
     */
    public static function domainFor(object|string $subject): ?string
    {
        return app(DomainResolver::class)->for($subject);
    }

    /**
     * How a component's slot resolved: its key, outcome, text and why.
     */
    public static function explain(object $component, MessageSlot $slot = MessageSlot::Label): Resolution
    {
        return app(ComponentIdentifier::class)->resolve($component, $slot);
    }

    /**
     * Looks up a message at a component's identity by hand, for copy the
     * binder does not reach, such as select option labels.
     *
     * @param  string  $relative  a dotted sub-key replacing the slot, e.g. `options.admin`
     * @param  array<string, mixed>  $replace
     */
    public static function message(
        object $component,
        MessageSlot $slot = MessageSlot::Label,
        string $relative = '',
        array $replace = [],
        Countable|float|int|null $number = null,
    ): ?string {
        $explained = self::explain($component, $slot);

        if (! $explained->outcome->hasKey()) {
            return $explained->text;
        }

        $resolution = app(MessageResolver::class)->resolve($explained->identity->withSlot($slot, $relative));

        if (! $resolution->isPresent() || ($replace === [] && $number === null)) {
            return $resolution->text;
        }

        $translator = app(Translator::class);

        return $number !== null
            ? $translator->choice($resolution->key, $number, $replace, $resolution->locale)
            : (string) $translator->get($resolution->key, $replace, $resolution->locale);
    }
}
