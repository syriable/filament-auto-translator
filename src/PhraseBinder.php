<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Events\ActionCalled;
use Filament\Actions\Events\ActionCalling;
use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Notifications\Notification;
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
use Filament\Support\Components\Component as SupportComponent;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Syriable\Filament\Plugins\AutoTranslator\Contracts\PhraseCatalog;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseDecision;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseMode;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseScope;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseSlot;
use Syriable\Filament\Plugins\AutoTranslator\Exceptions\ParentDepthExceededException;
use Syriable\Filament\Plugins\AutoTranslator\Support\NameNormalizer;
use Throwable;

class PhraseBinder
{
    /**
     * @var array<int, true>
     */
    private array $resolvingComponents = [];

    /**
     * @var array<int, Action>
     */
    private array $actionStack = [];

    public function __construct(
        private PhraseResolver $resolver,
        private PhraseBindings $bindings,
        private PhraseRegistry $registry,
    ) {}

    public function registerHooks(): void
    {
        if ($this->registry->hooksRegistered) {
            return;
        }

        $this->registry->hooksRegistered = true;
        $binder = $this;

        SupportComponent::macro('phrase', function (string $name) use ($binder): static {
            $binder->setPhraseName($this, $name);

            return $this;
        });

        SupportComponent::macro('catalog', function (string $id) use ($binder): static {
            $binder->setCatalogId($this, $id);

            return $this;
        });

        Field::configureUsing(function (Field $field) use ($binder): void {
            $binder->bindNamedChrome($field);
        });

        Entry::configureUsing(function (Entry $entry) use ($binder): void {
            $binder->bindNamedChrome($entry);
        });

        Section::configureUsing(function (Section $section) use ($binder): void {
            if (! filled($section->getHeading())) {
                $section->heading(fn (): ?string => $binder->boundText($section, PhraseSlot::Heading));
            }

            if (! filled($section->getDescription())) {
                $section->description(fn (): ?string => $binder->boundText($section, PhraseSlot::Description));
            }
        });

        Fieldset::configureUsing(function (Fieldset $fieldset) use ($binder): void {
            $binder->bindFieldset($fieldset);
        });

        Wizard::configureUsing(function (Wizard $wizard) use ($binder): void {
            if ($wizard->hasCustomLabel()) {
                return;
            }

            $wizard->label(fn (): ?string => $binder->boundText($wizard, PhraseSlot::Label));
        });

        Step::configureUsing(function (Step $step) use ($binder): void {
            $binder->bindMakeArgumentLabel($step);
        });

        Tabs::configureUsing(function (Tabs $tabs) use ($binder): void {
            $binder->bindMakeArgumentLabel($tabs);
        });

        Tab::configureUsing(function (Tab $tab) use ($binder): void {
            $binder->bindMakeArgumentLabel($tab);
        });

        EmptyState::configureUsing(function (EmptyState $emptyState) use ($binder): void {
            $binder->bindEmptyState($emptyState);
        });

        Callout::configureUsing(function (Callout $callout) use ($binder): void {
            $binder->bindCallout($callout);
        });

        SchemaText::configureUsing(function (SchemaText $text) use ($binder): void {
            $binder->bindSchemaText($text);
        });

        Notification::configureUsing(function (Notification $notification) use ($binder): void {
            $binder->bindNotification($notification);
        });

        Action::configureUsing(function (Action $action) use ($binder): void {
            $binder->bindActionLabel($action);
            $binder->bindActionModalChrome($action);
        });

        Column::configureUsing(function (Column $column) use ($binder): void {
            $binder->bindColumn($column);
        });

        BaseFilter::configureUsing(function (BaseFilter $filter) use ($binder): void {
            $binder->bindFilterLabel($filter);
            $binder->bindFilterIndicator($filter);
        });

        Event::listen(ActionCalling::class, function (mixed $event) use ($binder): void {
            $action = $binder->actionFromEvent($event);

            if ($action instanceof Action) {
                $binder->pushAction($action);
            }
        });

        Event::listen(ActionCalled::class, function () use ($binder): void {
            $binder->popAction();
        });
    }

    public function boundText(object $component, PhraseSlot $slot): ?string
    {
        $resolution = $this->evaluate($component, $slot);

        return $resolution->text;
    }

    private function boundPresentText(object $component, PhraseSlot $slot): ?string
    {
        $resolution = $this->evaluate($component, $slot);

        if (! in_array($resolution->decision, [PhraseDecision::Bound, PhraseDecision::UsedFallback], true)) {
            return null;
        }

        return $resolution->text;
    }

    public function setPhraseName(object $component, string $name): void
    {
        $this->bindings->setPhrase($component, $name);
    }

    public function setCatalogId(object $component, string $id): void
    {
        $this->bindings->setCatalog($component, $id);
    }

    public function pushAction(Action $action): void
    {
        $this->actionStack[] = $action;
    }

    public function popAction(): void
    {
        array_pop($this->actionStack);
    }

    public function actionFromEvent(mixed $event): ?Action
    {
        if ($event instanceof Action) {
            return $event;
        }

        if ($event instanceof ActionCalling || $event instanceof ActionCalled) {
            return $event->getAction();
        }

        return null;
    }

    public function explain(object $component, PhraseSlot $slot): PhraseResolution
    {
        return $this->evaluate($component, $slot);
    }

    public function evaluate(object $component, PhraseSlot $slot): PhraseResolution
    {
        $id = spl_object_id($component);

        if (isset($this->resolvingComponents[$id])) {
            return new PhraseResolution(
                identity: new PhraseIdentity(
                    catalogId: '',
                    scope: PhraseScope::Form,
                    path: [],
                    name: '',
                    slot: $slot,
                ),
                key: '',
                decision: PhraseDecision::Unbound,
                reason: 'Skipped recursive heading evaluation.',
                mode: $this->resolver->mode(),
            );
        }

        $this->resolvingComponents[$id] = true;

        try {
            return $this->resolveComponent($component, $slot);
        } finally {
            unset($this->resolvingComponents[$id]);
        }
    }

    private function resolveComponent(object $component, PhraseSlot $slot): PhraseResolution
    {
        $catalogId = $this->catalogIdFor($component);

        if ($catalogId === null) {
            return new PhraseResolution(
                identity: new PhraseIdentity(
                    catalogId: '',
                    scope: PhraseScope::Form,
                    path: [],
                    name: '',
                    slot: $slot,
                ),
                key: '',
                decision: PhraseDecision::NoCatalog,
                reason: 'The owning Livewire class is not a phrase catalog.',
                mode: $this->resolver->mode(),
            );
        }

        if ($component instanceof Notification && ($this->notificationAction($component) === null || $this->notificationStatusName($component) === null)) {
            return new PhraseResolution(
                identity: new PhraseIdentity($catalogId, PhraseScope::Form, [], '', $slot),
                key: '',
                decision: PhraseDecision::Unbound,
                reason: 'The notification has no owning action or status.',
                mode: $this->resolver->mode(),
            );
        }

        [$scope, $path] = $this->scopeAndPath($component);
        $name = $this->leafName($component);

        if ($name === null && ! in_array($slot, [PhraseSlot::Heading, PhraseSlot::Title], true)) {
            return new PhraseResolution(
                identity: new PhraseIdentity($catalogId, $scope, $path, '', $slot),
                key: '',
                decision: PhraseDecision::Unbound,
                reason: 'The component has no machine name.',
                mode: $this->resolver->mode(),
            );
        }

        $identity = new PhraseIdentity(
            catalogId: $catalogId,
            scope: $scope,
            path: $path,
            name: $name ?? '',
            slot: $slot,
        );

        return $this->resolver->resolve($identity);
    }

    private function bindActionLabel(Action $action): void
    {
        $captured = $action->getLabel();

        $action->label(function () use ($action, $captured): mixed {
            $text = $this->boundText($action, PhraseSlot::Label);

            if ($text !== null) {
                return $text;
            }

            $resolution = $this->explain($action, PhraseSlot::Label);

            if ($resolution->decision === PhraseDecision::NoCatalog) {
                return $captured;
            }

            if ($resolution->decision === PhraseDecision::Missing && $resolution->mode === PhraseMode::Lenient) {
                return $captured;
            }

            return $captured;
        });
    }

    private function bindActionModalChrome(Action $action): void
    {
        $action->modalHeading(fn (): ?string => $this->boundText($action, PhraseSlot::ModalHeading));
        $action->modalDescription(fn (): ?string => $this->boundText($action, PhraseSlot::ModalDescription));
        $action->modalCancelActionLabel(fn (): ?string => $this->boundText($action, PhraseSlot::ModalCancelActionLabel));
        $action->modalSubmitActionLabel(fn (): ?string => $this->boundText($action, PhraseSlot::ModalSubmitActionLabel));
    }

    private function bindColumn(Column $column): void
    {
        $captured = $column->getLabel();

        $column->label(function () use ($column, $captured): mixed {
            return $this->boundText($column, PhraseSlot::Label) ?? $captured;
        });

        $this->bindColumnPrefix($column);
    }

    private function bindColumnPrefix(Column $column): void
    {
        if (! method_exists($column, 'prefix')) {
            return;
        }

        call_user_func(
            [$column, 'prefix'],
            fn (): ?string => $this->boundText($column, PhraseSlot::Prefix),
        );
    }

    private function bindFilterLabel(BaseFilter $filter): void
    {
        $captured = $filter->getLabel();

        $filter->label(function () use ($filter, $captured): mixed {
            return $this->boundText($filter, PhraseSlot::Label) ?? $captured;
        });
    }

    private function bindFilterIndicator(BaseFilter $filter): void
    {
        $filter->indicator(fn (): ?string => $this->boundText($filter, PhraseSlot::Indicator));
    }

    private function bindFieldset(Fieldset $fieldset): void
    {
        if ($fieldset->hasCustomLabel()) {
            return;
        }

        $fieldset->label(fn (): ?string => $this->boundPresentText($fieldset, PhraseSlot::Label));
    }

    private function bindNamedChrome(Field|Entry $component): void
    {
        if (! $component->hasCustomLabel()) {
            $component->label(fn (): ?string => $this->boundText($component, PhraseSlot::Label));
        }

        $component->helperText(fn (): ?string => $this->boundText($component, PhraseSlot::HelperText));

        if (! $component->hasHint()) {
            $component->hint(fn (): ?string => $this->boundText($component, PhraseSlot::Hint));
        }

        $this->bindFieldPlaceholder($component);
        $this->bindFieldChromeContent($component);
    }

    private function bindFieldPlaceholder(Field|Entry $component): void
    {
        if (! method_exists($component, 'placeholder')) {
            return;
        }

        call_user_func(
            [$component, 'placeholder'],
            fn (): ?string => $this->boundText($component, PhraseSlot::Placeholder),
        );
    }

    private function bindFieldChromeContent(Field|Entry $component): void
    {
        $component->beforeContent(fn (): ?string => $this->boundText($component, PhraseSlot::BeforeContent));
        $component->afterContent(fn (): ?string => $this->boundText($component, PhraseSlot::AfterContent));
    }

    private function bindMakeArgumentLabel(Step|Tab|Tabs $component): void
    {
        $captured = $component->getLabel();
        $this->assignMachineKeyFromCaptured($component, $captured);

        $component->label(function () use ($component, $captured): mixed {
            $text = $this->boundText($component, PhraseSlot::Label);

            if ($text !== null) {
                return $text;
            }

            return $captured;
        });
    }

    private function bindNotification(Notification $notification): void
    {
        $action = $this->currentAction();

        if ($action instanceof Action) {
            $this->bindings->setOwner($notification, $action);
        }

        try {
            $capturedTitle = $notification->getTitle();
        } catch (Throwable) {
            $capturedTitle = null;
        }

        try {
            $capturedBody = $notification->getBody();
        } catch (Throwable) {
            $capturedBody = null;
        }

        $notification->title(function () use ($notification, $capturedTitle): mixed {
            $text = $this->boundText($notification, PhraseSlot::Title);

            if ($text !== null) {
                return $text;
            }

            return $capturedTitle;
        });

        $notification->body(function () use ($notification, $capturedBody): mixed {
            $text = $this->boundText($notification, PhraseSlot::Body);

            if ($text !== null) {
                return $text;
            }

            return $capturedBody;
        });
    }

    private function bindEmptyState(EmptyState $component): void
    {
        $captured = $component->getHeading();
        $this->assignMachineKeyFromCaptured($component, $captured);

        $component->heading(function () use ($component, $captured): mixed {
            $text = $this->boundText($component, PhraseSlot::Heading);

            if ($text !== null) {
                return $text;
            }

            return $captured;
        });

        $component->description(fn (): ?string => $this->boundText($component, PhraseSlot::Description));
    }

    private function bindCallout(Callout $component): void
    {
        try {
            $captured = $component->getHeading();
        } catch (Throwable) {
            return;
        }

        if (! is_string($captured) || $captured === '') {
            return;
        }

        $machine = NameNormalizer::machine($captured);

        if (! NameNormalizer::isValid($machine) || $machine === '') {
            return;
        }

        $component->key($machine, isInheritable: false);

        $component->heading(function () use ($component, $captured): mixed {
            $text = $this->boundText($component, PhraseSlot::Heading);

            if ($text !== null) {
                return $text;
            }

            return $captured;
        });

        $component->description(fn (): ?string => $this->boundText($component, PhraseSlot::Description));
    }

    private function bindSchemaText(SchemaText $component): void
    {
        try {
            $captured = $component->getContent();
        } catch (Throwable) {
            return;
        }

        if (! is_string($captured) || $captured === '') {
            return;
        }

        $machine = NameNormalizer::machine($captured);

        if (! NameNormalizer::isValid($machine) || $machine === '') {
            return;
        }

        $component->key($machine, isInheritable: false);

        $component->content(function () use ($component, $captured): mixed {
            $chrome = $this->fieldChromeBinding($component);

            if ($chrome !== null) {
                [$owner, $slot] = $chrome;

                return $this->boundText($owner, $slot);
            }

            $text = $this->boundText($component, PhraseSlot::Body);

            if ($text !== null) {
                return $text;
            }

            return $captured;
        });

        if (! $component->hasTooltip()) {
            $component->tooltip(function () use ($component): ?string {
                if ($this->fieldChromeBinding($component) !== null) {
                    return null;
                }

                return $this->boundText($component, PhraseSlot::Tooltip);
            });
        }
    }

    private function assignMachineKeyFromCaptured(SchemaComponent $component, mixed $captured): void
    {
        if (! is_string($captured) || $captured === '') {
            return;
        }

        $machine = NameNormalizer::machine($captured);

        if (! NameNormalizer::isValid($machine) || $machine === '') {
            return;
        }

        $component->key($machine, isInheritable: false);
    }

    /**
     * @return array{0: Field|Entry, 1: PhraseSlot}|null
     */
    private function fieldChromeBinding(SchemaText $text): ?array
    {
        try {
            $container = $text->getContainer();
            $parent = $container->getParentComponent();
        } catch (Throwable) {
            return null;
        }

        if (! $parent instanceof Field && ! $parent instanceof Entry) {
            return null;
        }

        foreach ($this->fieldChromeSlots() as $key => $slot) {
            try {
                $schema = $parent->getChildSchema($key);
            } catch (Throwable) {
                continue;
            }

            if ($schema === $container) {
                return [$parent, $slot];
            }
        }

        return null;
    }

    /**
     * @return array<string, PhraseSlot>
     */
    private function fieldChromeSlots(): array
    {
        return [
            'before_content' => PhraseSlot::BeforeContent,
            'after_content' => PhraseSlot::AfterContent,
        ];
    }

    public function resolveIdentity(PhraseIdentity $identity): PhraseResolution
    {
        return $this->resolver->resolve($identity);
    }

    /**
     * @return array{0: PhraseScope, 1: array<int, string>}
     */
    private function scopeAndPath(object $component): array
    {
        if ($component instanceof Notification) {
            $action = $this->notificationAction($component);

            if (! $action instanceof Action) {
                return [PhraseScope::Form, ['notifications']];
            }

            [$scope, $path] = $this->scopeAndPath($action);
            $name = $this->leafName($action);

            if ($name !== null && $name !== '') {
                $path[] = $name;
            }

            $path[] = 'notifications';

            return [$scope, $path];
        }

        if ($component instanceof BaseFilter) {
            return [PhraseScope::Table, ['filters']];
        }

        if ($component instanceof Column) {
            return [PhraseScope::Table, ['columns']];
        }

        if ($component instanceof Action) {
            $footerParent = $this->extraModalFooterParent($component);

            if ($footerParent instanceof Action) {
                [$scope, $path] = $this->scopeAndPath($footerParent);
                $parentName = $this->leafName($footerParent);

                if ($parentName !== null && $parentName !== '') {
                    $path[] = $parentName;
                }

                $path[] = 'extra_modal_footer_actions';

                return [$scope, $path];
            }

            $table = $component->getTable();

            if ($table instanceof Table) {
                return [PhraseScope::Table, [$this->tableActionSegment($component, $table)]];
            }

            $owner = $this->relatedFieldOf($component);

            if ($owner instanceof Field || $owner instanceof Entry) {
                $name = $this->leafName($owner);

                return [
                    $this->formScope($owner),
                    [
                        ...$this->schemaPath($owner),
                        ...(($name !== null && $name !== '') ? [$name] : []),
                        'actions',
                    ],
                ];
            }

            if ($this->schemaContainerOf($component) !== null) {
                return [$this->formScope($component), $this->schemaActionPath($component)];
            }

            $livewire = $this->livewireOf($component);

            if ($this->isResourcePageLivewire($livewire)) {
                return [PhraseScope::Pages, $this->pageActionPath($livewire)];
            }

            return [PhraseScope::Actions, []];
        }

        if ($component instanceof SchemaComponent) {
            $action = $this->owningActionOf($component);

            if ($action instanceof Action) {
                [$scope, $path] = $this->scopeAndPath($action);
                $actionName = $this->leafName($action);

                if ($actionName !== null && $actionName !== '') {
                    $path[] = $actionName;
                }

                $path[] = 'schema';
                $path[] = 'components';

                return [$scope, [...$path, ...$this->schemaPath($component)]];
            }

            return [$this->formScope($component), $this->schemaPath($component)];
        }

        return [PhraseScope::Form, []];
    }

    private function formScope(object $component): PhraseScope
    {
        if ($component instanceof Entry) {
            return PhraseScope::Infolist;
        }

        if ($component instanceof Action) {
            $owner = $this->relatedFieldOf($component);

            if ($owner instanceof Entry) {
                return PhraseScope::Infolist;
            }

            if ($owner instanceof Field) {
                return PhraseScope::Form;
            }

            $container = $this->schemaContainerOf($component);

            if (is_object($container) && method_exists($container, 'getParentComponent')) {
                try {
                    $parent = $container->getParentComponent();
                } catch (Throwable) {
                    $parent = null;
                }

                if ($parent instanceof SchemaComponent) {
                    return $this->formScope($parent);
                }
            }
        }

        if ($component instanceof SchemaComponent && $this->schemaContainsEntry($component)) {
            return PhraseScope::Infolist;
        }

        return PhraseScope::Form;
    }

    private function schemaContainsEntry(SchemaComponent $component): bool
    {
        if ($component instanceof Entry) {
            return true;
        }

        try {
            $childSchema = $component->getChildSchema();
        } catch (Throwable) {
            return false;
        }

        if (! $childSchema instanceof Schema) {
            return false;
        }

        foreach ($childSchema->getComponents() as $child) {
            if ($child instanceof Entry) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function schemaActionPath(Action $action): array
    {
        $container = $this->schemaContainerOf($action);

        if (! is_object($container) || ! method_exists($container, 'getParentComponent')) {
            return ['actions'];
        }

        try {
            $parent = $container->getParentComponent();
        } catch (Throwable) {
            $parent = null;
        }

        if (! $parent instanceof SchemaComponent) {
            return ['actions'];
        }

        $parentName = $this->layoutName($parent);

        return [
            ...$this->schemaPath($parent),
            ...(($parentName !== null && $parentName !== '') ? [$parentName, 'schema'] : []),
            'actions',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function pageActionPath(object $livewire): array
    {
        return [NameNormalizer::kebabClassBasename($livewire::class), 'actions'];
    }

    private function isResourcePageLivewire(mixed $livewire): bool
    {
        return is_object($livewire)
            && (method_exists($livewire, 'getResourcePageName') || method_exists($livewire, 'getResource'));
    }

    private function extraModalFooterParent(Action $action): ?Action
    {
        $parent = $action->getParentAction();

        if (! $parent instanceof Action) {
            return null;
        }

        foreach ($parent->getExtraModalFooterActions() as $footer) {
            if ($footer === $action) {
                return $parent;
            }

            if ($footer instanceof Action && $footer->getName() === $action->getName()) {
                return $parent;
            }
        }

        return null;
    }

    private function tableActionSegment(Action $action, Table $table): string
    {
        if ($this->tableHoldsAction($table->getRecordActions(), $action)) {
            return 'record_actions';
        }

        if ($this->tableHoldsAction($table->getToolbarActions(), $action)) {
            return 'toolbar_actions';
        }

        if ($this->tableHoldsAction($table->getHeaderActions(), $action)) {
            return 'header_actions';
        }

        if ($this->tableHoldsAction($table->getEmptyStateActions(), $action)) {
            return 'empty_state_actions';
        }

        return 'actions';
    }

    /**
     * @param  array<int|string, mixed>  $actions
     */
    private function tableHoldsAction(array $actions, Action $needle): bool
    {
        foreach ($actions as $action) {
            if ($action === $needle) {
                return true;
            }

            if ($action instanceof ActionGroup && $this->tableHoldsAction($action->getFlatActions(), $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function schemaPath(SchemaComponent $component): array
    {
        $names = [];
        $current = $component;
        $depth = 0;
        $maxDepth = (int) config('auto-translator.max_parent_depth', 32);

        while ($depth < $maxDepth) {
            $depth++;
            $parent = $this->parentSchemaComponent($current);

            if ($parent === null) {
                break;
            }

            if ($this->bindings->catalog($parent) !== null) {
                break;
            }

            $parentName = $this->layoutName($parent);

            if ($parentName !== null) {
                array_unshift($names, $parentName, 'schema');
            }

            $current = $parent;
        }

        if ($depth >= $maxDepth) {
            throw ParentDepthExceededException::make($maxDepth);
        }

        return $names;
    }

    private function parentSchemaComponent(SchemaComponent $component): ?SchemaComponent
    {
        try {
            return $component->getContainer()->getParentComponent();
        } catch (Throwable) {
            return null;
        }
    }

    private function layoutName(SchemaComponent $component): ?string
    {
        $phrase = $this->bindings->phrase($component);

        if ($phrase !== null) {
            return NameNormalizer::machine($phrase);
        }

        return $this->machineKey($component);
    }

    private function schemaTextName(SchemaText $component): ?string
    {
        $fromKey = $this->machineKey($component);

        if ($fromKey !== null && $fromKey !== '') {
            return $fromKey;
        }

        try {
            $content = $component->getContent();
        } catch (Throwable) {
            return null;
        }

        if (! is_string($content) || $content === '') {
            return null;
        }

        $machine = NameNormalizer::machine($content);

        if (! NameNormalizer::isValid($machine) || $machine === '') {
            return null;
        }

        return $machine;
    }

    private function machineKey(SchemaComponent $component): ?string
    {
        $key = $component->getKey(isAbsolute: false);

        if (! filled($key) || str_contains((string) $key, '::')) {
            return null;
        }

        $machine = NameNormalizer::machine((string) $key);

        if ($machine === 'wizard') {
            return null;
        }

        return $machine;
    }

    private function leafName(object $component): ?string
    {
        $phrase = $this->bindings->phrase($component);

        if ($phrase !== null) {
            return NameNormalizer::machine($phrase);
        }

        if ($component instanceof Notification) {
            return $this->notificationStatusName($component);
        }

        if ($component instanceof Field || $component instanceof Entry || $component instanceof Column || $component instanceof Action || $component instanceof BaseFilter) {
            return NameNormalizer::machine((string) $component->getName());
        }

        if ($component instanceof SchemaText) {
            return $this->schemaTextName($component);
        }

        if ($component instanceof SchemaComponent) {
            return $this->machineKey($component);
        }

        return null;
    }

    private function catalogIdFor(object $component): ?string
    {
        $override = $this->bindings->catalog($component);

        if ($override !== null) {
            return $override;
        }

        if ($component instanceof Notification) {
            $action = $this->notificationAction($component);

            if ($action instanceof Action) {
                return $this->catalogIdFor($action);
            }
        }

        $current = $component;
        $depth = 0;
        $maxDepth = (int) config('auto-translator.max_parent_depth', 32);

        while (is_object($current) && $depth < $maxDepth) {
            $override = $this->bindings->catalog($current);

            if ($override !== null) {
                return $override;
            }

            $current = $current instanceof SchemaComponent
                ? $this->parentSchemaComponent($current)
                : null;

            $depth++;
        }

        $livewire = $this->livewireOf($component);

        if (! is_object($livewire) && $component instanceof Notification) {
            $livewire = $this->currentLivewire();
        }

        if (is_object($livewire)) {
            $livewireCatalog = $this->bindings->catalog($livewire);

            if ($livewireCatalog !== null) {
                return $livewireCatalog;
            }
        }

        if (! is_object($livewire) || ! is_a($livewire, PhraseCatalog::class)) {
            return null;
        }

        return $livewire::phraseCatalogId();
    }

    private function relatedFieldOf(Action $action): Field|Entry|null
    {
        try {
            $owner = $action->getSchemaComponent();
        } catch (Throwable) {
            return null;
        }

        if ($owner instanceof Field || $owner instanceof Entry) {
            return $owner;
        }

        return null;
    }

    private function schemaContainerOf(Action $action): mixed
    {
        try {
            return $action->getSchemaContainer();
        } catch (Throwable) {
            return null;
        }
    }

    private function livewireOf(object $component): mixed
    {
        if (! method_exists($component, 'getLivewire')) {
            return null;
        }

        try {
            return $component->getLivewire();
        } catch (Throwable) {
            return null;
        }
    }

    private function currentLivewire(): mixed
    {
        try {
            return Livewire::current();
        } catch (Throwable) {
            return null;
        }
    }

    private function currentAction(): ?Action
    {
        $action = end($this->actionStack);

        if ($action instanceof Action) {
            return $action;
        }

        return $this->mountedAction();
    }

    private function owningActionOf(SchemaComponent $component): ?Action
    {
        $container = $this->rootSchemaContainer($component);

        if (! $this->containerIsMountedActionSchema($container)) {
            return null;
        }

        return $this->mountedAction($component);
    }

    private function rootSchemaContainer(SchemaComponent $component): mixed
    {
        $current = $component;
        $container = null;
        $depth = 0;
        $maxDepth = (int) config('auto-translator.max_parent_depth', 32);

        while ($depth < $maxDepth) {
            $depth++;

            try {
                $container = $current->getContainer();
                $parent = $container->getParentComponent();
            } catch (Throwable) {
                return $container;
            }

            if (! $parent instanceof SchemaComponent) {
                return $container;
            }

            $current = $parent;
        }

        return $container;
    }

    private function containerIsMountedActionSchema(mixed $container): bool
    {
        if (! $container instanceof Schema) {
            return false;
        }

        try {
            $key = $container->getKey(isAbsolute: false);
        } catch (Throwable) {
            return false;
        }

        if (! is_string($key) || $key === '') {
            return false;
        }

        return str_starts_with(NameNormalizer::machine($key), 'mountedactionschema');
    }

    private function mountedAction(?object $component = null): ?Action
    {
        $livewire = $component !== null
            ? ($this->livewireOf($component) ?? $this->currentLivewire())
            : $this->currentLivewire();

        if (! is_object($livewire) || ! method_exists($livewire, 'getMountedAction')) {
            return null;
        }

        try {
            $mounted = $livewire->getMountedAction();
        } catch (Throwable) {
            return null;
        }

        return $mounted instanceof Action ? $mounted : null;
    }

    private function notificationAction(Notification $notification): ?Action
    {
        $owner = $this->bindings->owner($notification);

        if ($owner instanceof Action) {
            return $owner;
        }

        return $this->currentAction();
    }

    private function notificationStatusName(Notification $notification): ?string
    {
        try {
            $status = $notification->getStatus();
        } catch (Throwable) {
            return null;
        }

        if (! is_string($status) || $status === '') {
            return null;
        }

        $machine = NameNormalizer::machine($status);

        if (! NameNormalizer::isValid($machine) || $machine === '') {
            return null;
        }

        return $machine;
    }
}
