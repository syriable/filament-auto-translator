<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Tests\Fixtures;

use Syriable\MessageCatalog\Concerns\HasModelMessages;

class CatalogOwner extends CatalogChromeParent
{
    use HasModelMessages;
}
