<?php

declare(strict_types=1);

use Syriable\MessageCatalog\Support\NameNormalizer;

it('replaces dots with a double underscore so laravel does not nest the key', function () {
    expect(NameNormalizer::machine('author.name'))->toBe('author__name');
});

it('accepts kebab catalog basenames', function () {
    expect(NameNormalizer::isValid('user-resource'))->toBeTrue()
        ->and(NameNormalizer::isValid('author__name'))->toBeTrue()
        ->and(NameNormalizer::isValid('email address'))->toBeFalse();
});
