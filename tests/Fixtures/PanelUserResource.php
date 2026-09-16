<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Tests\Fixtures;

use Filament\Resources\Resource;
use Syriable\MessageCatalog\Concerns\HasModelMessages;

/**
 * A real Filament resource, so a panel can register it the way an app does.
 */
class PanelUserResource extends Resource
{
    use HasModelMessages;
}
