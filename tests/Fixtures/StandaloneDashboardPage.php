<?php

declare(strict_types=1);

namespace Syriable\Translation\Tests\Fixtures;

use Syriable\Translation\Attributes\TranslationDomain;
use Syriable\Translation\Concerns\HasPageTranslations;

/**
 * A custom panel page that owns its catalog (no getResource()).
 */
#[TranslationDomain('dashboard')]
class StandaloneDashboardPage extends StandalonePageParent
{
    use HasPageTranslations;
}
