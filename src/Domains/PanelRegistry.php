<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Domains;

use Filament\Clusters\Cluster;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Resources\Resource;
use Throwable;

/**
 * The resources, clusters and standalone pages that carry a translation
 * domain, across every registered panel.
 *
 * A panel plugin is configured in `Panel::boot()`, which only runs while a
 * panel serves a request. A console command reading panels on its own would
 * see no domain prefixes, discovery paths or missing-message policy — and
 * write copy to a different file than the browser reads. Booting every panel
 * first keeps the two in agreement.
 */
final class PanelRegistry
{
    private bool $booted = false;

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        $this->eachPanel(function (Panel $panel): void {
            try {
                $panel->boot();
            } catch (Throwable) {
                // a panel that cannot boot outside a request must not take the
                // whole walk down with it
            }
        });
    }

    /**
     * @return list<class-string<resource>>
     */
    public function resources(): array
    {
        /** @var list<class-string<resource>> */
        return $this->collect(
            fn (Panel $panel): array => $panel->getResources(),
            fn (string $class): bool => is_subclass_of($class, Resource::class),
        );
    }

    /**
     * @return list<class-string<Cluster>>
     */
    public function clusters(): array
    {
        /** @var list<class-string<Cluster>> */
        return $this->collect(
            fn (Panel $panel): array => $panel->getClusters(),
            fn (string $class): bool => is_subclass_of($class, Cluster::class),
        );
    }

    /**
     * Panel pages that own their domain: a resource's pages share the
     * resource's domain and are walked with it.
     *
     * @return list<class-string<Page>>
     */
    public function pages(): array
    {
        /** @var list<class-string<Page>> */
        return $this->collect(
            fn (Panel $panel): array => $panel->getPages(),
            fn (string $class): bool => is_subclass_of($class, Page::class) && ! is_subclass_of($class, ResourcePage::class),
        );
    }

    /**
     * @param  callable(Panel): array<mixed>  $classesOf
     * @param  callable(string): bool  $accepts
     * @return list<string>
     */
    private function collect(callable $classesOf, callable $accepts): array
    {
        $this->boot();
        $found = [];

        $this->eachPanel(function (Panel $panel) use ($classesOf, $accepts, &$found): void {
            try {
                $classes = $classesOf($panel);
            } catch (Throwable) {
                return;
            }

            foreach ($classes as $class) {
                if (is_string($class) && $accepts($class) && method_exists($class, 'translationDomain')) {
                    $found[$class] = $class;
                }
            }
        });

        return array_values($found);
    }

    /**
     * Runs the callback with each panel as the current one, restoring the
     * panel that was current before.
     *
     * @param  callable(Panel): void  $callback
     */
    private function eachPanel(callable $callback): void
    {
        if (! app()->bound('filament')) {
            return;
        }

        try {
            $panels = Filament::getPanels();
            $original = Filament::getCurrentPanel();
        } catch (Throwable) {
            return;
        }

        try {
            foreach ($panels as $panel) {
                Filament::setCurrentPanel($panel);
                $callback($panel);
            }
        } finally {
            Filament::setCurrentPanel($original);
        }
    }
}
