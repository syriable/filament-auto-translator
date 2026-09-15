<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Tests\Fixtures\Schemas\Plain;

use Filament\Schemas\Schema;
use Syriable\MessageCatalog\Contracts\PhraseCatalog;

abstract class AbstractForm implements PhraseCatalog
{
    public static function phraseCatalogId(): string
    {
        return 'identity.abstract';
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema;
    }
}
