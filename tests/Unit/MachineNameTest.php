<?php

declare(strict_types=1);

use Syriable\FilamentAutoTranslator\Messages\MachineName;

it('replaces dots with a double underscore so laravel does not nest the key', function () {
    expect(MachineName::normalize('author.name'))->toBe('author__name')
        ->and(MachineName::normalize('Email'))->toBe('email');
});

it('accepts only characters a key segment can hold', function () {
    expect(MachineName::isValid('user-resource'))->toBeTrue()
        ->and(MachineName::isValid('author__name'))->toBeTrue()
        ->and(MachineName::isValid(''))->toBeTrue()
        ->and(MachineName::isValid('email address'))->toBeFalse()
        ->and(MachineName::isValid('-leading'))->toBeFalse();
});

it('reads a make() argument as a name only when it is one', function (mixed $argument, ?string $name) {
    expect(MachineName::fromArgument($argument))->toBe($name);
})->with([
    'identifier' => ['user_tabs', 'user_tabs'],
    'dotted' => ['address.city', 'address__city'],
    'visible sentence' => ['Please read this first.', null],
    'empty' => ['', null],
    'not a string' => [null, null],
]);

it('kebabs a class basename', function () {
    expect(MachineName::ofClass('App\\Filament\\Resources\\UserResource'))->toBe('user-resource');
});
