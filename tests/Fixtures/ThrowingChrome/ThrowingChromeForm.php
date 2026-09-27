<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Tests\Fixtures\ThrowingChrome;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use RuntimeException;
use Syriable\FilamentAutoTranslator\Attributes\TranslationDomain;

/**
 * A schema domain whose chrome builder throws while its schema builds fine.
 */
#[TranslationDomain('identity.throwing-chrome')]
class ThrowingChromeForm
{
    public static function make(): Section
    {
        throw new RuntimeException('No user outside a request.');
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('nickname')]);
    }
}
