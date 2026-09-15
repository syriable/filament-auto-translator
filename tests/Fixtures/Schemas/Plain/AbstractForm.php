<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Tests\Fixtures\Schemas\Plain;

use Filament\Schemas\Schema;
use Syriable\MessageCatalog\Attributes\TranslationDomain;

#[TranslationDomain('identity.abstract')]
abstract class AbstractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema;
    }
}
