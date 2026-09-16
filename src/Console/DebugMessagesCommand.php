<?php

declare(strict_types=1);

namespace Syriable\Translation\Console;

use Illuminate\Console\Command;
use Syriable\Translation\Enums\ResolutionOutcome;
use Syriable\Translation\Extraction\MessageScanner;

class DebugMessagesCommand extends Command
{
    protected $signature = 'translations:debug
        {--locale= : Locale to check}
        {--fail-on-missing : Fail when required messages are missing}
        {--fail-on-fallback : Fail when the current locale uses the fallback locale}';

    protected $description = 'Audit message catalog completeness for the current locale.';

    public function handle(MessageScanner $auditor): int
    {
        $locale = $this->option('locale');
        $locale = is_string($locale) && $locale !== '' ? $locale : null;

        $findings = $auditor->audit($locale);

        if ($findings === []) {
            $this->info('No message issues found.');

            return self::SUCCESS;
        }

        $this->table(['Locale', 'Decision', 'Catalog', 'Key'], array_map(
            fn (array $finding): array => [
                $finding['locale'],
                $finding['decision'],
                $finding['catalog'],
                $finding['key'],
            ],
            $findings,
        ));

        $failOnMissing = (bool) $this->option('fail-on-missing');
        $failOnFallback = (bool) $this->option('fail-on-fallback');

        foreach ($findings as $finding) {
            if ($failOnMissing && $finding['decision'] === ResolutionOutcome::Missing->value) {
                return self::FAILURE;
            }

            if ($failOnFallback && $finding['decision'] === ResolutionOutcome::UsedFallbackLocale->value) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
