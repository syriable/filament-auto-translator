<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Binding;

use Closure;
use Filament\Actions\Action;
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
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Components\Component as SupportComponent;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\HtmlString;
use Illuminate\Translation\Translator;
use Syriable\FilamentAutoTranslator\Enums\MessageSlot;
use Syriable\FilamentAutoTranslator\Messages\MachineName;
use Syriable\FilamentAutoTranslator\Messages\Resolution;
use Throwable;

/**
 * Hooks Filament so every component fills its unset copy from the language
 * files.
 *
 * Each hook runs from `configureUsing()`, right after `make()`, and installs
 * a closure for the slot. Filament evaluates the closure when it renders, by
 * which time the component knows its parents and its Livewire owner. A setter
 * called after `make()` replaces the closure, which is why explicit copy in
 * PHP always wins.
 */
final class ComponentBinder
{
    /**
     * Schema components bound by a dedicated hook below. The generic hook for
     * third-party components steps aside for them.
     *
     * @var list<class-string>
     */
    private const array DEDICATED = [
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
        Text::class,
    ];

    private bool $registered = false;

    public function __construct(
        private readonly ComponentIdentifier $identifier,
        private readonly MessageOptions $options,
        private readonly Translator $translator,
    ) {}

    /**
     * Registers the macros and hooks. Safe to call more than once.
     */
    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        $this->registerMacros();
        $this->registerHooks();

        // Filament dispatches these by class name with the action as the
        // payload; an event object is accepted too
        Event::listen(ActionCalling::class, function (Action|ActionCalling $event): void {
            $this->identifier->enterAction($event instanceof Action ? $event : $event->getAction());
        });
        Event::listen(ActionCalled::class, fn () => $this->identifier->leaveAction());
    }

    /**
     * A schema component from another package — a separator, a divider —
     * bound by its `->key()` when it carries a label.
     *
     * Filament's own components are excluded: layout wrappers such as
     * `Actions` are keyed for Livewire identity only and must not invent a
     * required `….label`.
     */
    public static function isThirdParty(object $component): bool
    {
        if (! $component instanceof SchemaComponent || str_starts_with($component::class, 'Filament\\')) {
            return false;
        }

        foreach (self::DEDICATED as $dedicated) {
            if ($component instanceof $dedicated) {
                return false;
            }
        }

        return true;
    }

    /**
     * The slot's copy with the component's replacements applied, or null.
     */
    public function text(object $component, MessageSlot $slot): ?string
    {
        return $this->render($component, $this->identifier->resolve($component, $slot));
    }

    private function registerMacros(): void
    {
        $options = $this->options;

        SupportComponent::macro('messageName', function (string $name) use ($options): static {
            $options->setName($this, $name);

            return $this;
        });

        SupportComponent::macro('messageDomain', function (string $domain) use ($options): static {
            $options->setDomain($this, $domain);

            return $this;
        });

        SupportComponent::macro('messageReplace', function (array|Closure $replace) use ($options): static {
            /** @var array<string, mixed>|Closure $replace */
            $options->addReplacements($this, $replace);

            return $this;
        });

        SupportComponent::macro('messageHtml', function (bool $condition = true) use ($options): static {
            $options->setHtml($this, $condition);

            return $this;
        });
    }

    private function registerHooks(): void
    {
        Field::configureUsing(fn (Field $field) => $this->bindField($field));
        Entry::configureUsing(fn (Entry $entry) => $this->bindField($entry));
        Section::configureUsing(fn (Section $section) => $this->bindSection($section));
        Fieldset::configureUsing(fn (Fieldset $fieldset) => $this->bindFieldset($fieldset));
        Wizard::configureUsing(fn (Wizard $wizard) => $this->bindWizard($wizard));
        Step::configureUsing(fn (Step $step) => $this->bindNamedLabel($step));
        Tabs::configureUsing(fn (Tabs $tabs) => $this->bindNamedLabel($tabs));
        Tab::configureUsing(fn (Tab $tab) => $this->bindNamedLabel($tab));
        EmptyState::configureUsing(fn (EmptyState $emptyState) => $this->bindEmptyState($emptyState));
        Callout::configureUsing(fn (Callout $callout) => $this->bindCallout($callout));
        Text::configureUsing(fn (Text $text) => $this->bindText($text));
        SchemaComponent::configureUsing(fn (SchemaComponent $component) => $this->bindKeyedLabel($component));
        Action::configureUsing(fn (Action $action) => $this->bindAction($action));
        Column::configureUsing(fn (Column $column) => $this->bindColumn($column));
        BaseFilter::configureUsing(fn (BaseFilter $filter) => $this->bindFilter($filter));
        Notification::configureUsing(fn (Notification $notification) => $this->bindNotification($notification));
    }

    // -------------------------------------------------------------------------
    // Hooks
    // -------------------------------------------------------------------------

    private function bindField(Field|Entry $field): void
    {
        if (! $field->hasCustomLabel()) {
            $field->label(fn (): ?string => $this->text($field, MessageSlot::Label));
        }

        if (! $field->hasHint()) {
            $field->hint(fn (): ?string => $this->text($field, MessageSlot::Hint));
        }

        $field->helperText(fn (): ?string => $this->text($field, MessageSlot::HelperText));

        // a one-argument ->hintIcon($icon) keeps this; a second argument or
        // ->hintIconTooltip() after make() replaces it
        $field->hintIconTooltip(fn (): ?string => $this->text($field, MessageSlot::HintIconTooltip));

        $field->beforeContent(fn (): ?string => $this->text($field, MessageSlot::BeforeContent));
        $field->afterContent(fn (): ?string => $this->text($field, MessageSlot::AfterContent));
        $field->belowLabel(fn (): ?string => $this->text($field, MessageSlot::BelowLabel));

        if (method_exists($field, 'placeholder')) {
            $field->placeholder(fn (): ?string => $this->text($field, MessageSlot::Placeholder));
        }

        // with no line, Filament keeps its default: the label, lowercased
        if ($field instanceof Field) {
            $field->validationAttribute(fn (): ?string => $this->presentText($field, MessageSlot::ValidationAttribute));
        }
    }

    private function bindSection(Section $section): void
    {
        if (! filled($section->getHeading())) {
            $section->heading(fn (): ?string => $this->text($section, MessageSlot::Heading));
        }

        if (! filled($section->getDescription())) {
            $section->description(fn (): ?string => $this->text($section, MessageSlot::Description));
        }
    }

    private function bindFieldset(Fieldset $fieldset): void
    {
        // optional: a fieldset with no line stays untitled
        if (! $fieldset->hasCustomLabel()) {
            $fieldset->label(fn (): ?string => $this->presentText($fieldset, MessageSlot::Label));
        }
    }

    private function bindWizard(Wizard $wizard): void
    {
        if (! $wizard->hasCustomLabel()) {
            $wizard->label(fn (): ?string => $this->text($wizard, MessageSlot::Label));
        }
    }

    /**
     * Steps and tabs take a machine name in `make()`; it becomes the key and
     * stays the fallback label.
     */
    private function bindNamedLabel(Step|Tab|Tabs $component): void
    {
        $captured = $component->getLabel();
        $this->keyFromArgument($component, $captured);

        $component->label(fn (): mixed => $this->text($component, MessageSlot::Label) ?? $captured);
    }

    private function bindEmptyState(EmptyState $emptyState): void
    {
        $captured = $emptyState->getHeading();
        $this->keyFromArgument($emptyState, $captured);

        $emptyState->heading(fn (): mixed => $this->text($emptyState, MessageSlot::Heading) ?? $captured);
        $emptyState->description(fn (): ?string => $this->text($emptyState, MessageSlot::Description));
    }

    private function bindCallout(Callout $callout): void
    {
        try {
            $captured = $callout->getHeading();
        } catch (Throwable) {
            return;
        }

        // a visible sentence in make() is copy, not a name, and is left alone
        if (! $this->keyFromArgument($callout, $captured)) {
            return;
        }

        $callout->heading(fn (): mixed => $this->text($callout, MessageSlot::Heading) ?? $captured);
        $callout->description(fn (): ?string => $this->text($callout, MessageSlot::Description));
    }

    private function bindText(Text $text): void
    {
        try {
            $captured = $text->getContent();
        } catch (Throwable) {
            return;
        }

        if (! $this->keyFromArgument($text, $captured)) {
            return;
        }

        $text->content(function () use ($text, $captured): mixed {
            // a field's before/after/below-label text renders the field's slot
            if (($owner = $this->identifier->fieldContentOwner($text)) !== null) {
                return $this->text(...$owner);
            }

            return $this->markup($text, $this->text($text, MessageSlot::Body)) ?? $captured;
        });

        if (! $text->hasTooltip()) {
            $text->tooltip(fn (): ?string => $this->identifier->fieldContentOwner($text) === null
                ? $this->text($text, MessageSlot::Tooltip)
                : null);
        }
    }

    private function bindKeyedLabel(SchemaComponent $component): void
    {
        // the key is set after make(), so it cannot be checked here; with no
        // key at render time the closure resolves nothing
        if (! self::isThirdParty($component) || ! method_exists($component, 'label') || ! method_exists($component, 'getLabel')) {
            return;
        }

        // hasCustomLabel() is no guide outside Filament: a component whose
        // make() takes the label has one before anyone sets it, so it is kept
        // as the fallback
        $captured = $component->getLabel();

        $component->label(fn (): mixed => $this->text($component, MessageSlot::Label) ?? $captured);
    }

    private function bindAction(Action $action): void
    {
        // a vendor action such as DeleteAction keeps Filament's label until
        // the domain defines its own
        $captured = $action->getLabel();

        $action->label(fn (): mixed => $this->text($action, MessageSlot::Label) ?? $captured);
        $action->modalHeading(fn (): ?string => $this->text($action, MessageSlot::ModalHeading));
        $action->modalDescription(fn (): ?string => $this->text($action, MessageSlot::ModalDescription));
        $action->modalCancelActionLabel(fn (): ?string => $this->text($action, MessageSlot::ModalCancelActionLabel));
        $action->modalSubmitActionLabel(fn (): ?string => $this->text($action, MessageSlot::ModalSubmitActionLabel));
    }

    private function bindColumn(Column $column): void
    {
        $captured = $column->getLabel();

        $column->label(fn (): mixed => $this->text($column, MessageSlot::Label) ?? $captured);

        if (method_exists($column, 'prefix')) {
            $column->prefix(fn (): ?string => $this->text($column, MessageSlot::Prefix));
        }
    }

    private function bindFilter(BaseFilter $filter): void
    {
        $captured = $filter->getLabel();

        $filter->label(fn (): mixed => $this->text($filter, MessageSlot::Label) ?? $captured);
        $filter->indicator(fn (): ?string => $this->text($filter, MessageSlot::Indicator));
    }

    private function bindNotification(Notification $notification): void
    {
        // the action being called now is the one sending this notification
        if (($action = $this->identifier->currentAction()) !== null) {
            $this->options->setOwner($notification, $action);
        }

        $capturedTitle = rescue(fn (): mixed => $notification->getTitle(), null, report: false);
        $capturedBody = rescue(fn (): mixed => $notification->getBody(), null, report: false);

        $notification->title(fn (): mixed => $this->text($notification, MessageSlot::Title) ?? $capturedTitle);
        $notification->body(fn (): mixed => $this->text($notification, MessageSlot::Body) ?? $capturedBody);
    }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    /**
     * Makes a valid `make()` argument the component's key, so it is its
     * machine name. Returns whether it did.
     */
    private function keyFromArgument(SchemaComponent $component, mixed $argument): bool
    {
        $name = MachineName::fromArgument($argument);

        if ($name === null) {
            return false;
        }

        $component->key($name, isInheritable: false);

        return true;
    }

    /**
     * Copy for an optional slot that should be null rather than a debug key
     * when missing.
     */
    private function presentText(object $component, MessageSlot $slot): ?string
    {
        $resolution = $this->identifier->resolve($component, $slot);

        return $resolution->isPresent() ? $this->render($component, $resolution) : null;
    }

    /**
     * The line with the component's replacements. The resolver caches by
     * identity and locale, which per-component replacements must not poison,
     * so the line is re-read here — only when the component declared any.
     */
    private function render(object $component, Resolution $resolution): ?string
    {
        if ($resolution->text === null || ! $resolution->isPresent()) {
            return $resolution->text;
        }

        $replace = $this->replacementsFor($component);

        if ($replace === []) {
            return $resolution->text;
        }

        $text = $this->translator->get($resolution->key, $replace, $resolution->locale);

        return is_string($text) ? $text : $resolution->text;
    }

    /**
     * Closures run when the slot renders, through Filament's evaluation, so a
     * URL or a count is current and the component itself can be injected.
     *
     * @return array<string, mixed>
     */
    private function replacementsFor(object $component): array
    {
        $replace = $this->evaluate($component, $this->options->replacements($component));

        if (! is_array($replace)) {
            return [];
        }

        $html = $this->options->isHtml($component);

        return array_map(function (mixed $value) use ($component, $html): mixed {
            $value = $this->evaluate($component, $value);

            if (! $html) {
                return $value;
            }

            // the line is markup, so what is poured into it is escaped unless
            // the caller hands over markup of its own
            return $value instanceof Htmlable ? $value->toHtml() : e(is_scalar($value) ? (string) $value : '');
        }, $replace);
    }

    private function evaluate(object $component, mixed $value): mixed
    {
        if (! $value instanceof Closure) {
            return $value;
        }

        return method_exists($component, 'evaluate') ? $component->evaluate($value) : $value();
    }

    /**
     * Filament escapes a string, which is what copy should be. A component
     * that declares its line is markup says so with `messageHtml()` — the
     * package never guesses from the line's contents, or one translator's
     * stray `<` would silently stop a whole domain being escaped.
     */
    private function markup(object $component, ?string $text): string|Htmlable|null
    {
        return $text !== null && $this->options->isHtml($component) ? new HtmlString($text) : $text;
    }
}
