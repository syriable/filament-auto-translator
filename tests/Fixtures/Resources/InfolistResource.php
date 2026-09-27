<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\Resources;

use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Syriable\Filament\Plugins\AutoTranslator\Concerns\HasResourceTranslations;

/**
 * A resource whose infolist lives in its own infolist() builder.
 */
class InfolistResource extends Resource
{
    use HasResourceTranslations;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([TextEntry::make('status')]);
    }
}
