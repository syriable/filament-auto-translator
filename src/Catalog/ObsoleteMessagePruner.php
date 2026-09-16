<?php

declare(strict_types=1);

namespace Syriable\Translation\Catalog;

use Syriable\Translation\Enums\MessageSlot;

class ObsoleteMessagePruner
{
    /**
     * @var array<int, string>
     */
    private const STRUCTURAL_SEGMENTS = [
        'actions',
        'columns',
        'components',
        'empty_state_actions',
        'extra_modal_footer_actions',
        'filters',
        'form',
        'header_actions',
        'infolist',
        'notifications',
        'pages',
        'record_actions',
        'schema',
        'table',
        'toolbar_actions',
    ];

    /**
     * @param  array<int, string>  $segments
     * @param  array<string, true>  $livePrefixes
     * @param  array<string, true>  $livePages
     * @param  array<string, true>  $walkedScopes
     */
    public function isOrphan(array $segments, array $livePrefixes, array $livePages, array $walkedScopes): bool
    {
        if ($segments === []) {
            return false;
        }

        $root = $segments[0];

        if ($root === 'pages') {
            return $this->isOrphanPage($segments, $livePages, $walkedScopes);
        }

        if (! in_array($root, ['form', 'infolist', 'table'], true)) {
            return false;
        }

        if (! isset($walkedScopes[$root])) {
            return false;
        }

        return ! $this->hasLivePrefix($segments, $livePrefixes);
    }

    /**
     * @param  array<int, string>  $segments
     * @param  array<string, true>  $livePages
     * @param  array<string, true>  $walkedScopes
     */
    private function isOrphanPage(array $segments, array $livePages, array $walkedScopes): bool
    {
        if (! isset($walkedScopes['pages'])) {
            return false;
        }

        $page = $segments[1] ?? null;

        if (! is_string($page) || $page === '') {
            return true;
        }

        return ! isset($livePages[$page]);
    }

    /**
     * @param  array<int, string>  $segments
     * @param  array<string, true>  $livePrefixes
     */
    private function hasLivePrefix(array $segments, array $livePrefixes): bool
    {
        $candidate = $segments;
        $last = $candidate[array_key_last($candidate)] ?? null;

        if (is_string($last) && MessageSlot::tryFrom($last) instanceof MessageSlot) {
            array_pop($candidate);
        }

        while ($candidate !== []) {
            $prefix = implode('.', $candidate);

            if (isset($livePrefixes[$prefix])) {
                return true;
            }

            $removed = array_pop($candidate);

            if ($this->isStructural($removed) || $candidate === []) {
                return false;
            }
        }

        return false;
    }

    private function isStructural(string $segment): bool
    {
        return in_array($segment, self::STRUCTURAL_SEGMENTS, true);
    }
}
