<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Apply;

use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseSlot;

class SlotMethodMap
{
    public function method(PhraseSlot $slot): ?string
    {
        return match ($slot) {
            PhraseSlot::Label => 'label',
            PhraseSlot::Placeholder => 'placeholder',
            PhraseSlot::HelperText => 'helperText',
            PhraseSlot::Hint => 'hint',
            PhraseSlot::Heading => 'heading',
            PhraseSlot::Title => 'title',
            PhraseSlot::Description => 'description',
            PhraseSlot::Tooltip => 'tooltip',
            PhraseSlot::BeforeContent => 'beforeContent',
            PhraseSlot::AfterContent => 'afterContent',
            PhraseSlot::ModalHeading => 'modalHeading',
            PhraseSlot::ModalDescription => 'modalDescription',
            PhraseSlot::ModalCancelActionLabel => 'modalCancelActionLabel',
            PhraseSlot::ModalSubmitActionLabel => 'modalSubmitActionLabel',
            PhraseSlot::Indicator => 'indicator',
            PhraseSlot::Prefix => 'prefix',
            PhraseSlot::Subheading => 'subheading',
            PhraseSlot::Body => 'body',
            default => null,
        };
    }
}
