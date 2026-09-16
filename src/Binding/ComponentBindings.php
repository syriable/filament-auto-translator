<?php

declare(strict_types=1);

namespace Syriable\Translation\Binding;

use WeakMap;

class ComponentBindings
{
    /**
     * @var WeakMap<object, array{messageName:?string, domain:?string, owner:?object, replace:array<string, mixed>}>
     */
    private WeakMap $bindings;

    public function __construct()
    {
        $this->bindings = new WeakMap;
    }

    public function setMessageName(object $component, string $name): void
    {
        $current = $this->binding($component);
        $current['messageName'] = $name;
        $this->bindings[$component] = $current;
    }

    public function setDomain(object $component, string $id): void
    {
        $current = $this->binding($component);
        $current['domain'] = $id;
        $this->bindings[$component] = $current;
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    public function setReplace(object $component, array $replace): void
    {
        $current = $this->binding($component);
        $current['replace'] = [...$current['replace'], ...$replace];
        $this->bindings[$component] = $current;
    }

    public function setOwner(object $component, object $owner): void
    {
        $current = $this->binding($component);
        $current['owner'] = $owner;
        $this->bindings[$component] = $current;
    }

    public function messageName(object $component): ?string
    {
        return $this->binding($component)['messageName'];
    }

    public function domain(object $component): ?string
    {
        return $this->binding($component)['domain'];
    }

    public function owner(object $component): ?object
    {
        return $this->binding($component)['owner'];
    }

    /**
     * @return array<string, mixed>
     */
    public function replace(object $component): array
    {
        return $this->binding($component)['replace'];
    }

    /**
     * @return array{messageName:?string, domain:?string, owner:?object, replace:array<string, mixed>}
     */
    private function binding(object $component): array
    {
        return $this->bindings[$component] ?? [
            'messageName' => null,
            'domain' => null,
            'owner' => null,
            'replace' => [],
        ];
    }
}
