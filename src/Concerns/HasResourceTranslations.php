<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Concerns;

use Syriable\Filament\Plugins\AutoTranslator\Enums\Chrome;
use UnitEnum;

/**
 * For a Filament resource: model labels and navigation come from its domain.
 *
 * @see Chrome::forResource()
 */
trait HasResourceTranslations
{
    use ResolvesTranslationDomain;

    public static function getModelLabel(): string
    {
        $label = static::chromeMessage(Chrome::ModelLabel) ?? static::parentChrome('getModelLabel');

        return is_string($label) ? $label : 'resource';
    }

    public static function getPluralModelLabel(): string
    {
        $label = static::chromeMessage(Chrome::PluralModelLabel) ?? static::parentChrome('getPluralModelLabel');

        return is_string($label) ? $label : static::getModelLabel();
    }

    public static function getPluralLabel(): ?string
    {
        $label = static::chromeMessage(Chrome::PluralLabel) ?? static::parentChrome('getPluralLabel');

        return is_string($label) ? $label : null;
    }

    public static function getNavigationLabel(): string
    {
        $label = static::chromeMessage(Chrome::NavigationLabel) ?? static::parentChrome('getNavigationLabel');

        return is_string($label) ? $label : static::getPluralModelLabel();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $group = static::chromeMessage(Chrome::NavigationGroup) ?? static::parentChrome('getNavigationGroup');

        return is_string($group) || $group instanceof UnitEnum ? $group : null;
    }
}
