<?php

declare(strict_types=1);

namespace Syriable\Translation\Tests\Fixtures;

use Syriable\Translation\Concerns\HasPageTranslations;

class CatalogBoundPage extends CatalogPageParent
{
    use HasPageTranslations;
}
