<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Concerns;

use Illuminate\Contracts\Support\Htmlable;
use Syriable\Filament\Plugins\AutoTranslator\Domains\DomainResolver;
use Syriable\Filament\Plugins\AutoTranslator\Enums\Chrome;
use Syriable\Filament\Plugins\AutoTranslator\Messages\MachineName;

/**
 * For a Filament page: its title, subheading and navigation label come from
 * its domain, and so does the copy of every component it renders.
 *
 * A resource page shares its resource's domain and nests under
 * `pages.{page-kebab}`. A standalone panel page owns `{prefix}.pages.{page-kebab}`
 * and keeps its chrome at the root of that file.
 *
 * @see Chrome::forPage()
 */
trait HasPageTranslations
{
    use ResolvesTranslationDomain;

    public static function translationDomain(): string
    {
        $domains = app(DomainResolver::class);

        if (($declared = $domains->declaredOn(static::class)) !== null) {
            return $declared;
        }

        $resource = static::translatedResource();

        return $resource !== null ? $resource::translationDomain() : $domains->derivePage(static::class);
    }

    public function getTitle(): string|Htmlable
    {
        return static::chromeMessage(Chrome::PageTitle, static::chromePath()) ?? parent::getTitle();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return static::chromeMessage(Chrome::PageSubheading, static::chromePath()) ?? parent::getSubheading();
    }

    public static function getNavigationLabel(): string
    {
        return static::chromeMessage(Chrome::PageNavigationLabel, static::chromePath()) ?? parent::getNavigationLabel();
    }

    /**
     * @return list<string>
     */
    protected static function chromePath(): array
    {
        return static::translatedResource() !== null ? [MachineName::ofClass(static::class)] : [];
    }

    /**
     * The resource whose domain this page shares, if it is a resource page
     * of a resource that has one.
     *
     * @return class-string|null
     */
    protected static function translatedResource(): ?string
    {
        if (! method_exists(static::class, 'getResource')) {
            return null;
        }

        $resource = static::getResource();

        return is_string($resource) && method_exists($resource, 'translationDomain') ? $resource : null;
    }
}
