<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Syriable\MessageCatalog\Binding\ComponentBindings;
use Syriable\MessageCatalog\Binding\MessageBinder;
use Syriable\MessageCatalog\Binding\MessageOverrides;
use Syriable\MessageCatalog\Binding\ResolutionCache;
use Syriable\MessageCatalog\Binding\ResolutionExplainer;
use Syriable\MessageCatalog\Catalog\MessageResolver;
use Syriable\MessageCatalog\Console\DebugMessagesCommand;
use Syriable\MessageCatalog\Console\ExtractMessagesCommand;
use Syriable\MessageCatalog\Console\InlineMessagesCommand;
use Syriable\MessageCatalog\Discovery\DomainPrefixResolver;
use Syriable\MessageCatalog\Discovery\DomainRegistry;
use Syriable\MessageCatalog\Discovery\DomainResolver;
use Syriable\MessageCatalog\Discovery\PanelResources;
use Syriable\MessageCatalog\Extraction\MessageScanner;

class MessageCatalogServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('messages')
            ->hasConfigFile('messages')
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
