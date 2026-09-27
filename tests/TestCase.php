<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Tests;

use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Panel;
use Filament\PanelRegistry;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Foundation\Application;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Syriable\FilamentAutoTranslator\AutoTranslatorServiceProvider;

class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            AutoTranslatorServiceProvider::class,
        ];
    }

    /**
     * Registers Filament and a panel the way a panel provider does, without
     * serving a request — what a console command sees.
     */
    protected function registerPanel(Panel $panel): void
    {
        foreach ([
            LivewireServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
        ] as $provider) {
            $this->app->register($provider);
        }

        $this->app->make(PanelRegistry::class)->register($panel);
    }
}
