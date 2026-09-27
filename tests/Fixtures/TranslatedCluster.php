<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures;

use Syriable\Filament\Plugins\AutoTranslator\Concerns\HasClusterTranslations;

class TranslatedCluster extends ClusterChromeParent
{
    use HasClusterTranslations;
}
