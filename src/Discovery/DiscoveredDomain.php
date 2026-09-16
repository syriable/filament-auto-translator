<?php

declare(strict_types=1);

namespace Syriable\Translation\Discovery;

use Filament\Schemas\Schema;

class DiscoveredDomain
{
    /**
     * @param  class-string  $class
     */
    public function __construct(
        public string $class,
        public string $method,
        public string $catalogId,
        public ?string $contentMethod = null,
    ) {}

    public function build(Schema $schema): mixed
    {
        $builder = [$this->class, $this->method];

        // DomainDiscoverer only constructs this for a builder it confirmed by
        // reflection, so the guard is for the type checker, not for runtime.
        return is_callable($builder) ? $builder($schema) : null;
    }

    /**
     * The chrome this catalog wraps its schema in, when it declares any.
     */
    public function buildContent(): mixed
    {
        if ($this->contentMethod === null) {
            return null;
        }

        $builder = [$this->class, $this->contentMethod];

        // DomainDiscoverer only records a builder it confirmed by reflection,
        // so the guard is for the type checker, not for runtime.
        return is_callable($builder) ? $builder() : null;
    }
}
