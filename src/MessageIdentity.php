<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog;

use Syriable\MessageCatalog\Enums\MessageSlot;
use Syriable\MessageCatalog\Enums\MessageSurface;

class MessageIdentity
{
    /**
     * @param  array<int, string>  $path
     */
    public function __construct(
        public string $catalogId,
        public MessageSurface $scope,
        public array $path,
        public string $name,
        public MessageSlot $slot,
        public string $relative = '',
    ) {}

    /**
     * @param  array<int, string>  $path
     */
    public function withPath(array $path): self
    {
        return new self(
            catalogId: $this->catalogId,
            scope: $this->scope,
            path: $path,
            name: $this->name,
            slot: $this->slot,
            relative: $this->relative,
        );
    }

    public function withName(string $name): self
    {
        return new self(
            catalogId: $this->catalogId,
            scope: $this->scope,
            path: $this->path,
            name: $name,
            slot: $this->slot,
            relative: $this->relative,
        );
    }

    public function withCatalogId(string $catalogId): self
    {
        return new self(
            catalogId: $catalogId,
            scope: $this->scope,
            path: $this->path,
            name: $this->name,
            slot: $this->slot,
            relative: $this->relative,
        );
    }

    public function cacheKey(string $locale): string
    {
        return implode('|', [
            $this->catalogId,
            $this->scope->value,
            implode('.', $this->path),
            $this->name,
            $this->slot->value,
            $this->relative,
            $locale,
        ]);
    }
}
