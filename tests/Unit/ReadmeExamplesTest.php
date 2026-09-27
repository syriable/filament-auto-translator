<?php

declare(strict_types=1);

use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\Console\Kernel;
use Syriable\FilamentAutoTranslator\AutoTranslator;
use Syriable\FilamentAutoTranslator\AutoTranslatorPlugin;
use Syriable\FilamentAutoTranslator\Binding\ComponentBinder;
use Syriable\FilamentAutoTranslator\Enums\MissingMessagePolicy;

/**
 * The README makes concrete promises about the public surface. These check
 * the promises still hold, so the documentation cannot drift silently.
 */
function readme(): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/README.md');
}

it('exposes the entry points the README documents', function (string $class, string $method) {
    expect(method_exists($class, $method))->toBeTrue("{$class}::{$method}() is documented but missing");
})->with([
    [AutoTranslator::class, 'discoverIn'],
    [AutoTranslator::class, 'domainFor'],
    [AutoTranslator::class, 'explain'],
    [AutoTranslator::class, 'message'],
    [AutoTranslatorPlugin::class, 'domainPrefixes'],
    [AutoTranslatorPlugin::class, 'discoverIn'],
    [AutoTranslatorPlugin::class, 'onMissing'],
]);

it('offers the policies the README lists', function () {
    expect(array_map(fn (MissingMessagePolicy $policy): string => $policy->value, MissingMessagePolicy::cases()))
        ->toEqualCanonicalizing(['keep_vendor_label', 'debug', 'strict']);
});

it('ships exactly the config keys the README documents', function () {
    $config = require dirname(__DIR__, 2).'/config/filament-auto-translator.php';

    foreach (array_keys($config) as $key) {
        expect(readme())->toContain("`{$key}`");
    }

    expect(array_keys($config))->toEqualCanonicalizing([
        'on_missing', 'default_domain_prefix', 'domain_prefixes', 'discover_paths', 'module_path', 'max_parent_depth',
    ]);
});

it('registers the commands the README documents', function (string $command) {
    expect(array_keys(app(Kernel::class)->all()))->toContain($command)
        ->and(readme())->toContain($command);
})->with(['auto-translator:audit', 'auto-translator:extract', 'auto-translator:inline']);

it('imports only classes that exist in every README example', function () {
    preg_match_all('/^use (Syriable\\\\[A-Za-z0-9_\\\\]+);$/m', readme(), $matches);

    expect($matches[1])->not->toBeEmpty();

    foreach ($matches[1] as $class) {
        expect(class_exists($class) || trait_exists($class) || interface_exists($class) || enum_exists($class))
            ->toBeTrue("README imports {$class}, which does not exist");
    }
});

it('registers the macros the README demonstrates', function (string $macro) {
    app(ComponentBinder::class)->register();

    expect(TextInput::hasMacro($macro))->toBeTrue()
        ->and(readme())->toContain("{$macro}(");
})->with(['messageName', 'messageDomain', 'messageReplace', 'messageHtml']);

it('names the old package only in the upgrade guide', function () {
    $outsideUpgrading = preg_replace('/^## Upgrading$.*?(?=^## )/ms', '', readme());

    expect($outsideUpgrading)->not->toContain('laravel-translation')
        ->not->toContain('Syriable\\Translation\\')
        ->not->toContain('translations:');
});
