<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Tests\Fixtures\Schemas\Plain;

use Syriable\MessageCatalog\Contracts\PhraseCatalog;

class SchemalessCatalog implements PhraseCatalog
{
    public static function phraseCatalogId(): string
    {
        return 'identity.schemaless';
    }
}
