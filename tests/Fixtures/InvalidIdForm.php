<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures;

use Filament\Schemas\Schema;
use Syriable\Filament\Plugins\AutoTranslator\Attributes\TranslationDomain;

#[TranslationDomain('identity::users::edit')]
class InvalidIdForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema;
    }
}
