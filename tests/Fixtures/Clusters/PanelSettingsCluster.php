<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\Clusters;

use Filament\Clusters\Cluster;
use Syriable\Filament\Plugins\AutoTranslator\Attributes\TranslationDomain;
use Syriable\Filament\Plugins\AutoTranslator\Concerns\HasClusterTranslations;

/**
 * A real Filament cluster, so a panel can discover it the way an app does.
 */
#[TranslationDomain('filament.settings-cluster')]
class PanelSettingsCluster extends Cluster
{
    use HasClusterTranslations;
}
