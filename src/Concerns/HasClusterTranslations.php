<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Concerns;

use Syriable\FilamentAutoTranslator\Enums\Chrome;

/**
 * For a Filament cluster: its breadcrumb and navigation label come from its
 * domain.
 *
 * @see Chrome::forCluster()
 */
trait HasClusterTranslations
{
    use ResolvesTranslationDomain;

    public static function getClusterBreadcrumb(): ?string
    {
        $label = static::chromeMessage(Chrome::ClusterBreadcrumb) ?? static::parentChrome('getClusterBreadcrumb');

        return is_string($label) ? $label : null;
    }

    public static function getNavigationLabel(): string
    {
        $label = static::chromeMessage(Chrome::NavigationLabel) ?? static::parentChrome('getNavigationLabel');

        return is_string($label) ? $label : parent::getNavigationLabel();
    }
}
