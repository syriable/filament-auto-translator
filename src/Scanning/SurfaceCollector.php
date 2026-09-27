<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Scanning;

use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component as SchemaComponent;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Filesystem\Filesystem;
use ReflectionClass;
use ReflectionMethod;
use Syriable\FilamentAutoTranslator\Binding\MessageOptions;
use Syriable\FilamentAutoTranslator\Domains\DomainResolver;
use Syriable\FilamentAutoTranslator\Domains\PanelRegistry;
use Syriable\FilamentAutoTranslator\Domains\SchemaDomain;
use Syriable\FilamentAutoTranslator\Domains\SchemaDomainRegistry;
use Syriable\FilamentAutoTranslator\Enums\Chrome;
use Syriable\FilamentAutoTranslator\Messages\MachineName;
use Throwable;

/**
 * Builds every registered translatable surface the way a browser would,
 * without a request: resources (chrome, pages, form, infolist, table),
 * clusters, standalone panel pages, page tables and schema domains.
 *
 * The audit, extract and inline commands all read this one walk, so they
 * always agree on what exists.
 *
 * A builder that throws — one reading the signed-in user, say — marks its
 * scope failed rather than empty, so extraction never mistakes it for
 * deleted copy. Deleting live copy is the one failure a rerun cannot undo.
 */
final class SurfaceCollector
{
    /**
     * The schema name a Livewire component caches a schema domain's schema
     * under when its chrome embeds no other name.
     */
    private const string DEFAULT_SCHEMA_NAME = 'form';

    private const string CHROME_SCHEMA_NAME = 'content';

    public function __construct(
        private readonly PanelRegistry $panels,
        private readonly SchemaDomainRegistry $schemaDomains,
        private readonly DomainResolver $domains,
        private readonly MessageOptions $options,
        private readonly Filesystem $files,
    ) {}

    /**
     * @return list<Surface>
     */
    public function collect(): array
    {
        $surfaces = [];

        foreach ($this->panels->resources() as $resource) {
            if (($domain = $this->domains->for($resource)) !== null) {
                $surfaces = [...$surfaces, ...$this->resource($resource, $domain)];
            }
        }

        foreach ($this->panels->clusters() as $cluster) {
            if (($domain = $this->domains->for($cluster)) !== null) {
                $surfaces[] = $this->chromeSurface($cluster, $domain, Chrome::forCluster());
            }
        }

        foreach ($this->panels->pages() as $page) {
            if (($domain = $this->domains->for($page)) !== null) {
                $surfaces[] = $this->chromeSurface($page, $domain, Chrome::forPage());

                if (($table = $this->pageTable($page)) !== null) {
                    $surfaces[] = $table;
                }
            }
        }

        foreach ($this->schemaDomains->all() as $schemaDomain) {
            $surfaces[] = $this->schemaDomain($schemaDomain);
        }

        return $surfaces;
    }

    /**
     * Every component in a component list, depth first: each component, the
     * actions on a field, and the components of every child schema — a
     * section's header and footer as well as its body.
     *
     * @param  array<array-key, mixed>  $components
     * @return list<object>
     */
    public function flatten(array $components): array
    {
        $flat = [];

        foreach ($components as $component) {
            if ($component instanceof ActionGroup) {
                $flat = [...$flat, ...$this->flatten($component->getFlatActions())];

                continue;
            }

            // the node embedding a schema is not copy; the schema is walked
            // on its own, under the node's path
            if (! is_object($component) || $component instanceof EmbeddedSchema) {
                continue;
            }

            $flat[] = $component;

            if ($component instanceof Field || $component instanceof Entry) {
                foreach ($component->getHintActions() as $action) {
                    if ($action->getSchemaComponent() === null) {
                        $action->schemaComponent($component);
                    }

                    $flat[] = $action;
                }
            }

            if ($component instanceof SchemaComponent) {
                try {
                    $children = $component->getChildSchemas();
                } catch (Throwable) {
                    continue;
                }

                foreach ($children as $child) {
                    $flat = [...$flat, ...$this->flatten($child->getComponents())];
                }
            }
        }

        return $flat;
    }

    /**
     * @param  class-string<resource>  $resource
     * @return list<Surface>
     */
    private function resource(string $resource, string $domain): array
    {
        $pages = $this->pagesOf($resource);

        $chrome = array_map(fn (Chrome $chrome): ChromeMessage => new ChromeMessage($chrome, $resource, $domain), Chrome::forResource());

        foreach ($pages ?? [] as $page) {
            foreach (Chrome::forPage() as $pageChrome) {
                $chrome[] = new ChromeMessage($pageChrome, $page, $domain, [MachineName::ofClass($page)]);
            }
        }

        $surfaces = [
            new Surface(
                domain: $domain,
                chrome: $chrome,
                builtScopes: $pages === null ? [] : ['pages'],
                failedScopes: $pages === null ? ['pages'] : [],
                pages: array_map(MachineName::ofClass(...), $pages ?? []),
            ),
            $this->schemaSurface($domain, 'form', fn (ScanHost $host): mixed => $resource::form(Schema::make($host)), $this->filesOf($resource, 'Schemas')),
            $this->schemaSurface($domain, 'infolist', fn (ScanHost $host): mixed => $resource::infolist(Schema::make($host)), $this->filesOf($resource, 'Schemas', 'Infolists')),
            $this->tableSurface($domain, fn (ScanHost $host): mixed => $resource::table(Table::make($host)), $this->filesOf($resource, 'Tables')),
        ];

        foreach ($pages ?? [] as $page) {
            if (($surface = $this->pageTable($page)) !== null) {
                $surfaces[] = $surface;
            }
        }

        return $surfaces;
    }

    /**
     * @param  class-string  $owner
     * @param  list<Chrome>  $chrome
     */
    private function chromeSurface(string $owner, string $domain, array $chrome): Surface
    {
        return new Surface(
            domain: $domain,
            chrome: array_map(fn (Chrome $message): ChromeMessage => new ChromeMessage($message, $owner, $domain), $chrome),
        );
    }

    /**
     * A table a page builds in its own `table()` method — a resource index
     * listing something other than the resource's records, or a custom page
     * implementing `HasTable`. It binds through the page, so its copy lives
     * in the page's domain.
     *
     * A `table()` Filament itself declares is skipped: on a list page that is
     * the resource's own table, walked with the resource.
     *
     * @param  class-string  $page
     */
    private function pageTable(string $page): ?Surface
    {
        if (! is_subclass_of($page, HasTable::class) || ! method_exists($page, 'table')) {
            return null;
        }

        if (str_starts_with((new ReflectionMethod($page, 'table'))->getDeclaringClass()->getName(), 'Filament\\')) {
            return null;
        }

        $domain = $this->domains->for($page);

        if ($domain === null) {
            return null;
        }

        return $this->tableSurface($domain, function () use ($page): mixed {
            $livewire = app($page);

            return $livewire instanceof HasTable && method_exists($livewire, 'table')
                ? $livewire->table(Table::make($livewire))
                : null;
        }, $this->filesOf($page));
    }

    /**
     * A schema domain's schema, inside the chrome its `make()` wraps it in.
     *
     * The chrome is mounted first, because it lends the schema its path: the
     * node embedding the schema has to exist before a field inside can ask
     * which component it hangs from.
     */
    private function schemaDomain(SchemaDomain $schemaDomain): Surface
    {
        $host = $this->host($schemaDomain->domain);
        $files = $this->filesOf($schemaDomain->class);
        $chrome = $this->mountChrome($schemaDomain, $host);

        try {
            $schema = $schemaDomain->buildSchema(Schema::make($host)->key($chrome['embeds'] ?? self::DEFAULT_SCHEMA_NAME));
        } catch (Throwable) {
            $schema = null;
        }

        if (! $schema instanceof Schema) {
            return new Surface($schemaDomain->domain, files: $files, failedScopes: ['form']);
        }

        return new Surface(
            domain: $schemaDomain->domain,
            components: [...$this->flatten($schema->getComponents()), ...$chrome['components']],
            files: $files,
            builtScopes: $chrome['built'] ? ['form'] : [],
            failedScopes: $chrome['built'] ? [] : ['form'],
        );
    }

    /**
     * @return array{built: bool, embeds: ?string, components: list<object>}
     */
    private function mountChrome(SchemaDomain $schemaDomain, ScanHost $host): array
    {
        if ($schemaDomain->chromeMethod === null) {
            return ['built' => true, 'embeds' => null, 'components' => []];
        }

        try {
            $content = $schemaDomain->buildChrome();

            if (! $content instanceof SchemaComponent) {
                return ['built' => true, 'embeds' => null, 'components' => []];
            }

            // getComponents() is what mounts a loose component into its
            // container; before it, every parent lookup throws
            $schema = Schema::make($host)->components([$content]);
            $mounted = $schema->getComponents();
            $host->rememberSchema(self::CHROME_SCHEMA_NAME, $schema);

            $embeds = null;

            foreach ($mounted as $component) {
                $embeds ??= $this->embeddedName($component);
            }

            // the wrapper's own children, not the wrapper: they are the copy
            $children = [];

            foreach ($mounted as $component) {
                foreach ($component instanceof SchemaComponent ? $component->getChildSchemas() : [] as $child) {
                    $children = [...$children, ...$this->flatten($child->getComponents())];
                }
            }
        } catch (Throwable) {
            return ['built' => false, 'embeds' => null, 'components' => []];
        }

        return ['built' => true, 'embeds' => $embeds, 'components' => $children];
    }

    /**
     * The name of the first schema a chrome component embeds.
     */
    private function embeddedName(mixed $component, int $depth = 0): ?string
    {
        if ($component instanceof EmbeddedSchema) {
            return $component->getName();
        }

        if (! $component instanceof SchemaComponent || $depth >= 32) {
            return null;
        }

        foreach ($component->getChildSchemas() as $child) {
            foreach ($child->getComponents() as $grandchild) {
                if (($name = $this->embeddedName($grandchild, $depth + 1)) !== null) {
                    return $name;
                }
            }
        }

        return null;
    }

    /**
     * @param  callable(ScanHost): mixed  $build
     * @param  list<string>  $files
     */
    private function schemaSurface(string $domain, string $scope, callable $build, array $files): Surface
    {
        try {
            $schema = $build($this->host($domain));
        } catch (Throwable) {
            $schema = null;
        }

        if (! $schema instanceof Schema) {
            return new Surface($domain, files: $files, failedScopes: [$scope]);
        }

        return new Surface($domain, components: $this->flatten($schema->getComponents()), files: $files, builtScopes: [$scope]);
    }

    /**
     * @param  callable(ScanHost): mixed  $build
     * @param  list<string>  $files
     */
    private function tableSurface(string $domain, callable $build, array $files): Surface
    {
        try {
            $table = $build($this->host($domain));
        } catch (Throwable) {
            $table = null;
        }

        if (! $table instanceof Table) {
            return new Surface($domain, files: $files, failedScopes: ['table']);
        }

        $components = [
            ...array_values($table->getColumns()),
            ...array_values($table->getFilters(withHidden: true)),
            ...$this->flatten([
                ...$table->getRecordActions(),
                ...$table->getToolbarActions(),
                ...$table->getHeaderActions(),
                ...$table->getEmptyStateActions(),
            ]),
        ];

        return new Surface($domain, components: $components, files: $files, builtScopes: ['table']);
    }

    /**
     * A Livewire owner that resolves every message to the given domain.
     */
    private function host(string $domain): ScanHost
    {
        $host = new ScanHost;
        $this->options->setDomain($host, $domain);

        return $host;
    }

    /**
     * @param  class-string<resource>  $resource
     * @return list<class-string>|null null when the page list cannot be read
     */
    private function pagesOf(string $resource): ?array
    {
        try {
            $registrations = $resource::getPages();
        } catch (Throwable) {
            return null;
        }

        $pages = [];

        foreach ($registrations as $registration) {
            $page = $registration->getPage();

            if (class_exists($page)) {
                $pages[] = $page;
            }
        }

        return $pages;
    }

    /**
     * The class's own file, plus the PHP files in the given folders beside it
     * — where Filament's generators put a resource's form and table classes.
     *
     * @param  class-string  $class
     * @return list<string>
     */
    private function filesOf(string $class, string ...$folders): array
    {
        $file = (new ReflectionClass($class))->getFileName();

        if ($file === false) {
            return [];
        }

        $files = [$file];

        foreach ($folders as $folder) {
            $directory = dirname($file).DIRECTORY_SEPARATOR.$folder;

            if (! $this->files->isDirectory($directory)) {
                continue;
            }

            foreach ($this->files->allFiles($directory) as $found) {
                if ($found->getExtension() === 'php') {
                    $files[] = $found->getPathname();
                }
            }
        }

        return array_values(array_unique($files));
    }
}
