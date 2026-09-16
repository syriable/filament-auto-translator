<?php

declare(strict_types=1);

namespace Syriable\Translation\Console;

use Illuminate\Console\Command;
use Syriable\Translation\Apply\InlineWrite;
use Syriable\Translation\Apply\MessageInliner;

class InlineMessagesCommand extends Command
{
    protected $signature = 'translations:inline
        {--locale= : Locale whose language file supplies the keys}
        {--dry-run : Show methods that would be written without changing PHP}';

    protected $description = 'Write Filament setter calls for message keys that already exist in the language file.';

    public function handle(MessageInliner $applier): int
    {
        $locale = $this->locale();
        $dryRun = (bool) $this->option('dry-run');
        $writes = $applier->apply($locale, $dryRun);

        if ($writes === []) {
            $this->info('No setter calls to write.');

            return self::SUCCESS;
        }

        $this->table(['PHP file', 'make()', 'Method', 'Key', 'Action'], array_map(
            fn (InlineWrite $write): array => [
                $write->path,
                $write->make,
                $write->method,
                $write->key,
                $write->action,
            ],
            $writes,
        ));

        $count = count($writes);

        if ($dryRun) {
            $this->info("Would write {$count} setter calls.");

            return self::SUCCESS;
        }

        $this->info("Wrote {$count} setter calls.");

        return self::SUCCESS;
    }

    private function locale(): string
    {
        $option = $this->option('locale');
        $raw = is_string($option) ? $option : '';

        if ($raw === '') {
            return app()->getLocale();
        }

        foreach (explode(',', $raw) as $locale) {
            $locale = trim($locale);

            if ($locale !== '') {
                return $locale;
            }
        }

        return app()->getLocale();
    }
}
