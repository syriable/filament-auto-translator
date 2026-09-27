<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures;

use Filament\Pages\Page;
use Syriable\Filament\Plugins\AutoTranslator\Concerns\HasPageTranslations;

/**
 * A real Filament page: domain filament/pages/{kebab}.php, header actions at root actions.
 */
class PanelDashboardPage extends Page
{
    use HasPageTranslations;

    protected static string $routePath = '/panel-dashboard';
}
