<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Tests\Fixtures;

use Filament\Actions\Action;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Livewire\Component;

class EditUser extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    private ?Action $mountedTestingAction = null;

    public static function translationDomain(): string
    {
        return TranslatedResource::translationDomain();
    }

    public static function getResource(): string
    {
        return TranslatedResource::class;
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
