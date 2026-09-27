<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures;

use Syriable\Filament\Plugins\AutoTranslator\Concerns\HasPageTranslations;

/**
 * A custom panel page that owns its domain (no getResource()).
 * Default domain: filament.pages.standalone-dashboard-page.
 */
class StandaloneDashboardPage extends StandalonePageParent
{
    use HasPageTranslations;
}
