<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Syriable\MessageCatalog\Enums\MissingMessagePolicy;
use Syriable\MessageCatalog\MessageCatalogPlugin;
use Syriable\MessageCatalog\Messages;

/**
 * The README makes concrete promises about the public surface. These check the
 * promises still hold, so the documentation cannot drift from the code silently.
 */
it('exposes the entry points the README documents', function (string $class, string $method) {
    expect(method_exists($class, $method))->toBeTrue("{$class}::{$method}() is documented but missing");
})->with([
    [Messages::class, 'discoverIn'],
    [Messages::class, 'domainFor'],
    [Messages::class, 'explain'],
    [Messages::class, 'slot'],
    [MessageCatalogPlugin::class, 'domainPrefixes'],
    [MessageCatalogPlugin::class, 'discoverIn'],
    [MessageCatalogPlugin::class, 'onMissing'],
]);

it('offers the policies the README lists', function () {
    expect(array_map(fn (MissingMessagePolicy $p) => $p->value, MissingMessagePolicy::cases()))
        ->toEqualCanonicalizing(['fallback', 'debug', 'strict']);
});

it('ships the config keys the README documents', function () {
    $config = require dirname(__DIR__, 2).'/config/messages.php';

    expect(array_keys($config))->toEqualCanonicalizing([
        'on_missing', 'default_domain_prefix', 'domain_prefixes',
        'discover_paths', 'debug_query', 'max_parent_depth',
    ]);
});

it('registers the commands the README documents', function () {
    $names = array_keys(app(Kernel::class)->all());

    expect($names)->toContain('messages:extract')
        ->toContain('messages:debug')
        ->toContain('messages:inline');
});
