<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Tests\Fixtures\Schemas\User;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Syriable\FilamentAutoTranslator\Attributes\TranslationDomain;

/**
 * A schema domain that describes its chrome in a second, Schema-less builder.
 */
#[TranslationDomain('identity.user-chrome')]
class ChromeForm
{
    public static function make(): Form
    {
        return Form::make([
            Section::make()
                ->key('account')
                ->schema([EmbeddedSchema::make('form')])
                ->footer([
                    Action::make('register'),
                    Text::make('terms'),
                ]),
        ])->id('form-chrome');
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nickname'),
        ]);
    }
}
