<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\AutoTranslator\Enums\MissingMessagePolicy;

it('falls back rather than exposing keys when nothing is configured', function () {
    expect(MissingMessagePolicy::fromConfig(''))->toBe(MissingMessagePolicy::KeepVendorLabel)
        ->and(MissingMessagePolicy::fromConfig('nonsense'))->toBe(MissingMessagePolicy::KeepVendorLabel);
});

it('ships a safe default in the published config', function () {
    $config = require dirname(__DIR__, 2).'/config/filament-auto-translator.php';

    expect($config['on_missing'])->toBe('keep_vendor_label');
});

it('resolves each policy from its configured value', function (string $value, MissingMessagePolicy $expected) {
    expect(MissingMessagePolicy::fromConfig($value))->toBe($expected);
})->with([
    ['debug', MissingMessagePolicy::Debug],
    ['strict', MissingMessagePolicy::Strict],
    ['keep_vendor_label', MissingMessagePolicy::KeepVendorLabel],
]);
