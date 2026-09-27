<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Console;

use Illuminate\Console\Command;
use Syriable\Filament\Plugins\AutoTranslator\Inlining\MessageInliner;
use Syriable\Filament\Plugins\AutoTranslator\Inlining\SourceChange;

final class InlineCommand extends Command
{
    protected $signature = 'auto-translator:inline
        {--locale= : The locale whose language files supply the keys; defaults to the application locale}
        {--dry-run : Show what would change without writing}';

    protected $description = 'Write explicit __() setters into your Filament PHP for keys that already have copy.';

    public function handle(MessageInliner $inliner): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $locale = $this->option('locale');
        $changes = $inliner->inline(is_string($locale) && $locale !== '' ? $locale : null, $dryRun);

        if ($changes === []) {
            $this->components->info('Nothing to change.');

            return self::SUCCESS;
        }

        $this->table(
            ['File', 'Target', 'Method', 'Key', 'Change'],
            array_map(fn (SourceChange $change): array => [$change->path, $change->target, $change->method, $change->key, $change->type->describe($dryRun)], $changes),
        );

        $this->components->info(($dryRun ? 'Would write ' : 'Wrote ').count($changes).' setter calls.');

        return self::SUCCESS;
    }
}
