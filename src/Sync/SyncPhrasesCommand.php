<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Sync;

use Illuminate\Console\Command;

class SyncPhrasesCommand extends Command
{
    protected $signature = 'phrases:sync
        {--locale= : Locale to write, or comma-separated locales}
        {--dry-run : Show missing and orphan keys without writing files}
        {--no-prune : Keep language keys for components that were removed}';

    protected $description = 'Create missing phrase catalog keys and remove copy for deleted components.';

    public function handle(PhraseCatalogSyncer $syncer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $prune = ! (bool) $this->option('no-prune');
        $writes = [];

        foreach ($this->locales() as $locale) {
            $writes = [...$writes, ...$syncer->sync($locale, $dryRun, $prune)];
        }

        if ($writes === []) {
            $this->info('No phrase catalog changes.');

            return self::SUCCESS;
        }

        $this->table(['Locale file', 'Key', 'Action', 'Value'], array_map(
            fn (PhraseSyncWrite $write): array => [
                $write->path,
                $write->key,
                $write->action,
                $write->value,
            ],
            $writes,
        ));

        $created = count(array_filter(
            $writes,
            fn (PhraseSyncWrite $write): bool => in_array($write->action, ['created', 'would_create'], true),
        ));
        $deleted = count(array_filter(
            $writes,
            fn (PhraseSyncWrite $write): bool => in_array($write->action, ['deleted', 'would_delete'], true),
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
            $this->info("{$createdLabel} {$created} phrase keys.");
        }

        if ($deleted > 0) {
            $this->info("{$deletedLabel} {$deleted} phrase keys.");
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
