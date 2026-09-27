<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures;

use Filament\Resources\Resource;
use Syriable\Filament\Plugins\AutoTranslator\Concerns\HasResourceTranslations;

/**
 * A real Filament resource, so a panel can register it the way an app does.
 */
class PanelUserResource extends Resource
{
    use HasResourceTranslations;
}
