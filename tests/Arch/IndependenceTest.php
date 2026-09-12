<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('does not call invade', function () {
    foreach (File::allFiles(dirname(__DIR__, 2).'/src') as $file) {
        expect($file->getContents())->not->toContain('invade(');
    }
});

it('does not set max_execution_time', function () {
    foreach (File::allFiles(dirname(__DIR__, 2).'/src') as $file) {
        expect($file->getContents())->not->toContain('max_execution_time');
    }
});

it('does not ship replacement filament resource subclasses', function () {
    expect(is_dir(dirname(__DIR__, 2).'/src/Filament'))->toBeFalse();
});
