<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Tests\Fixtures;

use Filament\Schemas\Schema;
use Syriable\MessageCatalog\Contracts\PhraseCatalog;

class NamespacedIdForm implements PhraseCatalog
{
    public static function phraseCatalogId(): string
    {
        return 'identity::users.edit';
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema;
    }
}
