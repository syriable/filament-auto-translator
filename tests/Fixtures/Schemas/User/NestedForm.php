<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\Schemas\User;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Syriable\Filament\Plugins\AutoTranslator\Contracts\PhraseCatalog;

/**
 * Deep layout nesting, mirroring how a real application composes a schema.
 *
 * The shallow fixtures cannot catch a regression in parent-path derivation,
 * so this one exists to pin the compiled keys for nested layouts.
 */
class NestedForm implements PhraseCatalog
{
    public static function phraseCatalogId(): string
    {
        return 'identity.user-nested';
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('tabs-user')
                ->schema([
                    Tab::make()
                        ->key('tab-personal')
                        ->schema([
                            Section::make()
                                ->key('personal-info')
                                ->schema([
                                    TextInput::make('first_name'),
                                    TextInput::make('last_name'),
                                    Fieldset::make('contact')
                                        ->schema([
                                            TextInput::make('email'),
                                            TextInput::make('phone')->placeholder('x'),
                                        ]),
                                ])
                                ->footer(Action::make('save')),
                        ]),
                    Tab::make()
                        ->key('tab-address')
                        ->schema([
                            Select::make('country_id'),
                            Textarea::make('notes')->helperText('x'),
                        ]),
                ]),
        ]);
    }
}
