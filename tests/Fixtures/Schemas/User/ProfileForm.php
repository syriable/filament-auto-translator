<?php

declare(strict_types=1);

namespace Syriable\Translation\Tests\Fixtures\Schemas\User;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Syriable\Translation\Attributes\TranslationDomain;

#[TranslationDomain('identity.user-profile')]
class ProfileForm
{
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('timezone'),
        ]);
    }
}
