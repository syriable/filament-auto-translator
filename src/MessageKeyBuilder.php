<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog;

use Syriable\MessageCatalog\Enums\MessageSlot;
use Syriable\MessageCatalog\Enums\MessageSurface;
use Syriable\MessageCatalog\Exceptions\InvalidMessageNameException;
use Syriable\MessageCatalog\Support\NameNormalizer;

class MessageKeyBuilder
{
    public function compile(MessageIdentity $identity): string
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

    private function rootChromeSegment(MessageIdentity $identity): ?string
    {
        if ($identity->name !== '' || $identity->path !== [] || $identity->relative !== '') {
            return null;
        }

        return match ($identity->scope) {
            MessageSurface::Model => match ($identity->slot) {
                MessageSlot::Label => 'model_label',
                MessageSlot::Plural => 'plural_model_label',
                MessageSlot::PluralLabel => 'plural_label',
                default => null,
            },
            MessageSurface::Navigation => match ($identity->slot) {
                MessageSlot::Label => 'navigation_label',
                MessageSlot::Group => 'navigation_group',
                default => null,
            },
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    private function scopeSegments(MessageIdentity $identity): array
    {
        if (in_array($identity->scope, [MessageSurface::Form, MessageSurface::Infolist], true)) {
            return [$identity->scope->value, 'components', ...$identity->path];
        }

        return [$identity->scope->value, ...$identity->path];
    }

    private function slotSegment(MessageIdentity $identity): string
    {
        if (
            $identity->scope === MessageSurface::Pages
            && $identity->name === ''
            && $identity->relative === ''
            && count($identity->path) === 1
            && $identity->slot === MessageSlot::Label
        ) {
            return 'navigation_label';
        }

        return $identity->slot->value;
    }

    private function assertValid(string $name): string
    {
        if (! NameNormalizer::isValid($name)) {
            throw InvalidMessageNameException::forName($name);
        }

        return $name;
    }
}
