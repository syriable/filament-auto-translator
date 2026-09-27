<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\Schemas\Plain;

use Filament\Schemas\Schema;
use Syriable\Filament\Plugins\AutoTranslator\Attributes\TranslationDomain;

#[TranslationDomain('identity.abstract')]
abstract class AbstractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema;
    }
}
