<?php

declare(strict_types=1);

namespace Syriable\Translation\Extraction;

use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;

class ExtractionHost extends Component implements HasSchemas, HasTable
{
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table;
    }

    /**
     * Caches a schema the walk built, so the rest of the walk reaches it the
     * way a rendered page would: through the Livewire component, by name.
     */
    public function rememberSchema(string $name, Schema $schema): void
    {
        $this->cachedSchemas[$name] = $schema->key($name);
    }
}
