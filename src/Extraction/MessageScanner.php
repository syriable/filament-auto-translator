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

        try {
            $schema = $catalog->build(Schema::make($owner));
        } catch (Throwable) {
            return;
        }

        if (! $schema instanceof Schema) {
            return;
        }

        $this->walkedScopes[$catalogId]['form'] = true;

        foreach ($schema->getComponents() as $component) {
            $this->auditComponent($component, $catalogId);
        }

        $this->auditDomainContent($catalog, $catalogId, $owner);
    }

    /**
     * Walks the chrome a catalog builds around its schema.
     *
     * The components live in the same form scope as the schema's own, because
     * they are the same form; the embedded schema node is skipped so the
     * fields are not walked a second time under the wrapper's path, which
     * would move every key a consumer already has.
     *
     * A builder that throws — one reading the signed-in user, say — leaves the
     * scope unpruned rather than letting its keys look orphaned, since
     * deleting live copy is the one failure that cannot be undone by a rerun.
     */
    private function auditDomainContent(DiscoveredDomain $catalog, string $catalogId, ExtractionHost $owner): void
    {
        if ($catalog->contentMethod === null) {
            return;
        }

        try {
            $content = $catalog->buildContent();

            if (! $content instanceof SchemaComponent) {
                return;
            }

            // the builder hands back a loose component; the walk reads parents
            // and owners through the container, and getComponents() is what
            // binds it — without that call every child lookup throws
            $mounted = Schema::make($owner)->components([$content])->getComponents();

            $children = [];

            foreach ($mounted as $component) {
                $children = [...$children, ...$this->contentChildren($component)];
            }
        } catch (Throwable) {
            unset($this->walkedScopes[$catalogId]['form']);

            return;
        }

        foreach ($children as $component) {
            $this->auditComponent($component, $catalogId);
        }
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
