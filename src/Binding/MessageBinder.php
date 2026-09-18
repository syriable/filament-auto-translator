<?php

declare(strict_types=1);

namespace Syriable\Translation\Binding;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Events\ActionCalled;
use Filament\Actions\Events\ActionCalling;
use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Notifications\Notification;
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
use Filament\Support\Components\Component as SupportComponent;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\HtmlString;
use Illuminate\Translation\Translator;
use Livewire\Livewire;
use Syriable\Translation\Catalog\MessageResolver;
use Syriable\Translation\Discovery\DomainResolver;
use Syriable\Translation\Enums\MessageSlot;
use Syriable\Translation\Enums\MessageSurface;
use Syriable\Translation\Enums\MissingMessagePolicy;
use Syriable\Translation\Enums\ResolutionOutcome;
use Syriable\Translation\Exceptions\ParentDepthExceededException;
use Syriable\Translation\MessageIdentity;
use Syriable\Translation\Resolution;
use Syriable\Translation\Support\NameNormalizer;
use Throwable;

class MessageBinder
{
    /**
     * @var array<int, true>
     */
    private array $resolvingComponents = [];

    /**
     * @var array<int, Action>
     */
    private array $actionStack = [];

    private bool $resolvingEmbeddedParent = false;

    public function __construct(
        private MessageResolver $resolver,
        private ComponentBindings $bindings,
        private MessageOverrides $registry,
        private DomainResolver $domains,
        private Translator $translator,
        private EmbeddedSchemas $embeds,
    ) {}

    public function registerHooks(): void
    {
        if ($this->registry->hooksRegistered) {
            return;
        }

        $this->registry->hooksRegistered = true;
        $binder = $this;

        SupportComponent::macro('messageName', function (string $name) use ($binder): static {
            $binder->setMessageName($this, $name);

            return $this;
        });

        SupportComponent::macro('domain', function (string $id) use ($binder): static {
            $binder->setCatalogId($this, $id);

            return $this;
        });

        /** @param array<string, mixed> $replace */
        $messageReplace = function (array $replace) use ($binder): static {
            $binder->setMessageReplace($this, $replace);

            return $this;
        };

        SupportComponent::macro('messageReplace', $messageReplace);

        SupportComponent::macro('messageHtml', function (bool $condition = true) use ($binder): static {
            $binder->setMessageHtml($this, $condition);

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
                $section->heading(fn (): ?string => $binder->boundText($section, MessageSlot::Heading));
            }

            if (! filled($section->getDescription())) {
                $section->description(fn (): ?string => $binder->boundText($section, MessageSlot::Description));
            }
        });

        Fieldset::configureUsing(function (Fieldset $fieldset) use ($binder): void {
            $binder->bindFieldset($fieldset);
        });

        Wizard::configureUsing(function (Wizard $wizard) use ($binder): void {
            if ($wizard->hasCustomLabel()) {
                return;
            }

            $wizard->label(fn (): ?string => $binder->boundText($wizard, MessageSlot::Label));
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

        SchemaComponent::configureUsing(function (SchemaComponent $component) use ($binder): void {
            $binder->bindKeyedComponentLabel($component);
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

    public function boundText(object $component, MessageSlot $slot): ?string
    {
        $resolution = $this->evaluate($component, $slot);

        return $this->withReplacements($component, $resolution);
    }

    /**
     * Re-reads the line with the component's declared replacements.
     *
     * The resolver caches by identity and locale, which a per-component
     * replacement must not poison, so this runs after the cache and only when
     * the component actually declared any.
     */
    private function withReplacements(object $component, Resolution $resolution): ?string
    {
        if ($resolution->text === null) {
            return null;
        }

        $replace = $this->replacementsFor($component);

        if ($replace === []) {
            return $resolution->text;
        }

        if (! in_array($resolution->decision, [ResolutionOutcome::Bound, ResolutionOutcome::UsedFallbackLocale], true)) {
            return $resolution->text;
        }

        $text = $this->translator->get($resolution->key, $replace, $resolution->locale);

        return is_string($text) ? $text : $resolution->text;
    }

    /**
     * A replacement may be a closure so a URL or a count is resolved at render
     * time rather than when the component is built.
     *
     * @return array<string, mixed>
     */
    private function replacementsFor(object $component): array
    {
        $replace = $this->bindings->replace($component);
        $isHtml = $this->bindings->isHtml($component);

        return array_map(
            function (mixed $value) use ($isHtml): mixed {
                if ($value instanceof Closure) {
                    $value = $value();
                }

                if (! $isHtml) {
                    return $value;
                }

                // the line is markup, so what is poured into it has to be
                // escaped, unless the caller hands over markup of its own
                return $value instanceof Htmlable ? $value->toHtml() : e(is_scalar($value) ? (string) $value : '');
            },
            $replace,
        );
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    public function setMessageReplace(object $component, array $replace): void
    {
        $this->bindings->setReplace($component, $replace);
    }

    public function setMessageHtml(object $component, bool $html): void
    {
        $this->bindings->setHtml($component, $html);
    }

    /**
     * The catalog line, as markup when the component asked for markup.
     *
     * Filament escapes a plain string, which is what copy should be. A
     * component that declares its copy is markup says so once, at the call
     * site, rather than the package deciding by looking for tags: a line that
     * happens to contain a tag is not a licence to stop escaping a whole
     * catalog, and the replacements poured into it are not the author's text.
     */
    private function boundContent(object $component, MessageSlot $slot): string|Htmlable|null
    {
        $text = $this->boundText($component, $slot);

        if ($text === null || ! $this->bindings->isHtml($component)) {
            return $text;
        }

        return new HtmlString($text);
    }

    private function boundPresentText(object $component, MessageSlot $slot): ?string
    {
        $resolution = $this->evaluate($component, $slot);

        if (! in_array($resolution->decision, [ResolutionOutcome::Bound, ResolutionOutcome::UsedFallbackLocale], true)) {
            return null;
        }

        return $this->withReplacements($component, $resolution);
    }

    public function setMessageName(object $component, string $name): void
    {
        $this->bindings->setMessageName($component, $name);
    }

    public function setCatalogId(object $component, string $id): void
    {
        $this->bindings->setDomain($component, $id);
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

    public function explain(object $component, MessageSlot $slot): Resolution
    {
        return $this->evaluate($component, $slot);
    }

    public function evaluate(object $component, MessageSlot $slot): Resolution
    {
        $id = spl_object_id($component);

        if (isset($this->resolvingComponents[$id])) {
            return new Resolution(
                identity: new MessageIdentity(
                    catalogId: '',
                    scope: MessageSurface::Form,
                    path: [],
                    name: '',
                    slot: $slot,
                ),
                key: '',
                decision: ResolutionOutcome::Unbound,
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

    private function resolveComponent(object $component, MessageSlot $slot): Resolution
    {
        $catalogId = $this->catalogIdFor($component);

        if ($catalogId === null) {
            return new Resolution(
                identity: new MessageIdentity(
                    catalogId: '',
                    scope: MessageSurface::Form,
                    path: [],
                    name: '',
                    slot: $slot,
                ),
                key: '',
                decision: ResolutionOutcome::NoCatalog,
                reason: 'The owning Livewire class is not a message catalog.',
                mode: $this->resolver->mode(),
            );
        }

        if ($component instanceof Notification && ($this->notificationAction($component) === null || $this->notificationStatusName($component) === null)) {
            return new Resolution(
                identity: new MessageIdentity($catalogId, MessageSurface::Form, [], '', $slot),
                key: '',
                decision: ResolutionOutcome::Unbound,
                reason: 'The notification has no owning action or status.',
                mode: $this->resolver->mode(),
            );
        }

        [$scope, $path] = $this->scopeAndPath($component);
        $name = $this->leafName($component);

        if ($name === null && ! in_array($slot, [MessageSlot::Heading, MessageSlot::Title], true)) {
            return new Resolution(
                identity: new MessageIdentity($catalogId, $scope, $path, '', $slot),
                key: '',
                decision: ResolutionOutcome::Unbound,
                reason: 'The component has no machine name.',
                mode: $this->resolver->mode(),
            );
        }

        $identity = new MessageIdentity(
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
            $text = $this->boundText($action, MessageSlot::Label);

            if ($text !== null) {
                return $text;
            }

            $resolution = $this->explain($action, MessageSlot::Label);

            if ($resolution->decision === ResolutionOutcome::NoCatalog) {
                return $captured;
            }

            if ($resolution->decision === ResolutionOutcome::Missing && $resolution->mode === MissingMessagePolicy::KeepVendorLabel) {
                return $captured;
            }

            return $captured;
        });
    }

    private function bindActionModalChrome(Action $action): void
    {
        $action->modalHeading(fn (): ?string => $this->boundText($action, MessageSlot::ModalHeading));
        $action->modalDescription(fn (): ?string => $this->boundText($action, MessageSlot::ModalDescription));
        $action->modalCancelActionLabel(fn (): ?string => $this->boundText($action, MessageSlot::ModalCancelActionLabel));
        $action->modalSubmitActionLabel(fn (): ?string => $this->boundText($action, MessageSlot::ModalSubmitActionLabel));
    }

    private function bindColumn(Column $column): void
    {
        $captured = $column->getLabel();

        $column->label(function () use ($column, $captured): mixed {
            return $this->boundText($column, MessageSlot::Label) ?? $captured;
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
            fn (): ?string => $this->boundText($column, MessageSlot::Prefix),
        );
    }

    private function bindFilterLabel(BaseFilter $filter): void
    {
        $captured = $filter->getLabel();

        $filter->label(function () use ($filter, $captured): mixed {
            return $this->boundText($filter, MessageSlot::Label) ?? $captured;
        });
    }

    private function bindFilterIndicator(BaseFilter $filter): void
    {
        $filter->indicator(fn (): ?string => $this->boundText($filter, MessageSlot::Indicator));
    }

    private function bindFieldset(Fieldset $fieldset): void
    {
        if ($fieldset->hasCustomLabel()) {
            return;
        }

        $fieldset->label(fn (): ?string => $this->boundPresentText($fieldset, MessageSlot::Label));
    }

    private function bindNamedChrome(Field|Entry $component): void
    {
        if (! $component->hasCustomLabel()) {
            $component->label(fn (): ?string => $this->boundText($component, MessageSlot::Label));
        }

        $component->helperText(fn (): ?string => $this->boundText($component, MessageSlot::HelperText));

        if (! $component->hasHint()) {
            $component->hint(fn (): ?string => $this->boundText($component, MessageSlot::Hint));
        }

        $this->bindFieldPlaceholder($component);
        $this->bindFieldChromeContent($component);
        $this->bindFieldValidationAttribute($component);
    }

    /**
     * The name a validation message calls this field.
     *
     * Optional, like helper text: with no line in the catalog the closure
     * returns null and Filament falls back to its own default, which is the
     * label lowercased. Write the key only where the two differ — "email
     * address" against a label of "Email".
     *
     * Entries are not validated, so this is for fields alone.
     */
    private function bindFieldValidationAttribute(Field|Entry $component): void
    {
        if (! $component instanceof Field) {
            return;
        }

        $component->validationAttribute(
            fn (): ?string => $this->boundPresentText($component, MessageSlot::ValidationAttribute),
        );
    }

    private function bindFieldPlaceholder(Field|Entry $component): void
    {
        if (! method_exists($component, 'placeholder')) {
            return;
        }

        call_user_func(
            [$component, 'placeholder'],
            fn (): ?string => $this->boundText($component, MessageSlot::Placeholder),
        );
    }

    private function bindFieldChromeContent(Field|Entry $component): void
    {
        $component->beforeContent(fn (): ?string => $this->boundText($component, MessageSlot::BeforeContent));
        $component->afterContent(fn (): ?string => $this->boundText($component, MessageSlot::AfterContent));
        $component->belowLabel(fn (): ?string => $this->boundText($component, MessageSlot::BelowLabel));
    }

    /**
     * Components this package binds through a dedicated hook.
     *
     * The generic hook below runs for every schema component, so it has to
     * step aside for the ones already handled — otherwise a field would be
     * bound twice, the second call overwriting the first.
     *
     * @var array<int, class-string>
     */
    private const DEDICATED_BINDINGS = [
        Field::class,
        Entry::class,
        Section::class,
        Fieldset::class,
        Wizard::class,
        Step::class,
        Tabs::class,
        Tab::class,
        EmptyState::class,
        Callout::class,
        SchemaText::class,
    ];

    /**
     * A component outside Filament's own set, named with ->key().
     *
     * Filament is extensible, and a package's component — a separator, a
     * divider, anything using HasLabel — was invisible here: its copy could
     * not come from the catalog however it was written. Identity already
     * worked, since leafName() falls through to the machine key; only the
     * binding was missing.
     *
     * Filament's own schema components are never handled here. Dedicated
     * hooks cover the ones that need a catalog slot; layout wrappers such as
     * `Actions` (a keyed footer container on SettingsPage, for example) use
     * HasLabel for an optional chrome line and must not invent a required
     * `form.components.{key}.label`.
     *
     * The key is required rather than inferred. A component with no key has
     * no identity, and guessing one from the make() argument would be wrong:
     * on Separator that argument is the visible label, not a name.
     */
    public function bindKeyedComponentLabel(SchemaComponent $component): void
    {
        foreach (self::DEDICATED_BINDINGS as $dedicated) {
            if ($component instanceof $dedicated) {
                return;
            }
        }

        if (str_starts_with($component::class, 'Filament\\')) {
            return;
        }

        // the key is set after make(), so it cannot be checked here; the closure
        // below resolves nothing when there is still no key at render time
        if (! method_exists($component, 'label') || ! method_exists($component, 'getLabel')) {
            return;
        }

        // hasCustomLabel() is no guide outside Filament's own set: a component
        // whose make() takes the label has one before anyone sets it. So the
        // label present here is captured and kept as the fallback, the way
        // steps and tabs are handled, and an explicit ->label() after make()
        // still wins by overwriting this closure.
        $captured = $component->getLabel();

        $component->label(function () use ($component, $captured): mixed {
            $text = $this->boundText($component, MessageSlot::Label);

            return $text ?? $captured;
        });
    }

    private function bindMakeArgumentLabel(Step|Tab|Tabs $component): void
    {
        $captured = $component->getLabel();
        $this->assignMachineKeyFromCaptured($component, $captured);

        $component->label(function () use ($component, $captured): mixed {
            $text = $this->boundText($component, MessageSlot::Label);

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
            $text = $this->boundText($notification, MessageSlot::Title);

            if ($text !== null) {
                return $text;
            }

            return $capturedTitle;
        });

        $notification->body(function () use ($notification, $capturedBody): mixed {
            $text = $this->boundText($notification, MessageSlot::Body);

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
            $text = $this->boundText($component, MessageSlot::Heading);

            if ($text !== null) {
                return $text;
            }

            return $captured;
        });

        $component->description(fn (): ?string => $this->boundText($component, MessageSlot::Description));
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
            $text = $this->boundText($component, MessageSlot::Heading);

            if ($text !== null) {
                return $text;
            }

            return $captured;
        });

        $component->description(fn (): ?string => $this->boundText($component, MessageSlot::Description));
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

            $text = $this->boundContent($component, MessageSlot::Body);

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

                return $this->boundText($component, MessageSlot::Tooltip);
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
     * @return array{0: Field|Entry, 1: MessageSlot}|null
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
     * @return array<string, MessageSlot>
     */
    private function fieldChromeSlots(): array
    {
        return [
            'before_content' => MessageSlot::BeforeContent,
            'after_content' => MessageSlot::AfterContent,
            'below_label' => MessageSlot::BelowLabel,
        ];
    }

    public function resolveIdentity(MessageIdentity $identity): Resolution
    {
        return $this->resolver->resolve($identity);
    }

    /**
     * @return array{0: MessageSurface, 1: array<int, string>}
     */
    private function scopeAndPath(object $component): array
    {
        if ($component instanceof Notification) {
            $action = $this->notificationAction($component);

            if (! $action instanceof Action) {
                return [MessageSurface::Form, ['notifications']];
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
            return [MessageSurface::Table, ['filters']];
        }

        if ($component instanceof Column) {
            return [MessageSurface::Table, ['columns']];
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
                return [MessageSurface::Table, [$this->tableActionSegment($component, $table)]];
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
                return [MessageSurface::Pages, $this->pageActionPath($livewire)];
            }

            return [MessageSurface::Actions, []];
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

        return [MessageSurface::Form, []];
    }

    private function formScope(object $component): MessageSurface
    {
        if ($component instanceof Entry) {
            return MessageSurface::Infolist;
        }

        if ($component instanceof Action) {
            $owner = $this->relatedFieldOf($component);

            if ($owner instanceof Entry) {
                return MessageSurface::Infolist;
            }

            if ($owner instanceof Field) {
                return MessageSurface::Form;
            }

            $parent = $this->parentComponentOf($this->schemaContainerOf($component));

            if ($parent instanceof SchemaComponent) {
                return $this->formScope($parent);
            }
        }

        if ($component instanceof SchemaComponent && $this->schemaContainsEntry($component)) {
            return MessageSurface::Infolist;
        }

        return MessageSurface::Form;
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
        $parent = $this->parentComponentOf($this->schemaContainerOf($action));

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
        $crossed = [];
        $maxDepth = (int) config('translations.max_parent_depth', 32);

        while ($depth < $maxDepth) {
            $depth++;
            $parent = $this->parentSchemaComponent($current);

            if ($parent === null) {
                break;
            }

            if ($parent instanceof EmbeddedSchema) {
                $node = spl_object_id($parent);

                if (isset($crossed[$node])) {
                    break;
                }

                $crossed[$node] = true;
            }

            if ($this->bindings->domain($parent) !== null) {
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
            $container = $component->getContainer();
        } catch (Throwable) {
            return null;
        }

        return $this->parentComponentOf($container);
    }

    /**
     * The component a schema hangs from.
     *
     * A schema embedded in another one has no parent component of its own:
     * it is a schema on the Livewire component, reached by name. The node
     * that embeds it stands in for the missing parent, so a keyed wrapper
     * lends its segment to the components inside, exactly as it does to the
     * components beside them.
     */
    private function parentComponentOf(mixed $container): ?SchemaComponent
    {
        if (! $container instanceof Schema) {
            return null;
        }

        try {
            $parent = $container->getParentComponent();
        } catch (Throwable) {
            $parent = null;
        }

        if ($parent instanceof SchemaComponent) {
            return $parent;
        }

        return $this->embeddingComponent($container);
    }

    /**
     * The node embedding this schema, if one does.
     *
     * Reading the name off the container can evaluate a closure, which can
     * ask for a message and land back here, so the lookup refuses to nest.
     */
    private function embeddingComponent(Schema $container): ?EmbeddedSchema
    {
        if ($this->resolvingEmbeddedParent) {
            return null;
        }

        $this->resolvingEmbeddedParent = true;

        try {
            $name = $container->getKey(isAbsolute: false);

            if (! is_string($name)) {
                return null;
            }

            return $this->embeds->embedding($name, $container->getLivewire());
        } catch (Throwable) {
            return null;
        } finally {
            $this->resolvingEmbeddedParent = false;
        }
    }

    private function layoutName(SchemaComponent $component): ?string
    {
        $message = $this->bindings->messageName($component);

        if ($message !== null) {
            return NameNormalizer::machine($message);
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
        $message = $this->bindings->messageName($component);

        if ($message !== null) {
            return NameNormalizer::machine($message);
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
        $override = $this->bindings->domain($component);

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
        $maxDepth = (int) config('translations.max_parent_depth', 32);

        while (is_object($current) && $depth < $maxDepth) {
            $override = $this->bindings->domain($current);

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
            $livewireCatalog = $this->bindings->domain($livewire);

            if ($livewireCatalog !== null) {
                return $livewireCatalog;
            }
        }

        if (! is_object($livewire)) {
            return null;
        }

        return $this->domains->for($livewire);
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
        $maxDepth = (int) config('translations.max_parent_depth', 32);

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
