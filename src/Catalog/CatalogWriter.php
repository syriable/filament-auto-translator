<?php

declare(strict_types=1);

namespace Syriable\Translation\Catalog;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Syriable\Translation\Exceptions\UnknownDomainNamespaceException;

class CatalogWriter
{
    /**
     * @var array<string, string>
     */
    private array $registeredNamespaces = [];

    public function pathFor(string $catalogId, string $locale): string
    {
        [$namespace, $group] = $this->split($catalogId);

        $relative = $locale.'/'.str_replace('.', '/', $group).'.php';

        if ($namespace === null) {
            return lang_path($relative);
        }

        $root = $this->namespacePath($namespace);

        if ($root === null) {
            throw UnknownDomainNamespaceException::make($catalogId, $namespace);
        }

        return rtrim($root, '/\\').'/'.$relative;
    }

    /**
     * Splits a catalog id into its translation namespace and group.
     *
     * @return array{0: string|null, 1: string}
     */
    public function split(string $catalogId): array
    {
        if (! str_contains($catalogId, '::')) {
            return [null, $catalogId];
        }

        /** @var array{0: string, 1: string} $parts */
        $parts = explode('::', $catalogId, 2);

        return [$parts[0], $parts[1]];
    }

    private function namespacePath(string $namespace): ?string
    {
        $path = Lang::getLoader()->namespaces()[$namespace] ?? null;

        return is_string($path) ? $path : null;
    }

    /**
     * Registers a namespaced catalog's language directory, creating it when
     * the module that owns the name has none yet.
     *
     * Laravel knows a translation namespace only once something registers it,
     * and a module registers its own only when the directory is there. A
     * module that has never been translated therefore has no namespace, and
     * the one command whose job is to write its first language file would
     * stop on it instead.
     *
     * Nothing is guessed: the name has to match a module that exists on disk,
     * so a typo is still an unknown namespace and still throws. Only the
     * namespace is registered here — the directory itself appears with the
     * first file written into it, so a dry run stays a dry run. Writing that
     * file is also the lasting fix, because the directory is what a module
     * package looks for when it registers the namespace on the next boot.
     */
    public function ensureNamespace(string $catalogId): ?string
    {
        [$namespace] = $this->split($catalogId);

        if ($namespace === null || $this->namespacePath($namespace) !== null) {
            return null;
        }

        $path = $this->moduleLangPath($namespace);

        if ($path === null) {
            return null;
        }

        Lang::addNamespace($namespace, $path);

        return $this->registeredNamespaces[$namespace] = $path;
    }

    /**
     * The namespaces this run had to register itself, by name.
     *
     * @return array<string, string>
     */
    public function registeredNamespaces(): array
    {
        return $this->registeredNamespaces;
    }

    private function moduleLangPath(string $namespace): ?string
    {
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $namespace)) {
            return null;
        }

        $registered = $this->moduleRegistryLangPath($namespace);

        if ($registered !== null) {
            return $registered;
        }

        $configured = config('translations.module_path', 'modules');
        $base = base_path(trim(is_string($configured) ? $configured : 'modules', '/\\').'/'.$namespace);

        return is_dir($base) ? $base.'/resources/lang' : null;
    }

    /**
     * Asks a module package where the module lives, when one is installed.
     */
    private function moduleRegistryLangPath(string $namespace): ?string
    {
        $registry = 'InterNACHI\\Modular\\Support\\ModuleRegistry';

        if (! class_exists($registry) || ! app()->bound($registry)) {
            return null;
        }

        $modules = app($registry);

        if (! is_object($modules) || ! method_exists($modules, 'module')) {
            return null;
        }

        $module = $modules->module($namespace);

        if (! is_object($module) || ! method_exists($module, 'path')) {
            return null;
        }

        $path = $module->path('resources/lang');

        return is_string($path) ? $path : null;
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

        return (bool) preg_match('/^([A-Za-z0-9_-]+::)?[A-Za-z0-9._-]+$/', $catalogId);
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
