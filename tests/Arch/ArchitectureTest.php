<?php

declare(strict_types=1);

arch('source files declare strict types')
    ->expect('Syriable\FilamentAutoTranslator')
    ->toUseStrictTypes();

arch('message resolution knows nothing about Filament')
    ->expect('Syriable\FilamentAutoTranslator\Messages')
    ->not->toUse('Filament');

arch('runtime binding never reaches the console tooling')
    ->expect('Syriable\FilamentAutoTranslator\Binding')
    ->not->toUse([
        'Syriable\FilamentAutoTranslator\Scanning',
        'Syriable\FilamentAutoTranslator\Extraction',
        'Syriable\FilamentAutoTranslator\Inlining',
        'Syriable\FilamentAutoTranslator\Console',
    ]);

arch('scanning never writes files')
    ->expect('Syriable\FilamentAutoTranslator\Scanning')
    ->not->toUse([
        'Syriable\FilamentAutoTranslator\Extraction',
        'Syriable\FilamentAutoTranslator\Inlining',
    ]);

arch('value objects are immutable')
    ->expect([
        'Syriable\FilamentAutoTranslator\Messages\MessageIdentity',
        'Syriable\FilamentAutoTranslator\Messages\Resolution',
        'Syriable\FilamentAutoTranslator\Scanning\Surface',
        'Syriable\FilamentAutoTranslator\Scanning\ChromeMessage',
        'Syriable\FilamentAutoTranslator\Scanning\Finding',
        'Syriable\FilamentAutoTranslator\Scanning\Coverage',
        'Syriable\FilamentAutoTranslator\Scanning\ScanResult',
        'Syriable\FilamentAutoTranslator\Extraction\LanguageFileChange',
        'Syriable\FilamentAutoTranslator\Inlining\SourceChange',
        'Syriable\FilamentAutoTranslator\Domains\SchemaDomain',
    ])
    ->toBeReadonly();

arch('no reflection hacks into Filament internals')
    ->expect('Syriable\FilamentAutoTranslator')
    ->not->toUse(['invade', 'set_time_limit', 'ini_set']);

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die', 'exit'])
    ->not->toBeUsed();
