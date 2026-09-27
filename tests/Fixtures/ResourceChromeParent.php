<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures;

use UnitEnum;

class ResourceChromeParent
{
    public static function getModelLabel(): string
    {
        return 'parent model';
    }

    public static function getPluralModelLabel(): string
    {
        return 'parent models';
    }

    public static function getPluralLabel(): ?string
    {
        return 'parent plurals';
    }

    public static function getNavigationLabel(): string
    {
        return 'parent nav';
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'parent group';
    }
}
