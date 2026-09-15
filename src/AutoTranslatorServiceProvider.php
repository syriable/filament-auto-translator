<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Syriable\Filament\Plugins\AutoTranslator\Apply\ApplyPhrasesCommand;
use Syriable\Filament\Plugins\AutoTranslator\Audit\AuditPhrasesCommand;
use Syriable\Filament\Plugins\AutoTranslator\Audit\PhraseAuditor;
use Syriable\Filament\Plugins\AutoTranslator\Inspection\PhraseInspector;
use Syriable\Filament\Plugins\AutoTranslator\Sync\SyncPhrasesCommand;

class AutoTranslatorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('auto-translator')
            ->hasConfigFile('auto-translator')
            ->hasCommands(
                AuditPhrasesCommand::class,
                SyncPhrasesCommand::class,
                ApplyPhrasesCommand::class,
            );
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(PhraseRegistry::class);
        $this->app->scoped(PhraseMemo::class);
        $this->app->singleton(PhraseBindings::class);
        $this->app->singleton(PhraseKeyCompiler::class);
        $this->app->singleton(CatalogPrefixResolver::class);
        $this->app->singleton(PhraseResolver::class);
        $this->app->singleton(PhraseBinder::class);
        $this->app->singleton(PhraseInspector::class);
        $this->app->singleton(PhraseAuditor::class);
    }

    public function packageBooted(): void
    {
        $this->app->make(PhraseBinder::class)->registerHooks();
    }
}
