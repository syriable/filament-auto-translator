<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Tests\Fixtures;

use Filament\Schemas\Schema;
use Syriable\MessageCatalog\Attributes\TranslationDomain;

#[TranslationDomain('identity::users.edit')]
class NamespacedIdForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema;
    }
}
