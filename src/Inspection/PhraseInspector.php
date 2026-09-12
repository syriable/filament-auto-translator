<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Inspection;

use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseSlot;
use Syriable\Filament\Plugins\AutoTranslator\PhraseBinder;
use Syriable\Filament\Plugins\AutoTranslator\PhraseResolution;

class PhraseInspector
{
    public function __construct(
        private PhraseBinder $binder,
    ) {}

    public static function explain(object $component, PhraseSlot $slot = PhraseSlot::Label): PhraseResolution
    {
        return app(self::class)->inspect($component, $slot);
    }

    public function inspect(object $component, PhraseSlot $slot = PhraseSlot::Label): PhraseResolution
    {
        return $this->binder->explain($component, $slot);
    }
}
