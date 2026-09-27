<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Binding;

use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Schema;
use Throwable;
use WeakMap;

/**
 * Finds the node that embeds a Livewire component's named schema.
 *
 * An embedded schema is the one place a component's parent cannot be reached
 * by walking the tree: its components live in a schema of their own on the
 * Livewire component, so `getParentComponent()` on their container is null.
 * The `EmbeddedSchema` node naming that schema stands in for the parent, so a
 * keyed wrapper lends its path segment to the fields it embeds.
 *
 * Filament clones components as it mounts them, so the node is looked for in
 * the mounted tree, never in the prototype a configure hook saw.
 */
final class EmbeddedSchemaLocator
{
    private const int MAX_DEPTH = 32;

    /**
     * @var WeakMap<object, array<string, EmbeddedSchema>>
     */
    private WeakMap $found;

    public function __construct()
    {
        $this->found = new WeakMap;
    }

    public function find(string $name, object $livewire): ?EmbeddedSchema
    {
        if ($name === '' || ! method_exists($livewire, 'getCachedSchemas')) {
            return null;
        }

        if (isset($this->found[$livewire][$name])) {
            return $this->found[$livewire][$name];
        }

        try {
            $schemas = $livewire->getCachedSchemas();
        } catch (Throwable) {
            return null;
        }

        foreach (is_iterable($schemas) ? $schemas : [] as $schemaName => $schema) {
            if ($schemaName === $name || ! $schema instanceof Schema) {
                continue;
            }

            $node = $this->findIn($schema, $name, 0);

            if ($node !== null) {
                $this->found[$livewire] = [...($this->found[$livewire] ?? []), $name => $node];

                return $node;
            }
        }

        // a miss is not remembered: the embedding schema may not be built yet
        return null;
    }

    private function findIn(Schema $schema, string $name, int $depth): ?EmbeddedSchema
    {
        if ($depth >= self::MAX_DEPTH) {
            return null;
        }

        try {
            $components = $schema->getComponents();
        } catch (Throwable) {
            return null;
        }

        foreach ($components as $component) {
            if ($component instanceof EmbeddedSchema) {
                try {
                    if ($component->getName() === $name) {
                        return $component;
                    }
                } catch (Throwable) {
                    // a node that cannot say what it embeds is passed over
                }

                continue;
            }

            if (! $component instanceof Component) {
                continue;
            }

            try {
                $children = $component->getChildSchemas();
            } catch (Throwable) {
                continue;
            }

            foreach ($children as $child) {
                if (($node = $this->findIn($child, $name, $depth + 1)) !== null) {
                    return $node;
                }
            }
        }

        return null;
    }
}
