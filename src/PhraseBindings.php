<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator;

use WeakMap;

class PhraseBindings
{
    /**
     * @var WeakMap<object, array{phrase:?string, catalog:?string, owner:?object}>
     */
    private WeakMap $bindings;

    public function __construct()
    {
        $this->bindings = new WeakMap;
    }

    public function setPhrase(object $component, string $name): void
    {
        $current = $this->binding($component);
        $current['phrase'] = $name;
        $this->bindings[$component] = $current;
    }

    public function setCatalog(object $component, string $id): void
    {
        $current = $this->binding($component);
        $current['catalog'] = $id;
        $this->bindings[$component] = $current;
    }

    public function setOwner(object $component, object $owner): void
    {
        $current = $this->binding($component);
        $current['owner'] = $owner;
        $this->bindings[$component] = $current;
    }

    public function phrase(object $component): ?string
    {
        return $this->binding($component)['phrase'];
    }

    public function catalog(object $component): ?string
    {
        return $this->binding($component)['catalog'];
    }

    public function owner(object $component): ?object
    {
        return $this->binding($component)['owner'];
    }

    /**
     * @return array{phrase:?string, catalog:?string, owner:?object}
     */
    private function binding(object $component): array
    {
        return $this->bindings[$component] ?? ['phrase' => null, 'catalog' => null, 'owner' => null];
    }
}
