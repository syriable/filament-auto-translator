<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Syriable\MessageCatalog\Binding\MessageBinder;
use Syriable\MessageCatalog\Binding\MessageOverrides;
use Syriable\MessageCatalog\Discovery\DomainRegistry;
use Syriable\MessageCatalog\Enums\MissingMessagePolicy;

class MessageCatalogPlugin implements Plugin
{
    /**
     * @var array<string, string>
     */
    private array $prefixes = [];

    /**
     * @var array<string, array{path: string, namespace: string}>
     */
    private array $discoverPaths = [];

    private ?MissingMessagePolicy $policy = null;

    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'syriable-filament-messages';
    }

    /**
     * @param  array<string, string>  $prefixes
     */
    public function domainPrefixes(array $prefixes): self
    {
        $this->prefixes = $prefixes;

        return $this;
    }

    /**
     * Discover schema classes that declare a translation domain, such as
     * Livewire form schemas on the public site.
     */
    public function discoverIn(string $in, string $for): self
    {
        $this->discoverPaths[$in.'|'.$for] = ['path' => $in, 'namespace' => $for];

        return $this;
    }

    public function onMissing(MissingMessagePolicy $policy): self
    {
        $this->policy = $policy;

        return $this;
    }

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void
    {
        $registry = app(MessageOverrides::class);
        $registry->prefixes = [...$registry->prefixes, ...$this->prefixes];

        if ($this->policy instanceof MissingMessagePolicy) {
            $registry->mode = $this->policy;
        }

        $catalogs = app(DomainRegistry::class);

        foreach ($this->discoverPaths as $path) {
            $catalogs->discover(in: $path['path'], for: $path['namespace']);
        }

        app(MessageBinder::class)->registerHooks();
    }
}
