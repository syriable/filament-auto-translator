<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Binding;

use Closure;
use WeakMap;

/**
 * What a component declared about its messages through the `message*()`
 * macros, plus the action a notification was sent from.
 *
 * Kept in a WeakMap rather than as dynamic properties on Filament objects, so
 * the data dies with the component and Filament's classes stay untouched.
 */
final class MessageOptions
{
    /**
     * @var WeakMap<object, array{name: ?string, domain: ?string, owner: ?object, replace: array<string, mixed>|Closure, html: bool}>
     */
    private WeakMap $options;

    public function __construct()
    {
        $this->options = new WeakMap;
    }

    public function setName(object $component, string $name): void
    {
        $this->update($component, 'name', $name);
    }

    public function setDomain(object $component, string $domain): void
    {
        $this->update($component, 'domain', $domain);
    }

    public function setOwner(object $component, object $owner): void
    {
        $this->update($component, 'owner', $owner);
    }

    public function setHtml(object $component, bool $html): void
    {
        $this->update($component, 'html', $html);
    }

    /**
     * Arrays merge over earlier arrays; a closure replaces whatever was there.
     *
     * @param  array<string, mixed>|Closure  $replace
     */
    public function addReplacements(object $component, array|Closure $replace): void
    {
        $existing = $this->of($component)['replace'];

        $this->update($component, 'replace', $replace instanceof Closure || $existing instanceof Closure
            ? $replace
            : [...$existing, ...$replace]);
    }

    public function name(object $component): ?string
    {
        return $this->of($component)['name'];
    }

    public function domain(object $component): ?string
    {
        return $this->of($component)['domain'];
    }

    public function owner(object $component): ?object
    {
        return $this->of($component)['owner'];
    }

    public function isHtml(object $component): bool
    {
        return $this->of($component)['html'];
    }

    /**
     * @return array<string, mixed>|Closure
     */
    public function replacements(object $component): array|Closure
    {
        return $this->of($component)['replace'];
    }

    private function update(object $component, string $option, mixed $value): void
    {
        $options = $this->of($component);
        $options[$option] = $value;

        /** @var array{name: ?string, domain: ?string, owner: ?object, replace: array<string, mixed>|Closure, html: bool} $options */
        $this->options[$component] = $options;
    }

    /**
     * @return array{name: ?string, domain: ?string, owner: ?object, replace: array<string, mixed>|Closure, html: bool}
     */
    private function of(object $component): array
    {
        return $this->options[$component] ?? ['name' => null, 'domain' => null, 'owner' => null, 'replace' => [], 'html' => false];
    }
}
