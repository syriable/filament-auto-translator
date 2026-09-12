<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures;

use Illuminate\Contracts\Support\Htmlable;

class CatalogPageParent
{
    public static function getResource(): string
    {
        return CatalogOwner::class;
    }

    public function getTitle(): string|Htmlable
    {
        return 'parent title';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'parent subheading';
    }

    public static function getNavigationLabel(): string
    {
        return 'parent navigation';
    }
}
