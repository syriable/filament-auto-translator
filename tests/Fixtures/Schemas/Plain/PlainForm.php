<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\Schemas\Plain;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PlainForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nickname'),
        ]);
    }
}
