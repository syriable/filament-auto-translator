<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Tests\Fixtures;

use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Concerns\HasLabel;

/**
 * Stands in for a component shipped by another package — one that extends
 * Filament's base Component and carries a label, which nothing in Filament's
 * own set covers.
 */
class CustomSeparator extends Component
{
    use HasLabel;

    public static function make(): static
    {
        $static = app(static::class);
        $static->configure();

        return $static;
    }
}
