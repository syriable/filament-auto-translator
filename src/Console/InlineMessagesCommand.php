<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Console;

use Illuminate\Console\Command;
use Syriable\MessageCatalog\Apply\PhraseApplyWrite;
use Syriable\MessageCatalog\Apply\PhrasePhpApplier;

class InlineMessagesCommand extends Command
{
    protected $signature = 'phrases:apply
        {--locale= : Locale whose language file supplies the keys}
        {--dry-run : Show methods that would be written without changing PHP}';

    protected $description = 'Write Filament setter calls for phrase keys that already exist in the language file.';

    public function handle(PhrasePhpApplier $applier): int
    {
        $locale = $this->locale();
        $dryRun = (bool) $this->option('dry-run');
        $writes = $applier->apply($locale, $dryRun);

        if ($writes === []) {
            $this->info('No phrase methods to write.');

            return self::SUCCESS;
        }

        $this->table(['PHP file', 'make()', 'Method', 'Key', 'Action'], array_map(
            fn (PhraseApplyWrite $write): array => [
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
            $this->info("Would write {$count} phrase methods.");

            return self::SUCCESS;
        }

        $this->info("Wrote {$count} phrase methods.");

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
