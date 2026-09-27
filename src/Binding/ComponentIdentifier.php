<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Binding;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component as SchemaComponent;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Table;
use Livewire\Livewire;
use Syriable\Filament\Plugins\AutoTranslator\Domains\DomainResolver;
use Syriable\Filament\Plugins\AutoTranslator\Enums\MessageScope;
use Syriable\Filament\Plugins\AutoTranslator\Enums\MessageSlot;
use Syriable\Filament\Plugins\AutoTranslator\Enums\ResolutionOutcome;
use Syriable\Filament\Plugins\AutoTranslator\Exceptions\ParentDepthExceededException;
use Syriable\Filament\Plugins\AutoTranslator\Messages\MachineName;
use Syriable\Filament\Plugins\AutoTranslator\Messages\MessageIdentity;
use Syriable\Filament\Plugins\AutoTranslator\Messages\MessageResolver;
use Syriable\Filament\Plugins\AutoTranslator\Messages\Resolution;
use Syriable\Filament\Plugins\AutoTranslator\Settings;
use Throwable;

/**
 * Works out which message a component's slot is, and resolves it.
 *
 * Identity comes from the component tree, never from visible copy: the
 * domain from the Livewire owner (or a `messageDomain()` override), the path
 * from the machine names of keyed parents, the leaf from `make('name')`.
 *
 * Filament can throw from almost any getter on a component that is not
 * mounted yet, so every read of the tree is guarded and a failed read means
 * "no parent" rather than an error on the page.
 */
final class ComponentIdentifier
{
    /**
     * Components whose message is being resolved right now, so a closure
     * that reads its own heading while resolving cannot recurse forever.
     *
     * @var array<int, true>
     */
    private array $resolving = [];

    /**
     * Actions being called, innermost last: a notification sent inside one
     * belongs to it.
     *
     * @var list<Action>
     */
    private array $actions = [];

    private bool $locatingEmbeddingNode = false;

    public function __construct(
        private readonly MessageOptions $options,
        private readonly DomainResolver $domains,
        private readonly EmbeddedSchemaLocator $embeds,
        private readonly Settings $settings,
    ) {}

    public function resolve(object $component, MessageSlot $slot): Resolution
    {
        $id = spl_object_id($component);

        if (isset($this->resolving[$id])) {
            return Resolution::unresolved($this->anonymous('', $slot), ResolutionOutcome::Unbound, 'Skipped a recursive evaluation of the same component.');
        }

        $this->resolving[$id] = true;

        try {
            $identity = $this->identify($component, $slot);

            if ($identity instanceof Resolution) {
                return $identity;
            }

            // resolved lazily: the resolver's cache is scoped to a request,
            // while this object lives as long as the hooks that call it
            return app(MessageResolver::class)->resolve($identity);
        } finally {
            unset($this->resolving[$id]);
        }
    }

    public function enterAction(Action $action): void
    {
        $this->actions[] = $action;
    }

    public function leaveAction(): void
    {
        array_pop($this->actions);
    }

    /**
     * The action a notification built right now belongs to.
     */
    public function currentAction(): ?Action
    {
        return $this->actions[array_key_last($this->actions) ?? -1] ?? $this->mountedAction(null);
    }

    /**
     * The field and slot a Text renders for, when the Text is a field's
     * before/after/below-label content rather than copy of its own.
     *
     * @return array{0: Field|Entry, 1: MessageSlot}|null
     */
    public function fieldContentOwner(Text $text): ?array
    {
        try {
            $container = $text->getContainer();
            $field = $container->getParentComponent();
        } catch (Throwable) {
            return null;
        }

        if (! $field instanceof Field && ! $field instanceof Entry) {
            return null;
        }

        foreach (MessageSlot::fieldContent() as $slot) {
            try {
                if ($field->getChildSchema($slot->value) === $container) {
                    return [$field, $slot];
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    private function identify(object $component, MessageSlot $slot): MessageIdentity|Resolution
    {
        $domain = $this->domainOf($component);

        if ($domain === null) {
            return Resolution::unresolved($this->anonymous('', $slot), ResolutionOutcome::NoDomain, 'The owning Livewire component declares no translation domain.');
        }

        if ($component instanceof Notification && ($this->actionOf($component) === null || $this->statusOf($component) === null)) {
            return Resolution::unresolved($this->anonymous($domain, $slot), ResolutionOutcome::Unbound, 'The notification has no owning action or no status.');
        }

        [$scope, $path] = $this->scopeAndPath($component);
        $name = $this->leafName($component);

        // a section or page may be addressed by its path alone
        if ($name === null && $slot !== MessageSlot::Heading && $slot !== MessageSlot::Title) {
            return Resolution::unresolved(new MessageIdentity($domain, $scope, $path, '', $slot), ResolutionOutcome::Unbound, 'The component has no machine name.');
        }

        return new MessageIdentity($domain, $scope, $path, $name ?? '', $slot);
    }

    private function anonymous(string $domain, MessageSlot $slot): MessageIdentity
    {
        return new MessageIdentity($domain, MessageScope::Form, [], '', $slot);
    }

    // -------------------------------------------------------------------------
    // Domain
    // -------------------------------------------------------------------------

    private function domainOf(object $component): ?string
    {
        if ($component instanceof Notification && ($action = $this->actionOf($component)) !== null) {
            return $this->options->domain($component) ?? $this->domainOf($action);
        }

        // the nearest messageDomain() override up the tree wins
        $current = $component;

        for ($depth = 0; $current !== null && $depth < $this->settings->maxParentDepth(); $depth++) {
            if (($domain = $this->options->domain($current)) !== null) {
                return $domain;
            }

            $current = $current instanceof SchemaComponent ? $this->parentOf($current) : null;
        }

        $livewire = $this->livewireOf($component) ?? ($component instanceof Notification ? $this->currentLivewire() : null);

        if ($livewire === null) {
            return null;
        }

        return $this->options->domain($livewire) ?? $this->domains->for($livewire);
    }

    // -------------------------------------------------------------------------
    // Scope and path
    // -------------------------------------------------------------------------

    /**
     * @return array{0: MessageScope, 1: list<string>}
     */
    private function scopeAndPath(object $component): array
    {
        return match (true) {
            $component instanceof Notification => $this->notificationScopeAndPath($component),
            $component instanceof BaseFilter => [MessageScope::Table, ['filters']],
            $component instanceof Column => [MessageScope::Table, ['columns']],
            $component instanceof Action => $this->actionScopeAndPath($component),
            $component instanceof SchemaComponent => $this->schemaScopeAndPath($component),
            default => [MessageScope::Form, []],
        };
    }

    /**
     * `{action path}.{action}.notifications`, with the status as the leaf.
     *
     * @return array{0: MessageScope, 1: list<string>}
     */
    private function notificationScopeAndPath(Notification $notification): array
    {
        $action = $this->actionOf($notification);

        if ($action === null) {
            return [MessageScope::Form, ['notifications']];
        }

        return $this->beneath($action, 'notifications');
    }

    /**
     * @return array{0: MessageScope, 1: list<string>}
     */
    private function actionScopeAndPath(Action $action): array
    {
        if (($parent = $this->footerParentOf($action)) !== null) {
            return $this->beneath($parent, 'extra_modal_footer_actions');
        }

        $table = $action->getTable();

        if ($table instanceof Table) {
            return [MessageScope::Table, [$this->tableActionSegment($action, $table)]];
        }

        if (($field = $this->fieldOf($action)) !== null) {
            [$scope, $path] = $this->schemaScopeAndPath($field);
            $name = $this->leafName($field);

            return [$scope, [...$path, ...($name !== null && $name !== '' ? [$name] : []), 'actions']];
        }

        if ($this->schemaContainerOf($action) !== null) {
            return [$this->schemaScope($action), $this->schemaActionPath($action)];
        }

        $livewire = $this->livewireOf($action);

        // a resource page nests its header actions under `pages.{page}`; a
        // standalone page owns its whole file, so they sit at the root
        if ($livewire !== null && (method_exists($livewire, 'getResourcePageName') || method_exists($livewire, 'getResource'))) {
            return [MessageScope::Pages, [MachineName::ofClass($livewire::class), 'actions']];
        }

        return [MessageScope::Actions, []];
    }

    /**
     * @return array{0: MessageScope, 1: list<string>}
     */
    private function schemaScopeAndPath(SchemaComponent $component): array
    {
        $action = $this->modalActionOf($component);

        if ($action !== null) {
            [$scope, $path] = $this->beneath($action, 'schema');

            return [$scope, [...$path, 'components', ...$this->schemaPath($component)]];
        }

        return [$this->schemaScope($component), $this->schemaPath($component)];
    }

    /**
     * The owner's scope and path, extended by the owner's name and a segment.
     *
     * @return array{0: MessageScope, 1: list<string>}
     */
    private function beneath(Action $owner, string $segment): array
    {
        [$scope, $path] = $this->scopeAndPath($owner);
        $name = $this->leafName($owner);

        if ($name !== null && $name !== '') {
            $path[] = $name;
        }

        $path[] = $segment;

        return [$scope, $path];
    }

    private function schemaScope(object $component): MessageScope
    {
        if ($component instanceof Entry) {
            return MessageScope::Infolist;
        }

        if ($component instanceof Action) {
            $field = $this->fieldOf($component);

            if ($field !== null) {
                return $field instanceof Entry ? MessageScope::Infolist : MessageScope::Form;
            }

            $parent = $this->parentOfContainer($this->schemaContainerOf($component));

            return $parent !== null ? $this->schemaScope($parent) : MessageScope::Form;
        }

        if ($component instanceof SchemaComponent && $this->holdsEntries($component)) {
            return MessageScope::Infolist;
        }

        return MessageScope::Form;
    }

    private function holdsEntries(SchemaComponent $component): bool
    {
        try {
            $children = $component->getChildSchema()?->getComponents() ?? [];
        } catch (Throwable) {
            return false;
        }

        foreach ($children as $child) {
            if ($child instanceof Entry) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function schemaActionPath(Action $action): array
    {
        $parent = $this->parentOfContainer($this->schemaContainerOf($action));

        if ($parent === null) {
            return ['actions'];
        }

        $name = $this->layoutName($parent);

        return [...$this->schemaPath($parent), ...($name !== null && $name !== '' ? [$name, 'schema'] : []), 'actions'];
    }

    /**
     * `{parent}.schema.{child}.schema…` for every keyed parent up to the root
     * or up to a parent that switches domain.
     *
     * @return list<string>
     */
    private function schemaPath(SchemaComponent $component): array
    {
        $names = [];
        $crossed = [];
        $max = $this->settings->maxParentDepth();

        for ($depth = 0, $current = $component; ; $depth++) {
            if ($depth >= $max) {
                throw ParentDepthExceededException::atDepth($max);
            }

            $parent = $this->parentOf($current);

            if ($parent === null || $this->options->domain($parent) !== null) {
                break;
            }

            // an embedded schema can embed itself; crossing it twice is a loop
            if ($parent instanceof EmbeddedSchema) {
                if (isset($crossed[spl_object_id($parent)])) {
                    break;
                }

                $crossed[spl_object_id($parent)] = true;
            }

            $name = $this->layoutName($parent);

            if ($name !== null) {
                array_unshift($names, $name, 'schema');
            }

            $current = $parent;
        }

        return $names;
    }

    private function tableActionSegment(Action $action, Table $table): string
    {
        return match (true) {
            $this->holdsAction($table->getRecordActions(), $action) => 'record_actions',
            $this->holdsAction($table->getToolbarActions(), $action) => 'toolbar_actions',
            $this->holdsAction($table->getHeaderActions(), $action) => 'header_actions',
            $this->holdsAction($table->getEmptyStateActions(), $action) => 'empty_state_actions',
            default => 'actions',
        };
    }

    /**
     * @param  array<int|string, mixed>  $actions
     */
    private function holdsAction(array $actions, Action $needle): bool
    {
        foreach ($actions as $action) {
            if ($action === $needle || ($action instanceof ActionGroup && $this->holdsAction($action->getFlatActions(), $needle))) {
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Names
    // -------------------------------------------------------------------------

    private function leafName(object $component): ?string
    {
        $declared = $this->options->name($component);

        return match (true) {
            $declared !== null => MachineName::normalize($declared),
            $component instanceof Notification => $this->statusOf($component),
            $component instanceof Field, $component instanceof Entry, $component instanceof Column,
            $component instanceof Action, $component instanceof BaseFilter => MachineName::normalize((string) $component->getName()),
            $component instanceof Text => $this->machineKey($component) ?? $this->textName($component),
            $component instanceof SchemaComponent => $this->machineKey($component),
            default => null,
        };
    }

    private function layoutName(SchemaComponent $component): ?string
    {
        $declared = $this->options->name($component);

        return $declared !== null ? MachineName::normalize($declared) : $this->machineKey($component);
    }

    /**
     * A layout's `->key()`. Filament's own generated keys (`…::…`) and the
     * default `wizard` key are not machine names.
     */
    private function machineKey(SchemaComponent $component): ?string
    {
        $key = $component->getKey(isAbsolute: false);

        if (! is_string($key) || $key === '' || str_contains($key, '::')) {
            return null;
        }

        $name = MachineName::normalize($key);

        return $name === 'wizard' ? null : $name;
    }

    private function textName(Text $text): ?string
    {
        try {
            return MachineName::fromArgument($text->getContent());
        } catch (Throwable) {
            return null;
        }
    }

    private function statusOf(Notification $notification): ?string
    {
        try {
            return MachineName::fromArgument($notification->getStatus());
        } catch (Throwable) {
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // Tree navigation
    // -------------------------------------------------------------------------

    private function parentOf(SchemaComponent $component): ?SchemaComponent
    {
        try {
            $container = $component->getContainer();
        } catch (Throwable) {
            return null;
        }

        return $this->parentOfContainer($container);
    }

    /**
     * The component a schema hangs from, or the node embedding it when the
     * schema is a named schema of its own.
     */
    private function parentOfContainer(mixed $container): ?SchemaComponent
    {
        if (! $container instanceof Schema) {
            return null;
        }

        try {
            $parent = $container->getParentComponent();
        } catch (Throwable) {
            $parent = null;
        }

        return $parent instanceof SchemaComponent ? $parent : $this->embeddingNodeOf($container);
    }

    /**
     * Reading a schema's key can evaluate a closure that asks for a message and
     * lands back here, so the lookup refuses to nest.
     */
    private function embeddingNodeOf(Schema $container): ?EmbeddedSchema
    {
        if ($this->locatingEmbeddingNode) {
            return null;
        }

        $this->locatingEmbeddingNode = true;

        try {
            $name = $container->getKey(isAbsolute: false);

            return is_string($name) ? $this->embeds->find($name, $container->getLivewire()) : null;
        } catch (Throwable) {
            return null;
        } finally {
            $this->locatingEmbeddingNode = false;
        }
    }

    /**
     * The field a hint, prefix or suffix action belongs to.
     */
    private function fieldOf(Action $action): Field|Entry|null
    {
        try {
            $owner = $action->getSchemaComponent();
        } catch (Throwable) {
            return null;
        }

        return $owner instanceof Field || $owner instanceof Entry ? $owner : null;
    }

    private function schemaContainerOf(Action $action): mixed
    {
        try {
            return $action->getSchemaContainer();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * An extra modal footer action nests under the action whose modal it is in.
     */
    private function footerParentOf(Action $action): ?Action
    {
        $parent = $action->getParentAction();

        if (! $parent instanceof Action) {
            return null;
        }

        foreach ($parent->getExtraModalFooterActions() as $footer) {
            if ($footer === $action || ($footer instanceof Action && $footer->getName() === $action->getName())) {
                return $parent;
            }
        }

        return null;
    }

    /**
     * The mounted action whose modal schema holds this component.
     */
    private function modalActionOf(SchemaComponent $component): ?Action
    {
        $container = null;
        $current = $component;

        for ($depth = 0; $depth < $this->settings->maxParentDepth(); $depth++) {
            try {
                $container = $current->getContainer();
                $parent = $container->getParentComponent();
            } catch (Throwable) {
                break;
            }

            if (! $parent instanceof SchemaComponent) {
                break;
            }

            $current = $parent;
        }

        try {
            $key = $container instanceof Schema ? $container->getKey(isAbsolute: false) : null;
        } catch (Throwable) {
            return null;
        }

        if (! is_string($key) || ! str_starts_with(MachineName::normalize($key), 'mountedactionschema')) {
            return null;
        }

        return $this->mountedAction($component);
    }

    private function mountedAction(?object $component): ?Action
    {
        $livewire = ($component !== null ? $this->livewireOf($component) : null) ?? $this->currentLivewire();

        if ($livewire === null || ! method_exists($livewire, 'getMountedAction')) {
            return null;
        }

        try {
            $mounted = $livewire->getMountedAction();
        } catch (Throwable) {
            return null;
        }

        return $mounted instanceof Action ? $mounted : null;
    }

    private function actionOf(Notification $notification): ?Action
    {
        $owner = $this->options->owner($notification);

        return $owner instanceof Action ? $owner : $this->currentAction();
    }

    private function livewireOf(object $component): ?object
    {
        if (! method_exists($component, 'getLivewire')) {
            return null;
        }

        try {
            $livewire = $component->getLivewire();
        } catch (Throwable) {
            return null;
        }

        return is_object($livewire) ? $livewire : null;
    }

    private function currentLivewire(): ?object
    {
        try {
            $livewire = Livewire::current();
        } catch (Throwable) {
            return null;
        }

        return is_object($livewire) ? $livewire : null;
    }
}
