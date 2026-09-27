<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Syriable\Filament\Plugins\AutoTranslator\Binding\ComponentBinder;
use Syriable\Filament\Plugins\AutoTranslator\Binding\ComponentIdentifier;
use Syriable\Filament\Plugins\AutoTranslator\Binding\EmbeddedSchemaLocator;
use Syriable\Filament\Plugins\AutoTranslator\Binding\MessageOptions;
use Syriable\Filament\Plugins\AutoTranslator\Console\AuditCommand;
use Syriable\Filament\Plugins\AutoTranslator\Console\ExtractCommand;
use Syriable\Filament\Plugins\AutoTranslator\Console\InlineCommand;
use Syriable\Filament\Plugins\AutoTranslator\Domains\DomainResolver;
use Syriable\Filament\Plugins\AutoTranslator\Domains\PanelRegistry;
use Syriable\Filament\Plugins\AutoTranslator\Domains\SchemaDomainRegistry;
use Syriable\Filament\Plugins\AutoTranslator\Messages\MessageResolver;

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
