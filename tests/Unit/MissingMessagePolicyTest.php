<?php

declare(strict_types=1);

use Syriable\MessageCatalog\Enums\MissingMessagePolicy;

it('falls back rather than exposing keys when nothing is configured', function () {
    expect(MissingMessagePolicy::fromConfig(''))->toBe(MissingMessagePolicy::Fallback)
        ->and(MissingMessagePolicy::fromConfig('nonsense'))->toBe(MissingMessagePolicy::Fallback);
});

it('ships a safe default in the published config', function () {
    $config = require dirname(__DIR__, 2).'/config/messages.php';

    expect($config['on_missing'])->toBe('fallback');
});

it('resolves each policy from its configured value', function (string $value, MissingMessagePolicy $expected) {
    expect(MissingMessagePolicy::fromConfig($value))->toBe($expected);
})->with([
    ['debug', MissingMessagePolicy::Debug],
    ['strict', MissingMessagePolicy::Strict],
    ['fallback', MissingMessagePolicy::Fallback],
]);
