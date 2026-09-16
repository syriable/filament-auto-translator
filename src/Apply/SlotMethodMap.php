<?php

declare(strict_types=1);

namespace Syriable\Translation\Apply;

use Syriable\Translation\Enums\MessageSlot;

class SlotMethodMap
{
    public function method(MessageSlot $slot): ?string
    {
        return match ($slot) {
            MessageSlot::Label => 'label',
            MessageSlot::Placeholder => 'placeholder',
            MessageSlot::HelperText => 'helperText',
            MessageSlot::Hint => 'hint',
            MessageSlot::Heading => 'heading',
            MessageSlot::Title => 'title',
            MessageSlot::Description => 'description',
            MessageSlot::Tooltip => 'tooltip',
            MessageSlot::BeforeContent => 'beforeContent',
            MessageSlot::AfterContent => 'afterContent',
            MessageSlot::ModalHeading => 'modalHeading',
            MessageSlot::ModalDescription => 'modalDescription',
            MessageSlot::ModalCancelActionLabel => 'modalCancelActionLabel',
            MessageSlot::ModalSubmitActionLabel => 'modalSubmitActionLabel',
            MessageSlot::Indicator => 'indicator',
            MessageSlot::Prefix => 'prefix',
            MessageSlot::Subheading => 'subheading',
            MessageSlot::Body => 'body',
            default => null,
        };
    }
}
