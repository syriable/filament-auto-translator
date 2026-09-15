<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Apply;

class PhraseApplyWrite
{
    public function __construct(
        public string $path,
        public string $make,
        public string $method,
        public string $key,
        public string $action,
    ) {}
}
