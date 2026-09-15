<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Extraction;

use Illuminate\Support\Arr;
use Syriable\MessageCatalog\Binding\ResolutionCache;
use Syriable\MessageCatalog\Catalog\CatalogWriter;
use Syriable\MessageCatalog\Catalog\ObsoleteMessagePruner;
use Syriable\MessageCatalog\Enums\ResolutionOutcome;
use Syriable\MessageCatalog\MessageIdentity;

class MessageExtractor
{
    public function __construct(
        private MessageScanner $auditor,
        private CatalogWriter $writer,
        private ResolutionCache $memo,
        private ObsoleteMessagePruner $pruner,
    ) {}

    /**
     * @return array<int, ExtractionWrite>
     */
    public function sync(?string $locale = null, bool $dryRun = false, bool $prune = true): array
    {
        $locale ??= app()->getLocale();

        $writes = $this->writeFindings($this->auditor->audit($locale), $locale, $dryRun);

        if (! $prune) {
            return $writes;
        }

        return [...$writes, ...$this->pruneOrphans($locale, $dryRun)];
    }

    /**
     * @return array<int, ExtractionWrite>
     */
    public function pruneOrphans(string $locale, bool $dryRun = false): array
    {
        if (! $this->writer->isSafeLocale($locale)) {
            return [];
        }

        $writes = [];

        foreach (array_keys($this->auditor->walkedScopes()) as $catalogId) {
            $writes = [...$writes, ...$this->pruneCatalog($catalogId, $locale, $dryRun)];
        }

        $this->memo->flush();

        return $writes;
    }

    /**
     * @param  array<int, MessageIdentity>  $identities
     * @return array<int, ExtractionWrite>
     */
    public function syncIdentities(array $identities, string $locale, bool $dryRun = false): array
    {
        $originalLocale = app()->getLocale();
        app()->setLocale($locale);

        try {
            return $this->writeFindings($this->auditor->auditIdentities($identities), $locale, $dryRun);
        } finally {
            app()->setLocale($originalLocale);
        }
    }

    /**
     * @param  array<int, array{key: string, catalog: string, decision: string, locale: string, text: ?string}>  $findings
     * @return array<int, ExtractionWrite>
     */
    public function writeFindings(array $findings, string $locale, bool $dryRun = false): array
    {
        if (! $this->writer->isSafeLocale($locale)) {
            return [];
        }

        $grouped = [];

        foreach ($findings as $finding) {
            if (! in_array($finding['decision'], [ResolutionOutcome::Missing->value, ResolutionOutcome::UsedFallback->value], true)) {
                continue;
            }

            $catalogId = $finding['catalog'];

            if (! $this->writer->isSafeCatalogId($catalogId)) {
                continue;
            }

            $grouped[$catalogId][] = $finding;
        }

        $writes = [];

        foreach ($grouped as $catalogId => $catalogFindings) {
            $writes = [...$writes, ...$this->writeCatalog($catalogId, $locale, $catalogFindings, $dryRun)];
        }

        $this->memo->flush();

        return $writes;
    }

    /**
     * @param  array<int, array{key: string, catalog: string, decision: string, locale: string, text: ?string}>  $findings
     * @return array<int, ExtractionWrite>
     */
    private function writeCatalog(string $catalogId, string $locale, array $findings, bool $dryRun): array
    {
        $path = $this->writer->pathFor($catalogId, $locale);
        $tree = $this->writer->load($path);
        $changed = false;
        $writes = [];

        foreach ($findings as $finding) {
            $segments = $this->writer->segments($catalogId, $finding['key']);

            if (! $this->writer->canSet($tree, $segments)) {
                continue;
            }

            $value = $this->valueFor($finding);

            $writes[] = new ExtractionWrite(
                path: $path,
                key: $finding['key'],
                action: $dryRun ? 'would_create' : 'created',
                value: $value,
            );

            if ($dryRun) {
                continue;
            }

            $tree = $this->writer->set($tree, $segments, $value);
            $this->writer->remember($finding['key'], $value, $locale);
            $changed = true;
        }

        if ($changed) {
            $this->writer->persist($path, $tree);
        }

        return $writes;
    }

    /**
     * @param  array{key: string, catalog: string, decision: string, locale: string, text: ?string}  $finding
     */
    private function valueFor(array $finding): string
    {
        if (
            $finding['decision'] === ResolutionOutcome::UsedFallback->value
            && is_string($finding['text'])
            && $finding['text'] !== ''
            && $finding['text'] !== $finding['key']
        ) {
            return $finding['text'];
        }

        return $this->writer->stubValue($finding['key'], $finding['catalog']);
    }

    /**
     * @return array<int, ExtractionWrite>
     */
    private function pruneCatalog(string $catalogId, string $locale, bool $dryRun): array
    {
        if (! $this->writer->isSafeCatalogId($catalogId)) {
            return [];
        }

        $path = $this->writer->pathFor($catalogId, $locale);
        $tree = $this->writer->load($path);

        if ($tree === []) {
            return [];
        }

        $livePrefixes = $this->auditor->livePrefixes()[$catalogId] ?? [];
        $livePages = $this->auditor->livePages()[$catalogId] ?? [];
        $walkedScopes = $this->auditor->walkedScopes()[$catalogId] ?? [];
        $writes = [];
        $changed = false;

        foreach ($this->writer->leafSegments($tree) as $segments) {
            if (! $this->pruner->isOrphan($segments, $livePrefixes, $livePages, $walkedScopes)) {
                continue;
            }

            $current = Arr::get($tree, implode('.', $segments));

            $writes[] = new ExtractionWrite(
                path: $path,
                key: str_replace('.', '/', $catalogId).'.'.implode('.', $segments),
                action: $dryRun ? 'would_delete' : 'deleted',
                value: is_string($current) ? $current : '',
            );

            if ($dryRun) {
                continue;
            }

            $tree = $this->writer->forget($tree, $segments);
            $changed = true;
        }

        if ($changed) {
            $this->writer->persist($path, $tree);
        }

        return $writes;
    }
}
