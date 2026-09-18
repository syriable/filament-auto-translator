<?php

declare(strict_types=1);

namespace Syriable\Translation\Extraction;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource as FilamentResource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component as SchemaComponent;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmptyState;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text as SchemaText;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Syriable\Translation\Binding\MessageBinder;
use Syriable\Translation\Catalog\MessageResolver;
use Syriable\Translation\Discovery\DiscoveredDomain;
use Syriable\Translation\Discovery\DomainRegistry;
use Syriable\Translation\Discovery\PanelResources;
use Syriable\Translation\Enums\MessageSlot;
use Syriable\Translation\Enums\MessageSurface;
use Syriable\Translation\Enums\ResolutionOutcome;
use Syriable\Translation\MessageIdentity;
use Syriable\Translation\Resolution;
use Syriable\Translation\Support\NameNormalizer;
use Throwable;

class MessageScanner
{
    /**
     * The name a Livewire component caches its schema under when the catalog's
     * chrome names no other one.
     */
    private const DEFAULT_SCHEMA_NAME = 'form';

    /**
     * The name the walk caches a catalog's chrome under on its host.
     */
    private const CHROME_SCHEMA_NAME = 'content';

    /**
     * @var array<int, array{key: string, catalog: string, decision: string, locale: string, text: ?string}>
     */
    private array $findings = [];

    /**
     * @var array<string, array<string, true>>
     */
    private array $livePrefixes = [];

    /**
     * @var array<string, array<string, true>>
     */
    private array $livePages = [];

    /**
     * @var array<string, array<string, true>>
     */
    private array $walkedScopes = [];

    public function __construct(
        private MessageBinder $binder,
        private MessageResolver $resolver,
        private NotificationScanner $notificationScanner,
        private DomainRegistry $schemaCatalogs,
        private PanelResources $panels,
    ) {}

    /**
     * Plumbing MessageExtractor reads back after a walk. Public only because
     * PHP has no package-private; not part of the supported surface.
     *
     * @internal
     *
     * @return array<string, array<string, true>>
     */
    public function livePrefixes(): array
    {
        return $this->livePrefixes;
    }

    /**
     * Plumbing MessageExtractor reads back after a walk. Public only because
     * PHP has no package-private; not part of the supported surface.
     *
     * @internal
     *
     * @return array<string, array<string, true>>
     */
    public function livePages(): array
    {
        return $this->livePages;
    }

    /**
     * Plumbing MessageExtractor reads back after a walk. Public only because
     * PHP has no package-private; not part of the supported surface.
     *
     * @internal
     *
     * @return array<string, array<string, true>>
     */
    public function walkedScopes(): array
    {
        return $this->walkedScopes;
    }

    /**
     * @return array<int, array{key: string, catalog: string, decision: string, locale: string, text: ?string}>
     */
    public function audit(?string $locale = null): array
    {
        $this->resetWalk();
        $this->binder->registerHooks();
        $originalLocale = app()->getLocale();

        if ($locale !== null) {
            app()->setLocale($locale);
        }

        try {
            foreach ($this->panels->catalogs() as $resource) {
                $this->auditResource($resource);
            }

            foreach ($this->schemaCatalogs->catalogs() as $catalog) {
                $this->auditDiscoveredDomain($catalog);
            }
        } finally {
            app()->setLocale($originalLocale);
        }

        return $this->findings;
    }

    /**
     * @param  array<int, mixed>  $components
     * @return array<int, array{key: string, catalog: string, decision: string, locale: string, text: ?string}>
     */
    public function auditComponents(array $components, string $catalogId): array
    {
        $this->resetWalk();
        $this->binder->registerHooks();

        foreach ($components as $component) {
            $this->auditComponent($component, $catalogId);
        }

        return $this->findings;
    }

    /**
     * @param  class-string<FilamentResource>  $resource
     */
    private function auditResource(string $resource): void
    {
        if (! method_exists($resource, 'translationDomain')) {
            return;
        }

        $catalogId = $resource::translationDomain();

        $this->auditChrome($resource, $catalogId);

        $owner = new ExtractionHost;
        $this->binder->setCatalogId($owner, $catalogId);

        $this->auditForm($resource, $catalogId, $owner);
        $this->auditTable($resource, $catalogId, $owner);
    }

    /**
     * Walks a catalog that owns a schema but is not a Filament resource, such as
     * a Livewire form schema on the public site.
     */
    private function auditDiscoveredDomain(DiscoveredDomain $catalog): void
    {
        $catalogId = $catalog->catalogId;
        $owner = new ExtractionHost;
        $this->binder->setCatalogId($owner, $catalogId);

        $chrome = $this->mountDomainContent($catalog, $owner);

        try {
            $schema = $catalog->build(Schema::make($owner)->key($chrome['embedded'] ?? self::DEFAULT_SCHEMA_NAME));
        } catch (Throwable) {
            return;
        }

        if (! $schema instanceof Schema) {
            return;
        }

        if ($chrome['built']) {
            $this->walkedScopes[$catalogId]['form'] = true;
        }

        foreach ($schema->getComponents() as $component) {
            $this->auditComponent($component, $catalogId);
        }

        foreach ($chrome['children'] as $component) {
            $this->auditComponent($component, $catalogId);
        }
    }

    /**
     * Mounts the chrome a catalog builds around its schema.
     *
     * This runs before the schema itself, because the wrapper is what lends
     * the schema its path: the node embedding it has to exist before a field
     * inside can ask which component it hangs from. The name that node
     * embeds is handed back so the schema can be keyed with it, the way a
     * Livewire component keys the schema it caches.
     *
     * The chrome components live in the same form scope as the schema's own,
     * because they are the same form; the embedded node itself is not walked,
     * or the fields would be recorded a second time under their own path.
     *
     * A builder that throws — one reading the signed-in user, say — leaves the
     * scope unpruned rather than letting its keys look orphaned, since
     * deleting live copy is the one failure that cannot be undone by a rerun.
     *
     * @return array{built: bool, embedded: ?string, children: array<int, mixed>}
     */
    private function mountDomainContent(DiscoveredDomain $catalog, ExtractionHost $owner): array
    {
        if ($catalog->contentMethod === null) {
            return ['built' => true, 'embedded' => null, 'children' => []];
        }

        try {
            $content = $catalog->buildContent();

            if (! $content instanceof SchemaComponent) {
                return ['built' => true, 'embedded' => null, 'children' => []];
            }

            // the builder hands back a loose component; the walk reads parents
            // and owners through the container, and getComponents() is what
            // binds it — without that call every child lookup throws
            $chrome = Schema::make($owner)->components([$content]);
            $mounted = $chrome->getComponents();

            // the walk reaches the chrome the way a page does, through the
            // Livewire component, so a field inside the embedded schema can
            // find the node that embeds it
            $owner->rememberSchema(self::CHROME_SCHEMA_NAME, $chrome);

            $children = [];
            $embedded = null;

            foreach ($mounted as $component) {
                $embedded ??= $this->embeddedSchemaName($component);
                $children = [...$children, ...$this->contentChildren($component)];
            }
        } catch (Throwable) {
            return ['built' => false, 'embedded' => null, 'children' => []];
        }

        return ['built' => true, 'embedded' => $embedded, 'children' => $children];
    }

    /**
     * @return array<int, mixed>
     */
    private function contentChildren(mixed $content): array
    {
        if (! $content instanceof SchemaComponent) {
            return [];
        }

        $children = [];

        foreach ($content->getChildSchemas() as $childSchema) {
            foreach ($childSchema->getComponents() as $component) {
                if ($component instanceof EmbeddedSchema) {
                    continue;
                }

                $children[] = $component;
            }
        }

        return $children;
    }

    /**
     * The name of the first schema the chrome embeds, if it embeds one.
     */
    private function embeddedSchemaName(mixed $component, int $depth = 0): ?string
    {
        if (! $component instanceof SchemaComponent) {
            return null;
        }

        if ($component instanceof EmbeddedSchema) {
            return $component->getName();
        }

        if ($depth >= (int) config('translations.max_parent_depth', 32)) {
            return null;
        }

        try {
            $childSchemas = $component->getChildSchemas();
        } catch (Throwable) {
            return null;
        }

        $name = null;

        foreach ($childSchemas as $childSchema) {
            foreach ($childSchema->getComponents() as $child) {
                $name ??= $this->embeddedSchemaName($child, $depth + 1);
            }
        }

        return $name;
    }

    /**
     * @param  class-string<FilamentResource>  $resource
     */
    private function auditChrome(string $resource, string $catalogId): void
    {
        $this->record($this->resolver->resolve(new MessageIdentity(
            catalogId: $catalogId,
            scope: MessageSurface::Model,
            path: [],
            name: '',
            slot: MessageSlot::Label,
        )), $catalogId);

        $this->record($this->resolver->resolve(new MessageIdentity(
            catalogId: $catalogId,
            scope: MessageSurface::Navigation,
            path: [],
            name: '',
            slot: MessageSlot::Label,
        )), $catalogId);

        foreach ($resource::getPages() as $registration) {
            $page = $this->pageClass($registration);

            if ($page === null) {
                continue;
            }

            $pageName = NameNormalizer::kebabClassBasename($page);

            $this->record($this->resolver->resolve(new MessageIdentity(
                catalogId: $catalogId,
                scope: MessageSurface::Pages,
                path: [$pageName],
                name: '',
                slot: MessageSlot::Title,
            )), $catalogId);

            $this->record($this->resolver->resolve(new MessageIdentity(
                catalogId: $catalogId,
                scope: MessageSurface::Pages,
                path: [$pageName],
                name: '',
                slot: MessageSlot::Label,
            )), $catalogId);
        }
    }

    /**
     * @param  class-string<FilamentResource>  $resource
     */
    private function auditForm(string $resource, string $catalogId, ExtractionHost $owner): void
    {
        try {
            $schema = $resource::form(Schema::make($owner));
        } catch (Throwable) {
            return;
        }

        $this->walkedScopes[$catalogId]['form'] = true;
        $this->walkedScopes[$catalogId]['infolist'] = true;

        foreach ($schema->getComponents() as $component) {
            $this->auditComponent($component, $catalogId);
        }
    }

    /**
     * @param  class-string<FilamentResource>  $resource
     */
    private function auditTable(string $resource, string $catalogId, ExtractionHost $owner): void
    {
        try {
            $table = $resource::table(Table::make($owner));
        } catch (Throwable) {
            return;
        }

        $this->walkedScopes[$catalogId]['table'] = true;

        foreach ($table->getColumns() as $column) {
            $this->record($this->binder->explain($column, MessageSlot::Label), $catalogId);
        }

        foreach ($table->getFilters(withHidden: true) as $filter) {
            $this->record($this->binder->explain($filter, MessageSlot::Label), $catalogId);
        }

        foreach ([
            ...$table->getRecordActions(),
            ...$table->getToolbarActions(),
            ...$table->getHeaderActions(),
            ...$table->getEmptyStateActions(),
        ] as $action) {
            $this->auditTableAction($action, $catalogId);
        }
    }

    private function auditTableAction(mixed $action, string $catalogId): void
    {
        if ($action instanceof ActionGroup) {
            foreach ($action->getFlatActions() as $flat) {
                $this->auditTableAction($flat, $catalogId);
            }

            return;
        }

        if (! $action instanceof Action) {
            return;
        }

        $this->auditAction($action, $catalogId);
    }

    private function auditComponent(mixed $component, string $catalogId): void
    {
        if ($component instanceof Field || $component instanceof Entry || $component instanceof Step || $component instanceof Tab || $component instanceof Tabs) {
            $this->record($this->binder->explain($component, MessageSlot::Label), $catalogId);
        }

        if ($component instanceof Fieldset || $component instanceof Wizard) {
            $this->remember($this->binder->explain($component, MessageSlot::Label));
        }

        if ($component instanceof Section) {
            $this->remember($this->binder->explain($component, MessageSlot::Heading));
        }

        if ($component instanceof Field || $component instanceof Entry) {
            $this->auditRelatedFieldActions($component, $catalogId);
            $this->auditFieldChrome($component, $catalogId);
        }

        if ($component instanceof Action) {
            $this->auditAction($component, $catalogId);
        }

        if ($component instanceof EmptyState || $component instanceof Callout) {
            $this->record($this->binder->explain($component, MessageSlot::Heading), $catalogId);
        }

        if ($component instanceof SchemaText) {
            $this->record($this->binder->explain($component, MessageSlot::Body), $catalogId);
        }

        if ($this->isKeyedCustomComponent($component)) {
            $this->record($this->binder->explain($component, MessageSlot::Label), $catalogId);
        }

        if ($component instanceof SchemaComponent) {
            // every child schema, not only the default one: a section's footer
            // and header are child schemas too, and the copy in them is copy
            try {
                $childSchemas = $component->getChildSchemas();
            } catch (Throwable) {
                return;
            }

            foreach ($childSchemas as $childSchema) {
                foreach ($childSchema->getComponents() as $child) {
                    if ($child instanceof EmbeddedSchema) {
                        continue;
                    }

                    $this->auditComponent($child, $catalogId);
                }
            }
        }
    }

    private function auditRelatedFieldActions(Field|Entry $component, string $catalogId): void
    {
        foreach ($this->relatedActionsOf($component) as $action) {
            if ($action->getSchemaComponent() === null) {
                $action->schemaComponent($component);
            }

            $this->auditAction($action, $catalogId);
        }
    }

    private function auditAction(Action $action, string $catalogId): void
    {
        $this->record($this->binder->explain($action, MessageSlot::Label), $catalogId);
        $this->auditActionNotifications($action, $catalogId);
    }

    private function auditActionNotifications(Action $action, string $catalogId): void
    {
        $statuses = $this->notificationScanner->statuses($action);

        if ($statuses === []) {
            return;
        }

        $this->binder->pushAction($action);

        try {
            foreach ($statuses as $status) {
                $notification = $this->notificationForStatus($status);

                if (! $notification instanceof Notification) {
                    continue;
                }

                $this->record($this->binder->explain($notification, MessageSlot::Title), $catalogId);
            }
        } finally {
            $this->binder->popAction();
        }
    }

    private function notificationForStatus(string $status): ?Notification
    {
        return match ($status) {
            'success' => Notification::make()->success(),
            'danger' => Notification::make()->danger(),
            'info' => Notification::make()->info(),
            'warning' => Notification::make()->warning(),
            default => null,
        };
    }

    /**
     * @return array<int, Action>
     */
    private function relatedActionsOf(Field|Entry $component): array
    {
        return array_values($component->getHintActions());
    }

    /**
     * A component from outside Filament's own set that names itself with
     * ->key() and carries a label — a package's separator, say.
     *
     * The binder fills those now, so extraction has to offer the key or the
     * pruner would read it as dead on the next run.
     *
     * Filament first-party components are excluded: layout wrappers like
     * `Actions` are often keyed for Livewire identity only and must not
     * produce a stubbed `…form-actions.label`.
     */
    private function isKeyedCustomComponent(mixed $component): bool
    {
        if (! $component instanceof SchemaComponent) {
            return false;
        }

        if (str_starts_with($component::class, 'Filament\\')) {
            return false;
        }

        foreach ([Field::class, Entry::class, Section::class, Fieldset::class, Wizard::class, Step::class, Tabs::class, Tab::class, EmptyState::class, Callout::class, SchemaText::class] as $dedicated) {
            if ($component instanceof $dedicated) {
                return false;
            }
        }

        if (! method_exists($component, 'getLabel') || ! method_exists($component, 'hasCustomLabel')) {
            return false;
        }

        return filled($component->getKey(isAbsolute: false));
    }

    private function auditFieldChrome(Field|Entry $component, string $catalogId): void
    {
        foreach ([
            'before_content' => MessageSlot::BeforeContent,
            'after_content' => MessageSlot::AfterContent,
            'below_label' => MessageSlot::BelowLabel,
        ] as $key => $slot) {
            try {
                $schema = $component->getChildSchema($key);
            } catch (Throwable) {
                continue;
            }

            if (! $schema instanceof Schema) {
                continue;
            }

            if ($schema->getComponents() === []) {
                continue;
            }

            $this->record($this->binder->explain($component, $slot), $catalogId);
        }
    }

    private function record(Resolution $resolution, string $catalogId): void
    {
        $this->remember($resolution);

        if (in_array($resolution->decision, [ResolutionOutcome::Bound, ResolutionOutcome::NoCatalog, ResolutionOutcome::Unbound], true)) {
            return;
        }

        $this->findings[] = [
            'key' => $resolution->key,
            'catalog' => $catalogId,
            'decision' => $resolution->decision->value,
            'locale' => $resolution->locale,
            'text' => is_string($resolution->text) ? $resolution->text : null,
        ];
    }

    private function remember(Resolution $resolution): void
    {
        if ($resolution->key === '' || in_array($resolution->decision, [ResolutionOutcome::NoCatalog, ResolutionOutcome::Unbound], true)) {
            return;
        }

        $catalogId = $resolution->identity->catalogId;
        $scope = $resolution->identity->scope;

        if ($scope === MessageSurface::Pages) {
            $page = $resolution->identity->path[0] ?? null;

            if (is_string($page) && $page !== '') {
                $this->livePages[$catalogId][$page] = true;
            }

            $this->walkedScopes[$catalogId]['pages'] = true;

            return;
        }

        if (in_array($scope, [MessageSurface::Model, MessageSurface::Navigation], true)) {
            return;
        }

        $prefix = $this->prefixFromKey($catalogId, $resolution->key);

        if ($prefix !== '') {
            $this->livePrefixes[$catalogId][$prefix] = true;
        }

        $this->walkedScopes[$catalogId][$scope->value] = true;
    }

    private function prefixFromKey(string $catalogId, string $compiledKey): string
    {
        $group = str_replace('.', '/', $catalogId).'.';

        if (! str_starts_with($compiledKey, $group)) {
            return '';
        }

        $suffix = substr($compiledKey, strlen($group));

        if ($suffix === '') {
            return '';
        }

        $segments = explode('.', $suffix);
        $last = $segments[array_key_last($segments)] ?? null;

        if (is_string($last) && MessageSlot::tryFrom($last) instanceof MessageSlot) {
            array_pop($segments);
        }

        return implode('.', $segments);
    }

    private function resetWalk(): void
    {
        $this->findings = [];
        $this->livePrefixes = [];
        $this->livePages = [];
        $this->walkedScopes = [];
    }

    /**
     * @param  array<int, MessageIdentity>  $identities
     * @return array<int, array{key: string, catalog: string, decision: string, locale: string, text: ?string}>
     */
    public function auditIdentities(array $identities): array
    {
        $findings = [];

        foreach ($identities as $identity) {
            $resolution = $this->resolver->resolve($identity);

            if (in_array($resolution->decision, [ResolutionOutcome::Bound], true)) {
                continue;
            }

            $findings[] = [
                'key' => $resolution->key,
                'catalog' => $identity->catalogId,
                'decision' => $resolution->decision->value,
                'locale' => $resolution->locale,
                'text' => is_string($resolution->text) ? $resolution->text : null,
            ];
        }

        return $findings;
    }

    /**
     * @return class-string|null
     */
    private function pageClass(mixed $registration): ?string
    {
        if (is_string($registration) && class_exists($registration)) {
            return $registration;
        }

        if (! is_object($registration) || ! method_exists($registration, 'getPage')) {
            return null;
        }

        $page = $registration->getPage();

        if (! is_string($page) || $page === '' || ! class_exists($page)) {
            return null;
        }

        return $page;
    }
}
