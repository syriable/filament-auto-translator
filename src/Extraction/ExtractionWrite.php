<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Extraction;

class ExtractionWrite
{
    public function __construct(
        public string $path,
        public string $key,
        public string $action,
        public string $value,
    ) {}
}
