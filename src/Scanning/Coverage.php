<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Scanning;

use Syriable\Filament\Plugins\AutoTranslator\Enums\MessageSlot;

/**
 * What a scan proved is live in one domain — the evidence extraction needs
 * before it may delete a key.
 */
final readonly class Coverage
{
    /**
     * Keys under these first segments are pruned only when their scope was
     * fully built; chrome at the root is never pruned.
     */
    private const array PRUNABLE_SCOPES = ['form', 'infolist', 'table'];

    /**
     * Segments that group components rather than name one. A live prefix is
     * searched for only up to the nearest of these.
     */
    private const array STRUCTURAL_SEGMENTS = [
        'actions', 'columns', 'components', 'empty_state_actions', 'extra_modal_footer_actions', 'filters',
        'form', 'header_actions', 'infolist', 'notifications', 'pages', 'record_actions', 'schema', 'table',
        'toolbar_actions',
    ];

    /**
     * @param  array<string, true>  $livePrefixes  key segments below the domain, without the slot
     * @param  array<string, true>  $livePages  resource page kebabs
     * @param  array<string, true>  $builtScopes  scopes built to completion with none failing
     */
    public function __construct(
        public array $livePrefixes,
        public array $livePages,
        public array $builtScopes,
    ) {}

    /**
     * Whether a key in this domain's language file belongs to nothing the
     * scan reached. Optional slots under a live component — a placeholder,
     * `options.*` — stay, because their component's prefix is live.
     *
     * @param  list<string>  $segments  the key below the domain
     */
    public function isOrphaned(array $segments): bool
    {
        $scope = $segments[0] ?? null;

        if ($scope === 'pages') {
            return isset($this->builtScopes['pages']) && ! isset($this->livePages[$segments[1] ?? '']);
        }

        if (! in_array($scope, self::PRUNABLE_SCOPES, true) || ! isset($this->builtScopes[$scope])) {
            return false;
        }

        return ! $this->hasLivePrefix($segments);
    }

    /**
     * @param  list<string>  $segments
     */
    private function hasLivePrefix(array $segments): bool
    {
        if (MessageSlot::tryFrom((string) end($segments)) !== null) {
            array_pop($segments);
        }

        while ($segments !== []) {
            if (isset($this->livePrefixes[implode('.', $segments)])) {
                return true;
            }

            $removed = array_pop($segments);

            if (in_array($removed, self::STRUCTURAL_SEGMENTS, true)) {
                return false;
            }
        }

        return false;
    }
}
