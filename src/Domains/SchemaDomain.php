<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Domains;

use Filament\Schemas\Schema;

/**
 * A class outside any Filament resource that owns a schema and its copy, such
 * as a Livewire form schema on a public page.
 */
final readonly class SchemaDomain
{
    /**
     * @param  class-string  $class
     * @param  string  $schemaMethod  the static `form(Schema)` or `configure(Schema)` builder
     * @param  string|null  $chromeMethod  a static `make()` returning the component that wraps the schema
     */
    public function __construct(
        public string $class,
        public string $domain,
        public string $schemaMethod,
        public ?string $chromeMethod = null,
    ) {}

    public function buildSchema(Schema $schema): mixed
    {
        $builder = [$this->class, $this->schemaMethod];

        // discovery only records a builder it confirmed by reflection; the
        // guard is for the type checker
        return is_callable($builder) ? $builder($schema) : null;
    }

    public function buildChrome(): mixed
    {
        $builder = [$this->class, (string) $this->chromeMethod];

        return $this->chromeMethod !== null && is_callable($builder) ? $builder() : null;
    }
}
