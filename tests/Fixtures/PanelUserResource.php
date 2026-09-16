<?php

declare(strict_types=1);

namespace Syriable\Translation\Tests\Fixtures;

use Filament\Resources\Resource;
use Syriable\Translation\Concerns\HasModelTranslations;

/**
 * A real Filament resource, so a panel can register it the way an app does.
 */
class PanelUserResource extends Resource
{
    use HasModelTranslations;
}
