<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;
use Syriable\Filament\Plugins\AutoTranslator\Domains\DomainResolver;

/**
 * A page that wraps its form in chrome, the way a Livewire page does: the
 * fields are a schema of their own, and the chrome reaches them by name.
 */
class EmbeddedSchemaHost extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public ?string $wrapperKey = 'account';

    public static function translationDomain(): string
    {
        return app(DomainResolver::class)->derive(static::class);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->key($this->wrapperKey)
                ->schema([EmbeddedSchema::make('form')]),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nickname'),
            Action::make('submit'),
        ]);
    }
}
