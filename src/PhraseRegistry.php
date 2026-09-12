<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator;

use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseMode;

class PhraseRegistry
{
    /**
     * @var array<string, string>
     */
    public array $prefixes = [];

    public ?PhraseMode $mode = null;

    public bool $hooksRegistered = false;
}
