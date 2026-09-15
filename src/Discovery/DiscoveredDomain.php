<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Discovery;

use Filament\Schemas\Schema;
use Syriable\MessageCatalog\Contracts\PhraseCatalog;

class DiscoveredDomain
{
    /**
     * @param  class-string<PhraseCatalog>  $class
     */
    public function __construct(
        public string $class,
        public string $method,
        public string $catalogId,
    ) {}

    public function build(Schema $schema): mixed
    {
        return call_user_func([$this->class, $this->method], $schema);
    }
}
