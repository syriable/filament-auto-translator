<?php

declare(strict_types=1);

use Syriable\Translation\Discovery\DomainRegistry;
use Syriable\Translation\Extraction\MessageScanner;

/**
 * Pins the compiled key surface.
 *
 * Key derivation is the package's contract with every application that has
 * already translated its copy: if a key changes, the old translation silently
 * stops resolving. Refactoring must never move a key, so this snapshot is
 * compared byte for byte and is only ever updated deliberately.
 */
function goldenKeys(): array
{
    app(DomainRegistry::class)->discover(
        in: dirname(__DIR__).'/Fixtures/Schemas',
        for: 'Syriable\\Translation\\Tests\\Fixtures\\Schemas',
    );

    $keys = array_column(app(MessageScanner::class)->audit('en'), 'key');

    sort($keys);

    return array_values(array_unique($keys));
}

it('compiles exactly the keys recorded in the snapshot', function () {
    $snapshot = dirname(__DIR__).'/Snapshots/compiled-keys.txt';

    expect(file_exists($snapshot))->toBeTrue(
        'Compiled key snapshot is missing. It is generated deliberately, never on the fly.'
    );

    $expected = array_values(array_filter(explode("\n", trim((string) file_get_contents($snapshot)))));

    expect(goldenKeys())->toBe($expected);
});
