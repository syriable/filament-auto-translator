<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Tests\Fixtures;

use Syriable\MessageCatalog\Concerns\HasModelMessages;
use Syriable\MessageCatalog\Contracts\PhraseCatalog;

class CatalogOwner extends CatalogChromeParent implements PhraseCatalog
{
    use HasModelMessages;
}
