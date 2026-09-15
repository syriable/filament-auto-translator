<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Syriable\Filament\Plugins\AutoTranslator\Discovery\SchemaCatalogRegistry;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseMode;

class PhrasePlugin implements Plugin
{
    /**
     * @var array<string, string>
     */
    private array $prefixes = [];

    /**
     * @var array<string, array{path: string, namespace: string}>
     */
    private array $schemaCatalogPaths = [];

    private ?PhraseMode $mode = null;

    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'syriable-filament-auto-translator';
    }

    /**
     * @param  array<string, string>  $prefixes
     */
    public function catalogPrefixes(array $prefixes): self
    {
        $this->prefixes = $prefixes;

        return $this;
    }

    /**
     * Discover phrase catalogs that own a schema but are not resources, such as
     * Livewire form schemas on the public site.
     */
    public function discoverSchemaCatalogs(string $in, string $for): self
    {
        $this->schemaCatalogPaths[$in.'|'.$for] = ['path' => $in, 'namespace' => $for];

        return $this;
    }

    public function mode(PhraseMode $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void
    {
        $registry = app(PhraseRegistry::class);
        $registry->prefixes = [...$registry->prefixes, ...$this->prefixes];

        if ($this->mode instanceof PhraseMode) {
            $registry->mode = $this->mode;
        }

        $catalogs = app(SchemaCatalogRegistry::class);

        foreach ($this->schemaCatalogPaths as $path) {
            $catalogs->discover(in: $path['path'], for: $path['namespace']);
        }

        app(PhraseBinder::class)->registerHooks();
    }
}
