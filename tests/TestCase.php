<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use Syriable\MessageCatalog\MessageCatalogServiceProvider;

class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            MessageCatalogServiceProvider::class,
        ];
    }
}
