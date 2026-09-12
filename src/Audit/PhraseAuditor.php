<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Audit;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource as FilamentResource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component as SchemaComponent;
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
use Syriable\Filament\Plugins\AutoTranslator\Contracts\PhraseCatalog;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseDecision;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseScope;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseSlot;
use Syriable\Filament\Plugins\AutoTranslator\PhraseBinder;
use Syriable\Filament\Plugins\AutoTranslator\PhraseIdentity;
use Syriable\Filament\Plugins\AutoTranslator\PhraseResolution;
use Syriable\Filament\Plugins\AutoTranslator\PhraseResolver;
use Syriable\Filament\Plugins\AutoTranslator\Support\NameNormalizer;
use Syriable\Filament\Plugins\AutoTranslator\Sync\CatalogWalkLivewire;
use Throwable;

class PhraseAuditor
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
        private PhraseBinder $binder,
        private PhraseResolver $resolver,
        private ActionNotificationScanner $notificationScanner,
    ) {}

    /**
     * @return array<string, array<string, true>>
     */
    public function livePrefixes(): array
    {
        return $this->livePrefixes;
    }

    /**
     * @return array<string, array<string, true>>
     */
    public function livePages(): array
    {
        return $this->livePages;
    }

    /**
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
            $resources = [];

            if (app()->bound('filament')) {
                try {
                    $resources = Filament::getResources();
                } catch (Throwable) {
                    $resources = [];
                }
            }

            foreach ($resources as $resource) {
                if (! is_subclass_of($resource, FilamentResource::class)) {
                    continue;
                }

                if (! is_a($resource, PhraseCatalog::class, true)) {
                    continue;
                }

                $this->auditResource($resource);
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
     * @param  class-string<FilamentResource&PhraseCatalog>  $resource
     */
    private function auditResource(string $resource): void
    {
        $catalogId = $resource::phraseCatalogId();

        $this->auditChrome($resource, $catalogId);

        $owner = new CatalogWalkLivewire;
        $this->binder->setCatalogId($owner, $catalogId);

        $this->auditForm($resource, $catalogId, $owner);
        $this->auditTable($resource, $catalogId, $owner);
    }

    /**
     * @param  class-string<FilamentResource&PhraseCatalog>  $resource
     */
    private function auditChrome(string $resource, string $catalogId): void
    {
        $this->record($this->resolver->resolve(new PhraseIdentity(
            catalogId: $catalogId,
            scope: PhraseScope::Model,
            path: [],
            name: '',
            slot: PhraseSlot::Label,
        )), $catalogId);

        $this->record($this->resolver->resolve(new PhraseIdentity(
            catalogId: $catalogId,
            scope: PhraseScope::Navigation,
            path: [],
            name: '',
            slot: PhraseSlot::Label,
        )), $catalogId);

        foreach ($resource::getPages() as $registration) {
            $page = $this->pageClass($registration);

            if ($page === null) {
                continue;
            }

            $pageName = NameNormalizer::kebabClassBasename($page);

            $this->record($this->resolver->resolve(new PhraseIdentity(
                catalogId: $catalogId,
                scope: PhraseScope::Pages,
                path: [$pageName],
                name: '',
                slot: PhraseSlot::Title,
            )), $catalogId);

            $this->record($this->resolver->resolve(new PhraseIdentity(
                catalogId: $catalogId,
                scope: PhraseScope::Pages,
                path: [$pageName],
                name: '',
                slot: PhraseSlot::Label,
            )), $catalogId);
        }
    }

    /**
     * @param  class-string<FilamentResource&PhraseCatalog>  $resource
     */
    private function auditForm(string $resource, string $catalogId, CatalogWalkLivewire $owner): void
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
     * @param  class-string<FilamentResource&PhraseCatalog>  $resource
     */
    private function auditTable(string $resource, string $catalogId, CatalogWalkLivewire $owner): void
    {
        try {
            $table = $resource::table(Table::make($owner));
        } catch (Throwable) {
            return;
        }

        $this->walkedScopes[$catalogId]['table'] = true;

        foreach ($table->getColumns() as $column) {
            $this->record($this->binder->explain($column, PhraseSlot::Label), $catalogId);
        }

        foreach ($table->getFilters(withHidden: true) as $filter) {
            $this->record($this->binder->explain($filter, PhraseSlot::Label), $catalogId);
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
            $this->record($this->binder->explain($component, PhraseSlot::Label), $catalogId);
        }

        if ($component instanceof Fieldset || $component instanceof Wizard) {
            $this->remember($this->binder->explain($component, PhraseSlot::Label));
        }

        if ($component instanceof Section) {
            $this->remember($this->binder->explain($component, PhraseSlot::Heading));
        }

        if ($component instanceof Field || $component instanceof Entry) {
            $this->auditRelatedFieldActions($component, $catalogId);
            $this->auditFieldChrome($component, $catalogId);
        }

        if ($component instanceof Action) {
            $this->auditAction($component, $catalogId);
        }

        if ($component instanceof EmptyState || $component instanceof Callout) {
            $this->record($this->binder->explain($component, PhraseSlot::Heading), $catalogId);
        }

        if ($component instanceof SchemaText) {
            $this->record($this->binder->explain($component, PhraseSlot::Body), $catalogId);
        }

        if ($component instanceof SchemaComponent) {
            try {
                $childSchema = $component->getChildSchema();
            } catch (Throwable) {
                return;
            }

            if ($childSchema instanceof Schema) {
                foreach ($childSchema->getComponents() as $child) {
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
        $this->record($this->binder->explain($action, PhraseSlot::Label), $catalogId);
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

                $this->record($this->binder->explain($notification, PhraseSlot::Title), $catalogId);
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
            'before_content' => PhraseSlot::BeforeContent,
            'after_content' => PhraseSlot::AfterContent,
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

    private function record(PhraseResolution $resolution, string $catalogId): void
    {
        $this->remember($resolution);

        if (in_array($resolution->decision, [PhraseDecision::Bound, PhraseDecision::NoCatalog, PhraseDecision::Unbound], true)) {
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

    private function remember(PhraseResolution $resolution): void
    {
        if ($resolution->key === '' || in_array($resolution->decision, [PhraseDecision::NoCatalog, PhraseDecision::Unbound], true)) {
            return;
        }

        $catalogId = $resolution->identity->catalogId;
        $scope = $resolution->identity->scope;

        if ($scope === PhraseScope::Pages) {
            $page = $resolution->identity->path[0] ?? null;

            if (is_string($page) && $page !== '') {
                $this->livePages[$catalogId][$page] = true;
            }

            $this->walkedScopes[$catalogId]['pages'] = true;

            return;
        }

        if (in_array($scope, [PhraseScope::Model, PhraseScope::Navigation], true)) {
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

        if (is_string($last) && PhraseSlot::tryFrom($last) instanceof PhraseSlot) {
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
     * @param  array<int, PhraseIdentity>  $identities
     * @return array<int, array{key: string, catalog: string, decision: string, locale: string, text: ?string}>
     */
    public function auditIdentities(array $identities): array
    {
        $findings = [];

        foreach ($identities as $identity) {
            $resolution = $this->resolver->resolve($identity);

            if (in_array($resolution->decision, [PhraseDecision::Bound], true)) {
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
