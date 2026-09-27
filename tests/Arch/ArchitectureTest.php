<?php

declare(strict_types=1);

arch('source files declare strict types')
    ->expect('Syriable\Filament\Plugins\AutoTranslator')
    ->toUseStrictTypes();

arch('message resolution knows nothing about Filament')
    ->expect('Syriable\Filament\Plugins\AutoTranslator\Messages')
    ->not->toUse('Filament');

arch('runtime binding never reaches the console tooling')
    ->expect('Syriable\Filament\Plugins\AutoTranslator\Binding')
    ->not->toUse([
        'Syriable\Filament\Plugins\AutoTranslator\Scanning',
        'Syriable\Filament\Plugins\AutoTranslator\Extraction',
        'Syriable\Filament\Plugins\AutoTranslator\Inlining',
        'Syriable\Filament\Plugins\AutoTranslator\Console',
    ]);

arch('scanning never writes files')
    ->expect('Syriable\Filament\Plugins\AutoTranslator\Scanning')
    ->not->toUse([
        'Syriable\Filament\Plugins\AutoTranslator\Extraction',
        'Syriable\Filament\Plugins\AutoTranslator\Inlining',
    ]);

arch('value objects are immutable')
    ->expect([
        'Syriable\Filament\Plugins\AutoTranslator\Messages\MessageIdentity',
        'Syriable\Filament\Plugins\AutoTranslator\Messages\Resolution',
        'Syriable\Filament\Plugins\AutoTranslator\Scanning\Surface',
        'Syriable\Filament\Plugins\AutoTranslator\Scanning\ChromeMessage',
        'Syriable\Filament\Plugins\AutoTranslator\Scanning\Finding',
        'Syriable\Filament\Plugins\AutoTranslator\Scanning\Coverage',
        'Syriable\Filament\Plugins\AutoTranslator\Scanning\ScanResult',
        'Syriable\Filament\Plugins\AutoTranslator\Extraction\LanguageFileChange',
        'Syriable\Filament\Plugins\AutoTranslator\Inlining\SourceChange',
        'Syriable\Filament\Plugins\AutoTranslator\Domains\SchemaDomain',
    ])
    ->toBeReadonly();

arch('no reflection hacks into Filament internals')
    ->expect('Syriable\Filament\Plugins\AutoTranslator')
    ->not->toUse(['invade', 'set_time_limit', 'ini_set']);

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die', 'exit'])
    ->not->toBeUsed();
