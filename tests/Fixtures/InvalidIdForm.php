<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures;

use Filament\Schemas\Schema;
use Syriable\Filament\Plugins\AutoTranslator\Contracts\PhraseCatalog;

class InvalidIdForm implements PhraseCatalog
{
    public static function phraseCatalogId(): string
    {
        return 'identity::users::edit';
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema;
    }
}
