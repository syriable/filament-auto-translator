<?php

declare(strict_types=1);

namespace Syriable\Translation;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Syriable\Translation\Binding\ComponentBindings;
use Syriable\Translation\Binding\EmbeddedSchemas;
use Syriable\Translation\Binding\MessageBinder;
use Syriable\Translation\Binding\MessageOverrides;
use Syriable\Translation\Binding\ResolutionCache;
use Syriable\Translation\Binding\ResolutionExplainer;
use Syriable\Translation\Catalog\MessageResolver;
use Syriable\Translation\Console\DebugMessagesCommand;
use Syriable\Translation\Console\ExtractMessagesCommand;
use Syriable\Translation\Console\InlineMessagesCommand;
use Syriable\Translation\Discovery\DomainPrefixResolver;
use Syriable\Translation\Discovery\DomainRegistry;
use Syriable\Translation\Discovery\DomainResolver;
use Syriable\Translation\Discovery\PanelResources;
use Syriable\Translation\Extraction\MessageScanner;

class TranslationServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('translations')
            ->hasConfigFile('translations')
            ->hasCommands(
                DebugMessagesCommand::class,
                ExtractMessagesCommand::class,
                InlineMessagesCommand::class,
            );
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(MessageOverrides::class);
        $this->app->scoped(ResolutionCache::class);
        $this->app->singleton(ComponentBindings::class);
        $this->app->singleton(EmbeddedSchemas::class);
        $this->app->singleton(MessageKeyBuilder::class);
        $this->app->singleton(DomainPrefixResolver::class);
        $this->app->singleton(MessageResolver::class);
        $this->app->singleton(MessageBinder::class);
        $this->app->singleton(ResolutionExplainer::class);
        $this->app->singleton(DomainRegistry::class);
        $this->app->singleton(DomainResolver::class);
        $this->app->singleton(PanelResources::class);
        $this->app->singleton(MessageScanner::class);
    }
}
