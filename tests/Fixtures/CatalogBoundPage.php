<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Tests\Fixtures;

use Syriable\MessageCatalog\Concerns\HasPageMessages;

class CatalogBoundPage extends CatalogPageParent
{
    use HasPageMessages;
}
