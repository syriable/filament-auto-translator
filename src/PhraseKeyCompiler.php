<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator;

use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseScope;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseSlot;
use Syriable\Filament\Plugins\AutoTranslator\Exceptions\InvalidPhraseNameException;
use Syriable\Filament\Plugins\AutoTranslator\Support\NameNormalizer;

class PhraseKeyCompiler
{
    public function compile(PhraseIdentity $identity): string
    {
        $group = str_replace('.', '/', $identity->catalogId);
        $root = $this->rootChromeSegment($identity);

        if ($root !== null) {
            return $group.'.'.$this->assertValid($root);
        }

        $segments = $this->scopeSegments($identity);

        if ($identity->name !== '') {
            $segments[] = $identity->name;
        }

        if ($identity->relative !== '') {
            foreach (explode('.', $identity->relative) as $relativeSegment) {
                $segments[] = $this->assertValid(NameNormalizer::machine($relativeSegment));
            }
        } else {
            $segments[] = $this->slotSegment($identity);
        }

        $validated = array_map(
            fn (string $segment): string => $this->assertValid(NameNormalizer::machine($segment)),
            $segments,
        );

        return $group.'.'.implode('.', $validated);
    }

    private function rootChromeSegment(PhraseIdentity $identity): ?string
    {
        if ($identity->name !== '' || $identity->path !== [] || $identity->relative !== '') {
            return null;
        }

        return match ($identity->scope) {
            PhraseScope::Model => match ($identity->slot) {
                PhraseSlot::Label => 'model_label',
                PhraseSlot::Plural => 'plural_model_label',
                PhraseSlot::PluralLabel => 'plural_label',
                default => null,
            },
            PhraseScope::Navigation => match ($identity->slot) {
                PhraseSlot::Label => 'navigation_label',
                PhraseSlot::Group => 'navigation_group',
                default => null,
            },
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    private function scopeSegments(PhraseIdentity $identity): array
    {
        if (in_array($identity->scope, [PhraseScope::Form, PhraseScope::Infolist], true)) {
            return [$identity->scope->value, 'components', ...$identity->path];
        }

        return [$identity->scope->value, ...$identity->path];
    }

    private function slotSegment(PhraseIdentity $identity): string
    {
        if (
            $identity->scope === PhraseScope::Pages
            && $identity->name === ''
            && $identity->relative === ''
            && count($identity->path) === 1
            && $identity->slot === PhraseSlot::Label
        ) {
            return 'navigation_label';
        }

        return $identity->slot->value;
    }

    private function assertValid(string $name): string
    {
        if (! NameNormalizer::isValid($name)) {
            throw InvalidPhraseNameException::forName($name);
        }

        return $name;
    }
}
