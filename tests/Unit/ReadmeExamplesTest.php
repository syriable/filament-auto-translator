<?php

declare(strict_types=1);

use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\Console\Kernel;
use Syriable\Translation\Binding\MessageBinder;
use Syriable\Translation\Enums\MissingMessagePolicy;
use Syriable\Translation\TranslationPlugin;
use Syriable\Translation\Translations;

/**
 * The README makes concrete promises about the public surface. These check the
 * promises still hold, so the documentation cannot drift from the code silently.
 */
it('exposes the entry points the README documents', function (string $class, string $method) {
    expect(method_exists($class, $method))->toBeTrue("{$class}::{$method}() is documented but missing");
})->with([
    [Translations::class, 'discoverIn'],
    [Translations::class, 'domainFor'],
    [Translations::class, 'explain'],
    [Translations::class, 'slot'],
    [TranslationPlugin::class, 'domainPrefixes'],
    [TranslationPlugin::class, 'discoverIn'],
    [TranslationPlugin::class, 'onMissing'],
]);

it('offers the policies the README lists', function () {
    expect(array_map(fn (MissingMessagePolicy $p) => $p->value, MissingMessagePolicy::cases()))
        ->toEqualCanonicalizing(['keep_vendor_label', 'debug', 'strict']);
});

it('ships the config keys the README documents', function () {
    $config = require dirname(__DIR__, 2).'/config/translations.php';

    expect(array_keys($config))->toEqualCanonicalizing([
        'on_missing', 'default_domain_prefix', 'domain_prefixes',
        'discover_paths', 'module_path', 'max_parent_depth',
    ]);
});

it('registers the commands the README documents', function () {
    $names = array_keys(app(Kernel::class)->all());

    expect($names)->toContain('translations:extract')
        ->toContain('translations:debug')
        ->toContain('translations:inline');
});

it('imports only classes that exist in every README example', function () {
    $readme = (string) file_get_contents(dirname(__DIR__, 2).'/README.md');

    preg_match_all('/^use (Syriable\\\\Translation\\\\[A-Za-z0-9_\\\\]+);$/m', $readme, $matches);

    expect($matches[1])->not->toBeEmpty();

    foreach ($matches[1] as $class) {
        expect(class_exists($class) || trait_exists($class) || interface_exists($class) || enum_exists($class))
            ->toBeTrue("README imports {$class}, which does not exist");
    }
});

it('registers the override macros the README demonstrates', function () {
    app(MessageBinder::class)->registerHooks();

    expect(TextInput::hasMacro('messageName'))->toBeTrue('README documents ->messageName()')
        ->and(TextInput::hasMacro('domain'))->toBeTrue('README documents ->domain()');
})->skip(! class_exists(TextInput::class), 'Filament forms not installed');
