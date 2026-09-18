<?php

declare(strict_types=1);

namespace Syriable\Translation\Tests\Fixtures;

use Illuminate\Contracts\Support\Htmlable;

/**
 * Parent chrome for a standalone page that does not belong to a resource.
 */
class StandalonePageParent
{
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
