<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Messages;

use Illuminate\Translation\Translator;
use Syriable\FilamentAutoTranslator\Enums\MissingMessagePolicy;
use Syriable\FilamentAutoTranslator\Enums\ResolutionOutcome;
use Syriable\FilamentAutoTranslator\Exceptions\MissingMessageException;
use Syriable\FilamentAutoTranslator\Settings;

/**
 * Looks a message up in the translator and applies the missing-message policy.
 *
 * A form renders the same slot many times per request (every Livewire update
 * re-evaluates labels), so resolutions are cached per identity and locale for
 * the lifetime of the container scope — one request, or one console command.
 * Per-component replacements are applied after this cache, never inside it.
 */
final class MessageResolver
{
    /**
     * @var array<string, Resolution>
     */
    private array $resolved = [];

    public function __construct(
        private readonly Translator $translator,
        private readonly Settings $settings,
    ) {}

    public function resolve(MessageIdentity $identity): Resolution
    {
        $locale = $this->translator->getLocale();

        return $this->resolved[$identity->cacheKey($locale)] ??= $this->lookUp($identity, $locale);
    }

    /**
     * Forgets cached resolutions, after language files were written.
     */
    public function flush(): void
    {
        $this->resolved = [];
    }

    private function lookUp(MessageIdentity $identity, string $locale): Resolution
    {
        $key = $identity->key();
        $fallback = $this->translator->getFallback();
        $inCurrent = $this->translator->has($key, $locale, false);
        $inFallback = $fallback !== $locale && $this->translator->has($key, $fallback, false);

        if ($inCurrent || $inFallback) {
            $text = $this->translator->get($key, [], $inCurrent ? $locale : $fallback);

            return new Resolution(
                identity: $identity,
                key: $key,
                outcome: $inCurrent ? ResolutionOutcome::Bound : ResolutionOutcome::UsedFallbackLocale,
                text: is_string($text) ? $text : null,
                locale: $locale,
                presentInCurrentLocale: $inCurrent,
                presentInFallbackLocale: $inFallback,
                reason: $inCurrent
                    ? 'Present in the current locale.'
                    : 'Missing in the current locale; the fallback locale has the key.',
            );
        }

        return new Resolution(
            identity: $identity,
            key: $key,
            outcome: ResolutionOutcome::Missing,
            text: $this->missingText($identity, $key),
            locale: $locale,
            presentInCurrentLocale: false,
            presentInFallbackLocale: false,
            reason: 'Missing in the current locale and the fallback locale.',
        );
    }

    private function missingText(MessageIdentity $identity, string $key): ?string
    {
        if (! $identity->slot->isRequired($identity->path)) {
            return null;
        }

        return match ($this->settings->policy()) {
            MissingMessagePolicy::Strict => throw MissingMessageException::forKey($key),
            MissingMessagePolicy::Debug => $key,
            MissingMessagePolicy::KeepVendorLabel => null,
        };
    }
}
