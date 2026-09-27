<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Tests\Fixtures;

use Syriable\FilamentAutoTranslator\Concerns\HasClusterTranslations;

class TranslatedCluster extends ClusterChromeParent
{
    use HasClusterTranslations;
}
