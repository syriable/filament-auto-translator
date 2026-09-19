<?php

declare(strict_types=1);

namespace Syriable\Translation\Enums;

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
     * @param  array<int, string>  $path
     */
    public function isRequired(array $path = []): bool
    {
        if ($this === self::Body && ($path[array_key_last($path)] ?? null) === 'notifications') {
            return false;
        }

        return match ($this) {
            self::Label, self::Breadcrumb, self::Title, self::Body => true,
            default => false,
        };
    }
}
