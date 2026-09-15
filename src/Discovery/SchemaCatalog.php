<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Discovery;

use Filament\Schemas\Schema;
use Syriable\Filament\Plugins\AutoTranslator\Contracts\PhraseCatalog;

class SchemaCatalog
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
