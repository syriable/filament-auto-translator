<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Enums;

use Syriable\Filament\Plugins\AutoTranslator\Messages\MessageIdentity;

/**
 * Copy Filament reads from a static-ish method on a resource, page or cluster
 * rather than from a component: model labels, navigation, page titles.
 *
 * This is the single definition of that contract. The traits bind these
 * methods, the key compiler places them at the root of a language file, the
 * scanner audits the required ones and `auto-translator:inline` writes them.
 */
enum Chrome
{
    case ModelLabel;
    case PluralModelLabel;
    case PluralLabel;
    case NavigationLabel;
    case NavigationGroup;
    case ClusterBreadcrumb;
    case PageTitle;
    case PageSubheading;
    case PageNavigationLabel;

    /**
     * @return list<self>
     */
    public static function forResource(): array
    {
        return [self::ModelLabel, self::PluralModelLabel, self::PluralLabel, self::NavigationLabel, self::NavigationGroup];
    }

    /**
     * @return list<self>
     */
    public static function forCluster(): array
    {
        return [self::ClusterBreadcrumb, self::NavigationLabel];
    }

    /**
     * @return list<self>
     */
    public static function forPage(): array
    {
        return [self::PageTitle, self::PageSubheading, self::PageNavigationLabel];
    }

    public static function tryFor(MessageScope $scope, MessageSlot $slot): ?self
    {
        foreach (self::cases() as $chrome) {
            if ($chrome->scope() === $scope && $chrome->slot() === $slot) {
                return $chrome;
            }
        }

        return null;
    }

    public function scope(): MessageScope
    {
        return match ($this) {
            self::ModelLabel, self::PluralModelLabel, self::PluralLabel => MessageScope::Model,
            self::NavigationLabel, self::NavigationGroup => MessageScope::Navigation,
            self::ClusterBreadcrumb => MessageScope::Cluster,
            self::PageTitle, self::PageSubheading, self::PageNavigationLabel => MessageScope::Pages,
        };
    }

    public function slot(): MessageSlot
    {
        return match ($this) {
            self::ModelLabel, self::NavigationLabel, self::PageNavigationLabel => MessageSlot::Label,
            self::PluralModelLabel => MessageSlot::Plural,
            self::PluralLabel => MessageSlot::PluralLabel,
            self::NavigationGroup => MessageSlot::Group,
            self::ClusterBreadcrumb => MessageSlot::Breadcrumb,
            self::PageTitle => MessageSlot::Title,
            self::PageSubheading => MessageSlot::Subheading,
        };
    }

    /**
     * The language-file key, named after what Filament calls the value.
     */
    public function key(): string
    {
        return match ($this) {
            self::ModelLabel => 'model_label',
            self::PluralModelLabel => 'plural_model_label',
            self::PluralLabel => 'plural_label',
            self::NavigationLabel, self::PageNavigationLabel => 'navigation_label',
            self::NavigationGroup => 'navigation_group',
            self::ClusterBreadcrumb => 'cluster_breadcrumb',
            self::PageTitle => 'title',
            self::PageSubheading => 'subheading',
        };
    }

    public function method(): string
    {
        return match ($this) {
            self::ModelLabel => 'getModelLabel',
            self::PluralModelLabel => 'getPluralModelLabel',
            self::PluralLabel => 'getPluralLabel',
            self::NavigationLabel, self::PageNavigationLabel => 'getNavigationLabel',
            self::NavigationGroup => 'getNavigationGroup',
            self::ClusterBreadcrumb => 'getClusterBreadcrumb',
            self::PageTitle => 'getTitle',
            self::PageSubheading => 'getSubheading',
        };
    }

    public function isStatic(): bool
    {
        return $this !== self::PageTitle && $this !== self::PageSubheading;
    }

    /**
     * The return type `auto-translator:inline` declares; covariant with
     * Filament's own signature.
     */
    public function returnType(): string
    {
        return match ($this) {
            self::PluralLabel, self::NavigationGroup, self::ClusterBreadcrumb, self::PageSubheading => '?string',
            default => 'string',
        };
    }

    public function isRequired(): bool
    {
        return $this->slot()->isRequired();
    }

    /**
     * @param  list<string>  $path  `[page]` for a resource page, `[]` otherwise
     */
    public function identity(string $domain, array $path = []): MessageIdentity
    {
        return new MessageIdentity($domain, $this->scope(), $path, '', $this->slot());
    }
}
