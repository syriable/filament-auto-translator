<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use Syriable\Filament\Plugins\AutoTranslator\AutoTranslatorServiceProvider;

class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            AutoTranslatorServiceProvider::class,
        ];
    }
}
