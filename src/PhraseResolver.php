<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator;

use Illuminate\Translation\Translator;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseDecision;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseMode;
use Syriable\Filament\Plugins\AutoTranslator\Exceptions\MissingPhraseException;

class PhraseResolver
{
    public function __construct(
        private PhraseKeyCompiler $compiler,
        private PhraseMemo $memo,
        private PhraseRegistry $registry,
        private Translator $translator,
    ) {}

    public function resolve(PhraseIdentity $identity): PhraseResolution
    {
        $locale = $this->translator->getLocale();
        $cacheKey = $identity->cacheKey($locale);
        $cached = $this->memo->resolution($cacheKey);

        if ($cached instanceof PhraseResolution) {
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

            $resolution = new PhraseResolution(
                identity: $identity,
                key: $key,
                decision: PhraseDecision::Bound,
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

            $resolution = new PhraseResolution(
                identity: $identity,
                key: $key,
                decision: PhraseDecision::UsedFallback,
                text: is_string($text) ? $text : null,
                locale: $locale,
                presentInCurrentLocale: false,
                presentInFallbackLocale: true,
                mode: $mode,
                reason: 'Missing in the current locale; fallback locale has the key.',
            );

            return $this->memo->rememberResolution($cacheKey, $this->applyMode($resolution));
        }

        $resolution = new PhraseResolution(
            identity: $identity,
            key: $key,
            decision: PhraseDecision::Missing,
            text: null,
            locale: $locale,
            presentInCurrentLocale: false,
            presentInFallbackLocale: false,
            mode: $mode,
            reason: 'Missing in the current locale and the fallback locale.',
        );

        return $this->memo->rememberResolution($cacheKey, $this->applyMode($resolution));
    }

    public function applyMode(PhraseResolution $resolution): PhraseResolution
    {
        if ($resolution->decision === PhraseDecision::UsedFallback) {
            return $resolution;
        }

        if ($resolution->decision !== PhraseDecision::Missing) {
            return $resolution;
        }

        $required = $resolution->identity->slot->isRequired($resolution->identity->path);

        if ($required && $resolution->mode === PhraseMode::Strict) {
            throw MissingPhraseException::forKey($resolution->key);
        }

        if ($required && $resolution->mode === PhraseMode::Inspect) {
            $resolution->text = $resolution->key;

            return $resolution;
        }

        return $resolution;
    }

    public function mode(): PhraseMode
    {
        return $this->registry->mode
            ?? PhraseMode::fromConfig((string) config('auto-translator.mode', 'inspect'));
    }
}
