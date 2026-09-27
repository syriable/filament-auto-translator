<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Console;

use Illuminate\Console\Command;
use Syriable\Filament\Plugins\AutoTranslator\Enums\ResolutionOutcome;
use Syriable\Filament\Plugins\AutoTranslator\Scanning\Finding;
use Syriable\Filament\Plugins\AutoTranslator\Scanning\MessageScanner;

final class AuditCommand extends Command
{
    protected $signature = 'auto-translator:audit
        {--locale= : The locale to audit; defaults to the application locale}
        {--fail-on-missing : Exit with 1 when a required message is missing}
        {--fail-on-fallback : Exit with 1 when the locale shows the fallback locale\'s copy}';

    protected $description = 'Report messages the registered Filament UI is missing in a locale, without writing anything.';

    public function handle(MessageScanner $scanner): int
    {
        $locale = $this->option('locale');
        $findings = $scanner->scan(is_string($locale) && $locale !== '' ? $locale : null)->findings;

        if ($findings === []) {
            $this->components->info('No missing messages.');

            return self::SUCCESS;
        }

        $this->table(
            ['Locale', 'Outcome', 'Domain', 'Key'],
            array_map(fn (Finding $finding): array => [$finding->locale, $finding->outcome->value, $finding->domain, $finding->key], $findings),
        );

        return $this->fails($findings) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<Finding>  $findings
     */
    private function fails(array $findings): bool
    {
        foreach ($findings as $finding) {
            if ($this->option('fail-on-missing') && $finding->outcome === ResolutionOutcome::Missing) {
                return true;
            }

            if ($this->option('fail-on-fallback') && $finding->outcome === ResolutionOutcome::UsedFallbackLocale) {
                return true;
            }
        }

        return false;
    }
}
