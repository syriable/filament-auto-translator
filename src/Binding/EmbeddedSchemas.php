<?php

declare(strict_types=1);

namespace Syriable\Translation\Binding;

use Filament\Schemas\Components\Component as SchemaComponent;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Schema;
use Throwable;
use WeakMap;

/**
 * Finds the node that embeds a Livewire component's named schema.
 *
 * An embedded schema is the one place where a component's owner cannot be
 * reached by walking the tree. The components live in a schema of their own on
 * the Livewire component, so the node embedding them is not their parent and
 * `getParentComponent()` on their container answers null. The node is found by
 * asking the Livewire component for its other schemas and looking for the one
 * that names this schema.
 *
 * Filament clones a component as it mounts it, so the node has to be found in
 * the mounted tree; the instance a configure hook would have seen is a
 * prototype that never gets a container.
 */
class EmbeddedSchemas
{
    /**
     * @var WeakMap<object, array<string, EmbeddedSchema>>
     */
    private WeakMap $found;

    public function __construct()
    {
        $this->found = new WeakMap;
    }

    /**
     * The node embedding the schema named $name on this Livewire component.
     */
    public function embedding(string $name, object $livewire): ?EmbeddedSchema
    {
        if ($name === '' || ! method_exists($livewire, 'getCachedSchemas')) {
            return null;
        }

        $known = $this->found[$livewire] ?? [];

        if (array_key_exists($name, $known)) {
            return $known[$name];
        }

        try {
            $schemas = $livewire->getCachedSchemas();
        } catch (Throwable) {
            return null;
        }

        foreach ($schemas as $schemaName => $schema) {
            if ($schemaName === $name || ! $schema instanceof Schema) {
                continue;
            }

            $node = $this->findIn($schema, $name);

            if ($node instanceof EmbeddedSchema) {
                $known[$name] = $node;
                $this->found[$livewire] = $known;

                return $node;
            }
        }

        // a miss is not remembered: the schema embedding this one may not have
        // been built yet, and the answer changes once it is
        return null;
    }

    private function findIn(Schema $schema, string $name, int $depth = 0): ?EmbeddedSchema
    {
        if ($depth >= 32) {
            return null;
        }

        try {
            $components = $schema->getComponents();
        } catch (Throwable) {
            return null;
        }

        foreach ($components as $component) {
            if (! $component instanceof SchemaComponent) {
                continue;
            }

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

            try {
                $childSchemas = $component->getChildSchemas();
            } catch (Throwable) {
                continue;
            }

            foreach ($childSchemas as $childSchema) {
                $node = $this->findIn($childSchema, $name, $depth + 1);

                if ($node instanceof EmbeddedSchema) {
                    return $node;
                }
            }
        }

        return null;
    }
}
