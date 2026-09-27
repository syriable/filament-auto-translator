<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures;

class ClusterChromeParent
{
    public static function getClusterBreadcrumb(): ?string
    {
        return 'parent breadcrumb';
    }

    public static function getNavigationLabel(): string
    {
        return 'parent cluster nav';
    }
}
