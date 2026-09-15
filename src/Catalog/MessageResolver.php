<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Catalog;

use Illuminate\Translation\Translator;
use Syriable\MessageCatalog\Binding\MessageOverrides;
use Syriable\MessageCatalog\Binding\ResolutionCache;
use Syriable\MessageCatalog\Enums\MissingMessagePolicy;
use Syriable\MessageCatalog\Enums\ResolutionOutcome;
use Syriable\MessageCatalog\Exceptions\MissingMessageException;
use Syriable\MessageCatalog\MessageIdentity;
use Syriable\MessageCatalog\MessageKeyBuilder;
use Syriable\MessageCatalog\Resolution;

class MessageResolver
{
    public function __construct(
        private MessageKeyBuilder $compiler,
        private ResolutionCache $memo,
        private MessageOverrides $registry,
        private Translator $translator,
    ) {}

    public function resolve(MessageIdentity $identity): Resolution
    {
        $locale = $this->translator->getLocale();
        $cacheKey = $identity->cacheKey($locale);
        $cached = $this->memo->resolution($cacheKey);

        if ($cached instanceof Resolution) {
            return $cached;
        }

        $key = $this->compiler->compile($identity);
        $mode = $this->mode();
        $fallbackLocale = $this->translator->getFallback();
        $presentInCurrent = $this->translator->has($key, $locale, false);
        $presentInFallback = $fallbackLocale !== $locale
            && $this->translator->has($key, $fallbackLocale, false);

        if ($presentInCurrent) {
            $text = $this->translator->get($key, [], $locale);

            $resolution = new Resolution(
                identity: $identity,
                key: $key,
                decision: ResolutionOutcome::Bound,
                text: is_string($text) ? $text : null,
                locale: $locale,
                presentInCurrentLocale: true,
                presentInFallbackLocale: $presentInFallback,
                mode: $mode,
                reason: 'Present in the current locale.',
            );

            return $this->memo->rememberResolution($cacheKey, $resolution);
        }

        if ($presentInFallback) {
            $text = $this->translator->get($key, [], $fallbackLocale);

            $resolution = new Resolution(
                identity: $identity,
                key: $key,
                decision: ResolutionOutcome::UsedFallback,
                text: is_string($text) ? $text : null,
                locale: $locale,
                presentInCurrentLocale: false,
                presentInFallbackLocale: true,
                mode: $mode,
                reason: 'Missing in the current locale; fallback locale has the key.',
            );

            return $this->memo->rememberResolution($cacheKey, $this->applyMode($resolution));
        }

        $resolution = new Resolution(
            identity: $identity,
            key: $key,
            decision: ResolutionOutcome::Missing,
            text: null,
            locale: $locale,
            presentInCurrentLocale: false,
            presentInFallbackLocale: false,
            mode: $mode,
            reason: 'Missing in the current locale and the fallback locale.',
        );

        return $this->memo->rememberResolution($cacheKey, $this->applyMode($resolution));
    }

    public function applyMode(Resolution $resolution): Resolution
    {
        if ($resolution->decision === ResolutionOutcome::UsedFallback) {
            return $resolution;
        }

        if ($resolution->decision !== ResolutionOutcome::Missing) {
            return $resolution;
        }

        $required = $resolution->identity->slot->isRequired($resolution->identity->path);

        if ($required && $resolution->mode === MissingMessagePolicy::Strict) {
            throw MissingMessageException::forKey($resolution->key);
        }

        if ($required && $resolution->mode === MissingMessagePolicy::Inspect) {
            $resolution->text = $resolution->key;

            return $resolution;
        }

        return $resolution;
    }

    public function mode(): MissingMessagePolicy
    {
        return $this->registry->mode
            ?? MissingMessagePolicy::fromConfig((string) config('auto-translator.mode', 'inspect'));
    }
}
