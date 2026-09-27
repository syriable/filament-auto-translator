<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Enums;

/**
 * One piece of copy on a component: its label, its hint, its modal heading…
 *
 * The value is the last segment of the message key.
 */
enum MessageSlot: string
{
    case Label = 'label';
    case Breadcrumb = 'breadcrumb';
    case Heading = 'heading';
    case HelperText = 'helper_text';
    case Hint = 'hint';
    case HintIconTooltip = 'hint_icon_tooltip';
    case Placeholder = 'placeholder';
    case Tooltip = 'tooltip';
    case Description = 'description';
    case Title = 'title';
    case Subheading = 'subheading';
    case Group = 'group';
    case Plural = 'plural';
    case PluralLabel = 'plural_label';
    case Body = 'body';
    case NotificationTitle = 'notification_title';
    case BeforeContent = 'before_content';
    case BelowLabel = 'below_label';
    case AfterContent = 'after_content';
    case ModalHeading = 'modal_heading';
    case ModalDescription = 'modal_description';
    case ModalCancelActionLabel = 'modal_cancel_action_label';
    case ModalSubmitActionLabel = 'modal_submit_action_label';
    case Indicator = 'indicator';
    case Prefix = 'prefix';
    case ValidationAttribute = 'validation_attribute';

    /**
     * Whether a missing message is subject to the missing-message policy.
     * An optional slot that is missing simply stays empty.
     *
     * @param  list<string>  $path
     */
    public function isRequired(array $path = []): bool
    {
        // a notification body is optional copy under a required title
        if ($this === self::Body && ($path[array_key_last($path) ?? 0] ?? null) === 'notifications') {
            return false;
        }

        return match ($this) {
            self::Label, self::Breadcrumb, self::Title, self::Body => true,
            default => false,
        };
    }

    /**
     * The Filament setter that writes this slot, for `auto-translator:inline`.
     */
    public function setter(): ?string
    {
        return match ($this) {
            self::Label => 'label',
            self::Placeholder => 'placeholder',
            self::HelperText => 'helperText',
            self::Hint => 'hint',
            self::HintIconTooltip => 'hintIconTooltip',
            self::Heading => 'heading',
            self::Title => 'title',
            self::Description => 'description',
            self::Tooltip => 'tooltip',
            self::BeforeContent => 'beforeContent',
            self::AfterContent => 'afterContent',
            self::ModalHeading => 'modalHeading',
            self::ModalDescription => 'modalDescription',
            self::ModalCancelActionLabel => 'modalCancelActionLabel',
            self::ModalSubmitActionLabel => 'modalSubmitActionLabel',
            self::Indicator => 'indicator',
            self::Prefix => 'prefix',
            self::Subheading => 'subheading',
            self::Body => 'body',
            default => null,
        };
    }

    /**
     * Slots a field renders from a child schema of the same name.
     *
     * @return list<self>
     */
    public static function fieldContent(): array
    {
        return [self::BeforeContent, self::AfterContent, self::BelowLabel];
    }
}
