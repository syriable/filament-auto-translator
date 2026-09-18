<?php

declare(strict_types=1);

namespace Syriable\Translation\Concerns;

use Syriable\Translation\Enums\MessageSlot;
use Syriable\Translation\Enums\MessageSurface;

trait HasClusterTranslations
{
    use ResolvesTranslationDomain;

    public static function getClusterBreadcrumb(): ?string
    {
        $label = static::catalogMessage(MessageSurface::Cluster, MessageSlot::Breadcrumb)
            ?? static::callParentChrome('getClusterBreadcrumb');

        return is_string($label) ? $label : null;
    }

    public static function getNavigationLabel(): string
    {
        $label = static::catalogMessage(MessageSurface::Navigation, MessageSlot::Label)
            ?? static::callParentChrome('getNavigationLabel');

        if (is_string($label)) {
            return $label;
        }

        return parent::getNavigationLabel();
    }
}
