<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Tests\Fixtures;

use Syriable\MessageCatalog\Concerns\HasPageMessages;
use Syriable\MessageCatalog\Contracts\PhraseCatalog;

class CatalogBoundPage extends CatalogPageParent implements PhraseCatalog
{
    use HasPageMessages;
}
