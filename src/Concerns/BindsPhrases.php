<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Concerns;

use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseScope;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseSlot;
use UnitEnum;

trait BindsPhrases
{
    use ResolvesPhrases;

    public static function getModelLabel(): string
    {
        $label = static::catalogPhrase(PhraseScope::Model, PhraseSlot::Label)
            ?? static::callParentChrome('getModelLabel');

        return is_string($label) ? $label : 'resource';
    }

    public static function getPluralModelLabel(): string
    {
        $label = static::catalogPhrase(PhraseScope::Model, PhraseSlot::Plural)
            ?? static::callParentChrome('getPluralModelLabel');

        return is_string($label) ? $label : static::getModelLabel();
    }

    public static function getPluralLabel(): ?string
    {
        $label = static::catalogPhrase(PhraseScope::Model, PhraseSlot::PluralLabel)
            ?? static::callParentChrome('getPluralLabel');

        return is_string($label) ? $label : null;
    }

    public static function getNavigationLabel(): string
    {
        $label = static::catalogPhrase(PhraseScope::Navigation, PhraseSlot::Label)
            ?? static::callParentChrome('getNavigationLabel');

        return is_string($label) ? $label : static::getPluralModelLabel();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $group = static::catalogPhrase(PhraseScope::Navigation, PhraseSlot::Group)
            ?? static::callParentChrome('getNavigationGroup');

        if ($group instanceof UnitEnum || is_string($group) || $group === null) {
            return $group;
        }

        return null;
    }
}
