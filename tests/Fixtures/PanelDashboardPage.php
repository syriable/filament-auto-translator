<?php

declare(strict_types=1);

namespace Syriable\Translation\Tests\Fixtures;

use Filament\Pages\Page;
use Syriable\Translation\Concerns\HasPageTranslations;

/**
 * A real Filament page: catalog filament/pages/{kebab}.php, header actions at root actions.
 */
class PanelDashboardPage extends Page
{
    use HasPageTranslations;

    protected static string $routePath = '/panel-dashboard';
}
