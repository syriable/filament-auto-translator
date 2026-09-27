<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Tests\Fixtures\Clusters;

use Filament\Clusters\Cluster;
use Syriable\FilamentAutoTranslator\Attributes\TranslationDomain;
use Syriable\FilamentAutoTranslator\Concerns\HasClusterTranslations;

/**
 * A real Filament cluster, so a panel can discover it the way an app does.
 */
#[TranslationDomain('filament.settings-cluster')]
class PanelSettingsCluster extends Cluster
{
    use HasClusterTranslations;
}
