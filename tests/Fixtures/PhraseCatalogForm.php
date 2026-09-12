<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures;

use Filament\Actions\Action;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Livewire\Component;
use Syriable\Filament\Plugins\AutoTranslator\CatalogPrefixResolver;
use Syriable\Filament\Plugins\AutoTranslator\Contracts\PhraseCatalog;

class PhraseCatalogForm extends Component implements HasSchemas, PhraseCatalog
{
    use InteractsWithSchemas;

    private ?Action $mountedTestingAction = null;

    public static function phraseCatalogId(): string
    {
        return app(CatalogPrefixResolver::class)->idFor(static::class);
    }

    public function withMountedAction(Action $action): static
    {
        $this->mountedTestingAction = $action;

        return $this;
    }

    public function getMountedAction(?int $actionNestingIndex = null): ?Action
    {
        return $this->mountedTestingAction;
    }
}
