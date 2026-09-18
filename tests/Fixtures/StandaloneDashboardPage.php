<?php

declare(strict_types=1);

namespace Syriable\Translation\Tests\Fixtures;

use Syriable\Translation\Concerns\HasPageTranslations;

/**
 * A custom panel page that owns its catalog (no getResource()).
 * Default domain: pages.standalone-dashboard-page.
 */
class StandaloneDashboardPage extends StandalonePageParent
{
    use HasPageTranslations;
}
