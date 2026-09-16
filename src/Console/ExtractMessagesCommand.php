<?php

declare(strict_types=1);

namespace Syriable\Translation\Console;

use Illuminate\Console\Command;
use Syriable\Translation\Extraction\ExtractionWrite;
use Syriable\Translation\Extraction\MessageExtractor;

class ExtractMessagesCommand extends Command
{
    protected $signature = 'translations:extract
        {--locale= : Locale to write, or comma-separated locales}
        {--dry-run : Show missing and orphan keys without writing files}
        {--no-prune : Keep language keys for components that were removed}';

    protected $description = 'Create missing message catalog keys and remove copy for deleted components.';

    public function handle(MessageExtractor $syncer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $prune = ! (bool) $this->option('no-prune');
        $writes = [];

        foreach ($this->locales() as $locale) {
            $writes = [...$writes, ...$syncer->sync($locale, $dryRun, $prune)];
        }

        if ($writes === []) {
            $this->info('No message catalog changes.');

            return self::SUCCESS;
        }

        $this->table(['Locale file', 'Key', 'Action', 'Value'], array_map(
            fn (ExtractionWrite $write): array => [
                $write->path,
                $write->key,
                $write->action,
                $write->value,
            ],
            $writes,
        ));

        $created = count(array_filter(
            $writes,
            fn (ExtractionWrite $write): bool => in_array($write->action, ['created', 'would_create'], true),
        ));
        $deleted = count(array_filter(
            $writes,
            fn (ExtractionWrite $write): bool => in_array($write->action, ['deleted', 'would_delete'], true),
        ));

        if ($dryRun) {
            $this->reportCounts($created, $deleted, 'Would create', 'Would remove');

            return self::SUCCESS;
        }

        $this->reportCounts($created, $deleted, 'Created', 'Removed');

        return self::SUCCESS;
    }

    private function reportCounts(int $created, int $deleted, string $createdLabel, string $deletedLabel): void
    {
        if ($created > 0) {
            $this->info("{$createdLabel} {$created} message keys.");
        }

        if ($deleted > 0) {
            $this->info("{$deletedLabel} {$deleted} message keys.");
        }
    }

    /**
     * @return array<int, string>
     */
    private function locales(): array
    {
        $option = $this->option('locale');
        $raw = is_string($option) ? $option : '';

        if ($raw === '') {
            return [app()->getLocale()];
        }

        $locales = [];

        foreach (explode(',', $raw) as $locale) {
            $locale = trim($locale);

            if ($locale === '') {
                continue;
            }

            $locales[] = $locale;
        }

        if ($locales === []) {
            return [app()->getLocale()];
        }

        return array_values(array_unique($locales));
    }
}
