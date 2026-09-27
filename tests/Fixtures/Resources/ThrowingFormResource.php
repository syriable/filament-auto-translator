<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Tests\Fixtures\Resources;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use RuntimeException;
use Syriable\Filament\Plugins\AutoTranslator\Concerns\HasResourceTranslations;

/**
 * A resource whose form cannot be built outside a request, like one reading
 * the signed-in user.
 */
class ThrowingFormResource extends Resource
{
    use HasResourceTranslations;

    public static function form(Schema $schema): Schema
    {
        throw new RuntimeException('No user outside a request.');
    }
}
