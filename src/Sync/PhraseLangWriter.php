<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Sync;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;

class PhraseLangWriter
{
    public function pathFor(string $catalogId, string $locale): string
    {
        return lang_path($locale.'/'.str_replace('.', '/', $catalogId).'.php');
    }

    /**
     * @return array<int, string>
     */
    public function segments(string $catalogId, string $compiledKey): array
    {
        $group = str_replace('.', '/', $catalogId);
        $prefix = $group.'.';

        if (! str_starts_with($compiledKey, $prefix)) {
            return [];
        }

        $suffix = substr($compiledKey, strlen($prefix));

        if ($suffix === '') {
            return [];
        }

        return explode('.', $suffix);
    }

    public function stubValue(string $compiledKey, string $catalogId): string
    {
        $segments = $this->segments($catalogId, $compiledKey);

        if ($segments === []) {
            return 'Copy';
        }

        $slot = array_pop($segments);
        $leaf = $segments === [] ? (string) $slot : (string) end($segments);

        return str($leaf)
            ->replace('__', ' ')
            ->replace(['_', '-'], ' ')
            ->squish()
            ->ucfirst()
            ->toString();
    }

    /**
     * @param  array<string, mixed>  $tree
     * @param  array<int, string>  $segments
     */
    public function canSet(array $tree, array $segments): bool
    {
        if ($segments === []) {
            return false;
        }

        $current = $tree;
        $last = array_pop($segments);

        foreach ($segments as $segment) {
            if (! array_key_exists($segment, $current)) {
                return true;
            }

            if (! is_array($current[$segment])) {
                return false;
            }

            $current = $current[$segment];
        }

        return ! array_key_exists($last, $current);
    }

    /**
     * @return array<string, mixed>
     */
    public function load(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $loaded = include $path;

        return is_array($loaded) ? $loaded : [];
    }

    /**
     * @param  array<string, mixed>  $tree
     */
    public function persist(string $path, array $tree): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $this->render($tree));
    }

    public function remember(string $compiledKey, string $value, string $locale): void
    {
        Lang::addLines([
            $compiledKey => $value,
        ], $locale);
    }

    /**
     * @param  array<string, mixed>  $tree
     * @param  array<int, string>  $segments
     * @return array<string, mixed>
     */
    public function set(array $tree, array $segments, string $value): array
    {
        Arr::set($tree, implode('.', $segments), $value);

        return $tree;
    }

    /**
     * @param  array<string, mixed>  $tree
     * @param  array<int, string>  $prefix
     * @return array<int, array<int, string>>
     */
    public function leafSegments(array $tree, array $prefix = []): array
    {
        $leaves = [];

        foreach ($tree as $key => $value) {
            $path = [...$prefix, (string) $key];

            if (is_array($value)) {
                $leaves = [...$leaves, ...$this->leafSegments($value, $path)];

                continue;
            }

            $leaves[] = $path;
        }

        return $leaves;
    }

    /**
     * @param  array<string, mixed>  $tree
     * @param  array<int, string>  $segments
     * @return array<string, mixed>
     */
    public function forget(array $tree, array $segments): array
    {
        if ($segments === []) {
            return $tree;
        }

        Arr::forget($tree, implode('.', $segments));

        return $this->compactEmpty($tree);
    }

    /**
     * @param  array<string, mixed>  $tree
     * @return array<string, mixed>
     */
    private function compactEmpty(array $tree): array
    {
        $compacted = [];

        foreach ($tree as $key => $value) {
            if (is_array($value)) {
                $value = $this->compactEmpty($value);

                if ($value === []) {
                    continue;
                }
            }

            $compacted[$key] = $value;
        }

        return $compacted;
    }

    public function isSafeCatalogId(string $catalogId): bool
    {
        if ($catalogId === '' || str_contains($catalogId, '..') || str_starts_with($catalogId, '/')) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9._-]+$/', $catalogId);
    }

    public function isSafeLocale(string $locale): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,15}$/', $locale);
    }

    /**
     * @param  array<string, mixed>  $tree
     */
    private function render(array $tree): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\nreturn {$this->export($tree)};\n";
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function export(array $value, int $depth = 1): string
    {
        if ($value === []) {
            return '[]';
        }

        $indent = str_repeat('    ', $depth);
        $outdent = str_repeat('    ', $depth - 1);
        $lines = ['['];

        foreach ($value as $key => $item) {
            $exportedKey = var_export((string) $key, true);

            if (is_array($item)) {
                $lines[] = "{$indent}{$exportedKey} => {$this->export($item, $depth + 1)},";

                continue;
            }

            $lines[] = "{$indent}{$exportedKey} => ".var_export((string) $item, true).',';
        }

        $lines[] = "{$outdent}]";

        return implode("\n", $lines);
    }
}
