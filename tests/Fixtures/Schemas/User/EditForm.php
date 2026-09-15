<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\Schemas\User;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Syriable\Filament\Plugins\AutoTranslator\Contracts\PhraseCatalog;

class EditForm implements PhraseCatalog
{
    public static function phraseCatalogId(): string
    {
        return 'identity.user-edit';
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('email')->email(),
            Select::make('country_id'),
        ]);
    }
}
