<?php

declare(strict_types=1);

namespace Syriable\Translation\Tests\Fixtures;

use Syriable\Translation\Concerns\HasClusterTranslations;

class CatalogCluster extends CatalogClusterParent
{
    use HasClusterTranslations;
}
