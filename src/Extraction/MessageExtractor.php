<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Extraction;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Syriable\Filament\Plugins\AutoTranslator\Domains\DomainName;
use Syriable\Filament\Plugins\AutoTranslator\Enums\ChangeType;
use Syriable\Filament\Plugins\AutoTranslator\Enums\ResolutionOutcome;
use Syriable\Filament\Plugins\AutoTranslator\Exceptions\UnknownTranslationNamespaceException;
use Syriable\Filament\Plugins\AutoTranslator\Messages\MessageIdentity;
use Syriable\Filament\Plugins\AutoTranslator\Messages\MessageResolver;
use Syriable\Filament\Plugins\AutoTranslator\Scanning\Coverage;
use Syriable\Filament\Plugins\AutoTranslator\Scanning\Finding;
use Syriable\Filament\Plugins\AutoTranslator\Scanning\MessageScanner;
use Syriable\Filament\Plugins\AutoTranslator\Scanning\ScanResult;

/**
 * Writes the keys the scanned UI needs into its language files, and removes
 * keys for components that no longer exist.
 *
 * Copy already in a file is never overwritten. A missing key gets a
 * humanized stub (`is_featured` → `Is featured`), or the fallback locale's
 * copy when that has it, ready to translate.
 */
final class MessageExtractor
{
    public function __construct(
        private readonly MessageScanner $scanner,
        private readonly LanguageFiles $files,
    ) {}

    /**
     * @return list<LanguageFileChange>
     */
    public function extract(?string $locale = null, bool $dryRun = false, bool $prune = true): array
    {
        $locale ??= app()->getLocale();

        if (! LanguageFiles::isSafeLocale($locale)) {
            return [];
        }

        $result = $this->scanner->scan($locale);
        $changes = $this->write($result->findings, $locale, $dryRun);

        if ($prune) {
            $changes = [...$changes, ...$this->prune($result, $locale, $dryRun)];
        }

        app(MessageResolver::class)->flush();

        return $changes;
    }

    /**
     * Removes the keys a scan proved belong to nothing, in every domain it
     * covered.
     *
     * @return list<LanguageFileChange>
     */
    public function prune(ScanResult $result, string $locale, bool $dryRun = false): array
    {
        if (! LanguageFiles::isSafeLocale($locale)) {
            return [];
        }

        $changes = [];

        foreach ($result->coverage as $domain => $coverage) {
            $changes = [...$changes, ...$this->pruneDomain($domain, $coverage, $locale, $dryRun)];
        }

        return $changes;
    }

    /**
     * Writes the identities you name; never prunes.
     *
     * @param  list<MessageIdentity>  $identities
     * @return list<LanguageFileChange>
     */
    public function extractIdentities(array $identities, string $locale, bool $dryRun = false): array
    {
        if (! LanguageFiles::isSafeLocale($locale)) {
            return [];
        }

        $original = app()->getLocale();
        app()->setLocale($locale);

        try {
            $changes = $this->write($this->scanner->scanIdentities($identities), $locale, $dryRun);
        } finally {
            app()->setLocale($original);
        }

        app(MessageResolver::class)->flush();

        return $changes;
    }

    /**
     * The namespaces the run had to register because their module had no
     * language directory yet.
     *
     * @return array<string, string>
     */
    public function registeredNamespaces(): array
    {
        return $this->files->registeredNamespaces();
    }

    /**
     * @param  list<Finding>  $findings
     * @return list<LanguageFileChange>
     */
    private function write(array $findings, string $locale, bool $dryRun): array
    {
        $byDomain = [];

        foreach ($findings as $finding) {
            if (in_array($finding->outcome, [ResolutionOutcome::Missing, ResolutionOutcome::UsedFallbackLocale], true) && DomainName::isValid($finding->domain)) {
                $byDomain[$finding->domain][] = $finding;
            }
        }

        $changes = [];

        foreach ($byDomain as $domain => $domainFindings) {
            $this->files->ensureNamespace($domain);
            $path = $this->files->pathFor($domain, $locale);
            $tree = $this->files->load($path);
            $written = [];

            foreach ($domainFindings as $finding) {
                $segments = DomainName::segmentsOf($domain, $finding->key);

                if (! LanguageFiles::canSet($tree, $segments)) {
                    continue;
                }

                $value = $this->valueFor($finding, $segments);
                $changes[] = new LanguageFileChange($path, $finding->key, ChangeType::Created, $value, $dryRun);
                Arr::set($tree, implode('.', $segments), $value);
                $written[$finding->key] = $value;
            }

            if (! $dryRun && $written !== []) {
                $this->files->write($path, $tree);

                // the rest of the run reads what was just written
                Lang::addLines($written, $locale);
            }
        }

        return $changes;
    }

    /**
     * @return list<LanguageFileChange>
     */
    private function pruneDomain(string $domain, Coverage $coverage, string $locale, bool $dryRun): array
    {
        if (! DomainName::isValid($domain)) {
            return [];
        }

        try {
            $path = $this->files->pathFor($domain, $locale);
        } catch (UnknownTranslationNamespaceException) {
            // nothing is registered under the name, so there is no file
            return [];
        }

        $tree = $this->files->load($path);
        $changes = [];

        foreach (LanguageFiles::leaves($tree) as $segments) {
            if (! $coverage->isOrphaned($segments)) {
                continue;
            }

            $value = Arr::get($tree, implode('.', $segments));
            $changes[] = new LanguageFileChange($path, DomainName::group($domain).'.'.implode('.', $segments), ChangeType::Deleted, is_string($value) ? $value : '', $dryRun);
            $tree = LanguageFiles::forget($tree, $segments);
        }

        if (! $dryRun && $changes !== []) {
            $this->files->write($path, $tree);
        }

        return $changes;
    }

    /**
     * @param  list<string>  $segments
     */
    private function valueFor(Finding $finding, array $segments): string
    {
        if ($finding->outcome === ResolutionOutcome::UsedFallbackLocale && $finding->text !== null && $finding->text !== '' && $finding->text !== $finding->key) {
            return $finding->text;
        }

        // the leaf's own name, not its slot: `is_featured.label` → "Is featured"
        $name = count($segments) > 1 ? $segments[count($segments) - 2] : ($segments[0] ?? 'copy');

        return str($name)->replace(['__', '_', '-'], ' ')->squish()->ucfirst()->toString();
    }
}
