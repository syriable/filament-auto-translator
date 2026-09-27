<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Syriable\FilamentAutoTranslator\Binding\ComponentBinder;
use Syriable\FilamentAutoTranslator\Domains\SchemaDomainRegistry;
use Syriable\FilamentAutoTranslator\Enums\MissingMessagePolicy;

final class AutoTranslatorPlugin implements Plugin
{
    /**
     * @var array<string, string>
     */
    private array $domainPrefixes = [];

    /**
     * @var list<array{path: string, namespace: string}>
     */
    private array $discoverPaths = [];

    private ?MissingMessagePolicy $policy = null;

    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static */
        return filament(app(self::class)->getId());
    }

    public function getId(): string
    {
        return 'filament-auto-translator';
    }

    /**
     * Maps PHP namespaces to domain prefixes, merged over the config file.
     *
     * @param  array<string, string>  $prefixes  e.g. `['Modules\\Billing' => 'billing']`
     */
    public function domainPrefixes(array $prefixes): static
    {
        $this->domainPrefixes = [...$this->domainPrefixes, ...$prefixes];

        return $this;
    }

    /**
     * Registers a directory of schema classes that declare a translation
     * domain but are not resources, such as Livewire form schemas.
     */
    public function discoverIn(string $in, string $for): static
    {
        $this->discoverPaths[] = ['path' => $in, 'namespace' => $for];

        return $this;
    }

    public function onMissing(MissingMessagePolicy $policy): static
    {
        $this->policy = $policy;

        return $this;
    }

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void
    {
        $settings = app(Settings::class);
        $settings->addDomainPrefixes($this->domainPrefixes);

        if ($this->policy !== null) {
            $settings->usePolicy($this->policy);
        }

        foreach ($this->discoverPaths as $entry) {
            app(SchemaDomainRegistry::class)->register($entry['path'], $entry['namespace']);
        }

        app(ComponentBinder::class)->register();
    }
}
