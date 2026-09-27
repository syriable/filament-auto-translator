<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Syriable\FilamentAutoTranslator\Binding\ComponentBinder;
use Syriable\FilamentAutoTranslator\Binding\ComponentIdentifier;
use Syriable\FilamentAutoTranslator\Binding\EmbeddedSchemaLocator;
use Syriable\FilamentAutoTranslator\Binding\MessageOptions;
use Syriable\FilamentAutoTranslator\Console\AuditCommand;
use Syriable\FilamentAutoTranslator\Console\ExtractCommand;
use Syriable\FilamentAutoTranslator\Console\InlineCommand;
use Syriable\FilamentAutoTranslator\Domains\DomainResolver;
use Syriable\FilamentAutoTranslator\Domains\PanelRegistry;
use Syriable\FilamentAutoTranslator\Domains\SchemaDomainRegistry;
use Syriable\FilamentAutoTranslator\Messages\MessageResolver;

final class AutoTranslatorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-auto-translator')
            ->hasConfigFile()
            ->hasCommands(
                AuditCommand::class,
                ExtractCommand::class,
                InlineCommand::class,
            );
    }

    public function packageRegistered(): void
    {
        // Filament's configureUsing() hooks are process-wide, so the objects
        // they close over live as long as the process.
        $this->app->singleton(Settings::class);
        $this->app->singleton(ComponentBinder::class);
        $this->app->singleton(ComponentIdentifier::class);
        $this->app->singleton(MessageOptions::class);
        $this->app->singleton(EmbeddedSchemaLocator::class);
        $this->app->singleton(DomainResolver::class);
        $this->app->singleton(SchemaDomainRegistry::class);
        $this->app->singleton(PanelRegistry::class);

        // cached resolutions last one request, or one console command
        $this->app->scoped(MessageResolver::class);
    }
}
