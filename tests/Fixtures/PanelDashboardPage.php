<?php

declare(strict_types=1);

namespace Syriable\Translation\Tests\Fixtures;

use Filament\Pages\Page;
use Syriable\Translation\Attributes\TranslationDomain;
use Syriable\Translation\Concerns\HasPageTranslations;

/**
 * A real Filament page, so a panel can register it and header actions use
 * the pages.{kebab}.actions path without getResource().
 */
#[TranslationDomain('dashboard')]
class PanelDashboardPage extends Page
{
    use HasPageTranslations;

    protected static string $routePath = '/panel-dashboard';
}
