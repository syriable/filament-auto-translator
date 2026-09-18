<?php

declare(strict_types=1);

namespace Syriable\Translation\Discovery;

use Filament\Clusters\Cluster;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Resources\Resource as FilamentResource;
use Throwable;

/**
 * The resources that carry a translation domain, across every registered panel.
 *
 * A panel plugin is configured in Panel::boot(), which only runs while a panel
 * is serving a request. A console command that walked Filament::getResources()
 * on its own would therefore read no domain prefixes, no discovery paths and no
 * missing-message policy, and write copy to a different file than the browser
 * reads from. Booting the panels first is what keeps the two in agreement.
 */
class PanelResources
{
    private bool $booted = false;

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        $original = $this->currentPanel();

        try {
            foreach ($this->panels() as $panel) {
                try {
                    $this->usePanel($panel);
                    $panel->boot();
                } catch (Throwable) {
                    // A panel that cannot boot outside a request still must not
                    // take the whole walk down with it.
                }
            }
        } finally {
            $this->usePanel($original);
        }
    }

    /**
     * @return array<int, class-string<FilamentResource>>
     */
    public function catalogs(): array
    {
        $this->boot();

        $catalogs = [];
        $original = $this->currentPanel();

        try {
            foreach ($this->panels() as $panel) {
                $this->usePanel($panel);

                foreach ($this->resourcesOf($panel) as $resource) {
                    if (! is_subclass_of($resource, FilamentResource::class)) {
                        continue;
                    }

                    if (! method_exists($resource, 'translationDomain')) {
                        continue;
                    }

                    $catalogs[$resource] = $resource;
                }
            }
        } finally {
            $this->usePanel($original);
        }

        return array_values($catalogs);
    }

    /**
     * @return array<int, class-string<Cluster>>
     */
    public function clusters(): array
    {
        $this->boot();

        $clusters = [];
        $original = $this->currentPanel();

        try {
            foreach ($this->panels() as $panel) {
                $this->usePanel($panel);

                foreach ($this->clustersOf($panel) as $cluster) {
                    if (! is_subclass_of($cluster, Cluster::class)) {
                        continue;
                    }

                    if (! method_exists($cluster, 'translationDomain')) {
                        continue;
                    }

                    $clusters[$cluster] = $cluster;
                }
            }
        } finally {
            $this->usePanel($original);
        }

        return array_values($clusters);
    }

    public function flush(): void
    {
        $this->booted = false;
    }

    /**
     * @return array<string, Panel>
     */
    private function panels(): array
    {
        if (! app()->bound('filament')) {
            return [];
        }

        try {
            return Filament::getPanels();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, string>
     */
    private function resourcesOf(Panel $panel): array
    {
        try {
            return $panel->getResources();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, string>
     */
    private function clustersOf(Panel $panel): array
    {
        try {
            return $panel->getClusters();
        } catch (Throwable) {
            return [];
        }
    }

    private function usePanel(?Panel $panel): void
    {
        if (! app()->bound('filament')) {
            return;
        }

        try {
            Filament::setCurrentPanel($panel);
        } catch (Throwable) {
            // Nothing to switch to.
        }
    }

    private function currentPanel(): ?Panel
    {
        if (! app()->bound('filament')) {
            return null;
        }

        try {
            return Filament::getCurrentPanel();
        } catch (Throwable) {
            return null;
        }
    }
}
