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
    ) {}

    public function build(Schema $schema): mixed
    {
        $builder = [$this->class, $this->method];

        // DomainDiscoverer only constructs this for a builder it confirmed by
        // reflection, so the guard is for the type checker, not for runtime.
        return is_callable($builder) ? $builder($schema) : null;
    }
}
