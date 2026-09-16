<?php

declare(strict_types=1);

namespace Syriable\Translation\Tests\Fixtures;

use Syriable\Translation\Concerns\HasModelTranslations;

class CatalogOwner extends CatalogChromeParent
{
    use HasModelTranslations;
}
