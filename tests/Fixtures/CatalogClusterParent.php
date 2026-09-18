<?php

declare(strict_types=1);

namespace Syriable\Translation\Tests\Fixtures;

class CatalogClusterParent
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
