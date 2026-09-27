<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Tests\Fixtures;

use Syriable\FilamentAutoTranslator\Concerns\HasPageTranslations;

/**
 * A custom panel page that owns its domain (no getResource()).
 * Default domain: filament.pages.standalone-dashboard-page.
 */
class StandaloneDashboardPage extends StandalonePageParent
{
    use HasPageTranslations;
}
