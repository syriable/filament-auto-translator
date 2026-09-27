<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Console;

use Illuminate\Console\Command;
use Syriable\FilamentAutoTranslator\Enums\ChangeType;
use Syriable\FilamentAutoTranslator\Extraction\LanguageFileChange;
use Syriable\FilamentAutoTranslator\Extraction\MessageExtractor;

final class ExtractCommand extends Command
{
    protected $signature = 'auto-translator:extract
        {--locale= : The locale to write, or several separated by commas; defaults to the application locale}
        {--dry-run : Show what would change without writing}
        {--no-prune : Keep keys whose component no longer exists}';

    protected $description = 'Write the message keys the registered Filament UI needs, and remove keys for deleted components.';

    public function handle(MessageExtractor $extractor): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $changes = [];

        foreach ($this->locales() as $locale) {
            $changes = [...$changes, ...$extractor->extract($locale, $dryRun, prune: ! $this->option('no-prune'))];
        }

        foreach ($extractor->registeredNamespaces() as $namespace => $path) {
            $this->components->info("Registered the translation namespace [{$namespace}] at {$path}; the module had no language directory yet.");
        }

        if ($changes === []) {
            $this->components->info('Nothing to change.');

            return self::SUCCESS;
        }

        $this->table(
            ['File', 'Key', 'Change', 'Value'],
            array_map(fn (LanguageFileChange $change): array => [$change->path, $change->key, $change->type->describe($dryRun), $change->value], $changes),
        );

        foreach ([ChangeType::Created, ChangeType::Deleted] as $type) {
            $count = count(array_filter($changes, fn (LanguageFileChange $change): bool => $change->type === $type));

            if ($count > 0) {
                $this->components->info(ucfirst($type->describe($dryRun))." {$count} message keys.");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function locales(): array
    {
        $option = $this->option('locale');
        $locales = array_filter(array_map(trim(...), explode(',', is_string($option) ? $option : '')));

        return $locales === [] ? [app()->getLocale()] : array_values(array_unique($locales));
    }
}
