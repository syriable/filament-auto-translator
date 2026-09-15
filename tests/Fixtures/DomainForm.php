<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Tests\Fixtures;

use Filament\Actions\Action;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Livewire\Component;
use Syriable\MessageCatalog\Discovery\DomainPrefixResolver;

class DomainForm extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    private ?Action $mountedTestingAction = null;

    public static function translationDomain(): string
    {
        return app(DomainPrefixResolver::class)->idFor(static::class);
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
