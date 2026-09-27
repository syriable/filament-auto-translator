<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Tests\Fixtures\Schemas\Plain;

use Filament\Schemas\Schema;
use Syriable\FilamentAutoTranslator\Attributes\TranslationDomain;

#[TranslationDomain('identity.abstract')]
abstract class AbstractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema;
    }
}
