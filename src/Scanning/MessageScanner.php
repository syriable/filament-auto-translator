<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Scanning;

use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
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
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Syriable\Filament\Plugins\AutoTranslator\Binding\ComponentBinder;
use Syriable\Filament\Plugins\AutoTranslator\Binding\ComponentIdentifier;
use Syriable\Filament\Plugins\AutoTranslator\Domains\DomainName;
use Syriable\Filament\Plugins\AutoTranslator\Enums\MessageScope;
use Syriable\Filament\Plugins\AutoTranslator\Enums\MessageSlot;
use Syriable\Filament\Plugins\AutoTranslator\Enums\ResolutionOutcome;
use Syriable\Filament\Plugins\AutoTranslator\Messages\MessageIdentity;
use Syriable\Filament\Plugins\AutoTranslator\Messages\MessageResolver;
use Syriable\Filament\Plugins\AutoTranslator\Messages\Resolution;
use Throwable;

/**
 * Resolves every message the registered UI renders, without a browser.
 *
 * It reports what the current locale lacks (findings) and what it proved is
 * live (coverage), which is what extraction writes and prunes from.
 */
final class MessageScanner
{
    private const array UNPRUNED_SCOPES = [MessageScope::Pages, MessageScope::Model, MessageScope::Navigation, MessageScope::Cluster];

    /**
     * @var list<Finding>
     */
    private array $findings = [];

    /**
     * @var array<string, array<string, true>>
     */
    private array $livePrefixes = [];

    public function __construct(
        private readonly SurfaceCollector $surfaces,
        private readonly ComponentIdentifier $identifier,
        private readonly ComponentBinder $binder,
        private readonly ActionNotifications $notifications,
    ) {}

    /**
     * Scans every registered surface in the given locale, or the current one.
     */
    public function scan(?string $locale = null): ScanResult
    {
        $this->binder->register();
        $original = app()->getLocale();

        if ($locale !== null) {
            app()->setLocale($locale);
        }

        try {
            return $this->scanSurfaces($this->surfaces->collect());
        } finally {
            app()->setLocale($original);
        }
    }

    /**
     * Scans components you built yourself, such as a schema in a test.
     *
     * Name the scopes the components describe completely to let extraction
     * prune keys in them that the components do not use.
     *
     * @param  array<array-key, mixed>  $components
     * @param  list<string>  $completeScopes  e.g. `['form']`
     */
    public function scanComponents(array $components, string $domain = '', array $completeScopes = []): ScanResult
    {
        $this->binder->register();

        return $this->scanSurfaces([new Surface($domain, components: $this->surfaces->flatten($components), builtScopes: $completeScopes)]);
    }

    /**
     * Resolves identities you name directly; every one not bound in the
     * current locale is a finding.
     *
     * @param  list<MessageIdentity>  $identities
     * @return list<Finding>
     */
    public function scanIdentities(array $identities): array
    {
        $resolver = app(MessageResolver::class);
        $findings = [];

        foreach ($identities as $identity) {
            $resolution = $resolver->resolve($identity);

            if ($resolution->outcome !== ResolutionOutcome::Bound) {
                $findings[] = Finding::from($resolution);
            }
        }

        return $findings;
    }

    /**
     * @param  list<Surface>  $surfaces
     */
    private function scanSurfaces(array $surfaces): ScanResult
    {
        $this->findings = [];
        $this->livePrefixes = [];
        $pages = $built = $failed = [];

        foreach ($surfaces as $surface) {
            foreach ($surface->chrome as $chrome) {
                if ($chrome->chrome->isRequired()) {
                    $this->record(app(MessageResolver::class)->resolve($chrome->identity()));
                }
            }

            foreach ($surface->components as $component) {
                $this->scanComponent($component);
            }

            foreach ($surface->pages as $page) {
                $pages[$surface->domain][$page] = true;
            }

            foreach ($surface->builtScopes as $scope) {
                $built[$surface->domain][$scope] = true;
            }

            foreach ($surface->failedScopes as $scope) {
                $failed[$surface->domain][$scope] = true;
            }
        }

        $coverage = [];

        foreach (array_unique([...array_keys($built), ...array_keys($this->livePrefixes)]) as $domain) {
            $coverage[$domain] = new Coverage(
                livePrefixes: $this->livePrefixes[$domain] ?? [],
                livePages: $pages[$domain] ?? [],
                builtScopes: array_diff_key($built[$domain] ?? [], $failed[$domain] ?? []),
            );
        }

        return new ScanResult($this->findings, $coverage);
    }

    private function scanComponent(object $component): void
    {
        foreach ($this->requiredSlotsOf($component) as $slot) {
            $this->record($this->identifier->resolve($component, $slot));
        }

        // optional layout copy is live but never reported missing
        foreach ($this->optionalSlotsOf($component) as $slot) {
            $this->markLive($this->identifier->resolve($component, $slot));
        }

        if ($component instanceof Field || $component instanceof Entry) {
            foreach (MessageSlot::fieldContent() as $slot) {
                if ($this->rendersContent($component, $slot)) {
                    $this->record($this->identifier->resolve($component, $slot));
                }
            }
        }

        if ($component instanceof Action) {
            foreach ($this->notifications->of($component) as $notification) {
                $this->record($this->identifier->resolve($notification, MessageSlot::Title));
            }
        }
    }

    /**
     * @return list<MessageSlot>
     */
    private function requiredSlotsOf(object $component): array
    {
        return match (true) {
            $component instanceof Field, $component instanceof Entry, $component instanceof Step,
            $component instanceof Tab, $component instanceof Tabs, $component instanceof Action,
            $component instanceof Column, $component instanceof BaseFilter => [MessageSlot::Label],
            $component instanceof EmptyState, $component instanceof Callout => [MessageSlot::Heading],
            $component instanceof Text => [MessageSlot::Body],
            $this->isKeyedThirdParty($component) => [MessageSlot::Label],
            default => [],
        };
    }

    /**
     * @return list<MessageSlot>
     */
    private function optionalSlotsOf(object $component): array
    {
        return match (true) {
            $component instanceof Fieldset, $component instanceof Wizard => [MessageSlot::Label],
            $component instanceof Section => [MessageSlot::Heading],
            default => [],
        };
    }

    private function isKeyedThirdParty(object $component): bool
    {
        return $component instanceof SchemaComponent
            && ComponentBinder::isThirdParty($component)
            && method_exists($component, 'getLabel')
            && filled($component->getKey(isAbsolute: false));
    }

    private function rendersContent(Field|Entry $field, MessageSlot $slot): bool
    {
        try {
            return ($field->getChildSchema($slot->value)?->getComponents() ?? []) !== [];
        } catch (Throwable) {
            return false;
        }
    }

    private function record(Resolution $resolution): void
    {
        $this->markLive($resolution);

        if (! in_array($resolution->outcome, [ResolutionOutcome::Bound, ResolutionOutcome::NoDomain, ResolutionOutcome::Unbound], true)) {
            $this->findings[] = Finding::from($resolution);
        }
    }

    /**
     * Records the component's key, less its slot, as live, so extraction keeps
     * it and every optional slot beside it.
     */
    private function markLive(Resolution $resolution): void
    {
        $identity = $resolution->identity;

        // chrome is never pruned, and page keys live as long as their page
        if ($resolution->key === '' || ! $resolution->outcome->hasKey() || in_array($identity->scope, self::UNPRUNED_SCOPES, true)) {
            return;
        }

        $segments = DomainName::segmentsOf($identity->domain, $resolution->key);

        if (MessageSlot::tryFrom((string) end($segments)) !== null) {
            array_pop($segments);
        }

        if ($segments !== []) {
            $this->livePrefixes[$identity->domain][implode('.', $segments)] = true;
        }
    }
}
