<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Messages;

use Syriable\Filament\Plugins\AutoTranslator\Domains\DomainName;
use Syriable\Filament\Plugins\AutoTranslator\Enums\Chrome;
use Syriable\Filament\Plugins\AutoTranslator\Enums\MessageScope;
use Syriable\Filament\Plugins\AutoTranslator\Enums\MessageSlot;
use Syriable\Filament\Plugins\AutoTranslator\Exceptions\InvalidMessageNameException;

/**
 * Where one message lives: its domain, scope, parent machine names, leaf name
 * and slot. Everything else — the Laravel key, the file, the nested array —
 * is derived from this.
 */
final readonly class MessageIdentity
{
    /**
     * @param  list<string>  $path  machine names of the parents, with their structural segments
     * @param  string  $relative  a dotted sub-key replacing the slot, e.g. `options.admin`
     */
    public function __construct(
        public string $domain,
        public MessageScope $scope,
        public array $path,
        public string $name,
        public MessageSlot $slot,
        public string $relative = '',
    ) {}

    /**
     * The Laravel translation key, e.g.
     * `filament/user-resource.form.components.email.label`.
     *
     * @throws InvalidMessageNameException when a segment cannot live in a key
     */
    public function key(): string
    {
        $segments = array_map(
            static function (string $segment): string {
                $segment = MachineName::normalize($segment);

                if (! MachineName::isValid($segment)) {
                    throw InvalidMessageNameException::forName($segment);
                }

                return $segment;
            },
            $this->chromeSegments() ?? $this->nestedSegments(),
        );

        return DomainName::group($this->domain).'.'.implode('.', $segments);
    }

    /**
     * Resource, cluster and page chrome is named after Filament's own method
     * and sits at the root of the file, or under `pages.{page}` on a resource.
     *
     * @return list<string>|null
     */
    private function chromeSegments(): ?array
    {
        if ($this->name !== '' || $this->relative !== '') {
            return null;
        }

        $chrome = Chrome::tryFor($this->scope, $this->slot);

        return match (true) {
            $chrome === null => null,
            $this->path === [] => [$chrome->key()],
            $this->scope === MessageScope::Pages && count($this->path) === 1 => ['pages', $this->path[0], $chrome->key()],
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private function nestedSegments(): array
    {
        $segments = [$this->scope->value];

        if ($this->scope->nestsComponents()) {
            $segments[] = 'components';
        }

        $segments = [...$segments, ...$this->path];

        if ($this->name !== '') {
            $segments[] = $this->name;
        }

        if ($this->relative === '') {
            $segments[] = $this->slot->value;

            return $segments;
        }

        return [...$segments, ...explode('.', $this->relative)];
    }

    public function withSlot(MessageSlot $slot, string $relative = ''): self
    {
        return new self($this->domain, $this->scope, $this->path, $this->name, $slot, $relative);
    }

    /**
     * Identifies the resolution for the per-request cache.
     */
    public function cacheKey(string $locale): string
    {
        return implode('|', [
            $this->domain,
            $this->scope->value,
            implode('.', $this->path),
            $this->name,
            $this->slot->value,
            $this->relative,
            $locale,
        ]);
    }
}
