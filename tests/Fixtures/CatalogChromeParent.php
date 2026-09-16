<?php

declare(strict_types=1);

namespace Syriable\Translation\Tests\Fixtures;

use UnitEnum;

class CatalogChromeParent
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
