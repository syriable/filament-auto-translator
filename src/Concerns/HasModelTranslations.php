<?php

declare(strict_types=1);

namespace Syriable\Translation\Concerns;

use Syriable\Translation\Enums\MessageSlot;
use Syriable\Translation\Enums\MessageSurface;
use UnitEnum;

trait HasModelTranslations
{
    use ResolvesTranslationDomain;

    public static function getModelLabel(): string
    {
        $label = static::catalogMessage(MessageSurface::Model, MessageSlot::Label)
            ?? static::callParentChrome('getModelLabel');

        return is_string($label) ? $label : 'resource';
    }

    public static function getPluralModelLabel(): string
    {
        $label = static::catalogMessage(MessageSurface::Model, MessageSlot::Plural)
            ?? static::callParentChrome('getPluralModelLabel');

        return is_string($label) ? $label : static::getModelLabel();
    }

    public static function getPluralLabel(): ?string
    {
        $label = static::catalogMessage(MessageSurface::Model, MessageSlot::PluralLabel)
            ?? static::callParentChrome('getPluralLabel');

        return is_string($label) ? $label : null;
    }

    public static function getNavigationLabel(): string
    {
        $label = static::catalogMessage(MessageSurface::Navigation, MessageSlot::Label)
            ?? static::callParentChrome('getNavigationLabel');

        return is_string($label) ? $label : static::getPluralModelLabel();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $group = static::catalogMessage(MessageSurface::Navigation, MessageSlot::Group)
            ?? static::callParentChrome('getNavigationGroup');

        if ($group instanceof UnitEnum || is_string($group) || $group === null) {
            return $group;
        }

        return null;
    }
}
