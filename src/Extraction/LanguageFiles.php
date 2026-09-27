<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Extraction;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Syriable\Filament\Plugins\AutoTranslator\Domains\DomainName;
use Syriable\Filament\Plugins\AutoTranslator\Exceptions\UnknownTranslationNamespaceException;
use Syriable\Filament\Plugins\AutoTranslator\Settings;

/**
 * Reads and writes the PHP language files a domain maps to.
 *
 * One instance serves one command run: each file is read once and kept in
 * memory, and writes update that copy.
 */
final class LanguageFiles
{
    /**
     * @var array<string, array<array-key, mixed>>
     */
    private array $loaded = [];

    /**
     * @var array<string, string>
     */
    private array $registeredNamespaces = [];

    public function __construct(
        private readonly Filesystem $files,
        private readonly Settings $settings,
    ) {}

    public static function isSafeLocale(string $locale): bool
    {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,15}$/', $locale) === 1;
    }

    /**
     * @throws UnknownTranslationNamespaceException
     */
    public function pathFor(string $domain, string $locale): string
    {
        [$namespace, $name] = DomainName::split($domain);
        $relative = $locale.'/'.DomainName::group($name).'.php';

        if ($namespace === null) {
            return lang_path($relative);
        }

        $root = $this->namespacePath($namespace) ?? throw UnknownTranslationNamespaceException::forDomain($domain, $namespace);

        return rtrim($root, '/\\').'/'.$relative;
    }

    /**
     * Registers a namespaced domain's translation namespace when nothing has.
     *
     * A module package registers a module's namespace only once its
     * `resources/lang` directory exists, so a module that was never translated
     * has none — and the command whose job is to write its first file would
     * stop on it. The name must match a module on disk, so a typo still
     * throws. Only the namespace is registered: the directory arrives with the
     * first file written, so a dry run stays dry.
     */
    public function ensureNamespace(string $domain): void
    {
        [$namespace] = DomainName::split($domain);

        if ($namespace === null || $this->namespacePath($namespace) !== null) {
            return;
        }

        $path = $this->moduleLangPath($namespace);

        if ($path !== null) {
            Lang::addNamespace($namespace, $path);
            $this->registeredNamespaces[$namespace] = $path;
        }
    }

    /**
     * The namespaces this run registered itself.
     *
     * @return array<string, string>
     */
    public function registeredNamespaces(): array
    {
        return $this->registeredNamespaces;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function load(string $path): array
    {
        if (! array_key_exists($path, $this->loaded)) {
            $contents = $this->files->isFile($path) ? $this->files->getRequire($path) : [];
            $this->loaded[$path] = is_array($contents) ? $contents : [];
        }

        return $this->loaded[$path];
    }

    /**
     * @param  array<array-key, mixed>  $tree
     */
    public function write(string $path, array $tree): void
    {
        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, "<?php\n\ndeclare(strict_types=1);\n\nreturn ".$this->export($tree).";\n");
        $this->loaded[$path] = $tree;
    }

    /**
     * Whether a value can be written at the segments without replacing
     * existing copy or a string standing where an array is needed.
     *
     * @param  array<array-key, mixed>  $tree
     * @param  list<string>  $segments
     */
    public static function canSet(array $tree, array $segments): bool
    {
        $last = array_pop($segments);

        if ($last === null) {
            return false;
        }

        foreach ($segments as $segment) {
            if (! array_key_exists($segment, $tree)) {
                return true;
            }

            if (! is_array($tree[$segment])) {
                return false;
            }

            $tree = $tree[$segment];
        }

        return ! array_key_exists($last, $tree);
    }

    /**
     * Every leaf, as the segments leading to it.
     *
     * @param  array<array-key, mixed>  $tree
     * @param  list<string>  $prefix
     * @return list<list<string>>
     */
    public static function leaves(array $tree, array $prefix = []): array
    {
        $leaves = [];

        foreach ($tree as $key => $value) {
            $path = [...$prefix, (string) $key];
            $leaves = is_array($value) ? [...$leaves, ...self::leaves($value, $path)] : [...$leaves, $path];
        }

        return $leaves;
    }

    /**
     * Removes a leaf and every array it leaves empty.
     *
     * @param  array<array-key, mixed>  $tree
     * @param  list<string>  $segments
     * @return array<array-key, mixed>
     */
    public static function forget(array $tree, array $segments): array
    {
        Arr::forget($tree, implode('.', $segments));

        return self::withoutEmptyArrays($tree);
    }

    /**
     * @param  array<array-key, mixed>  $tree
     * @return array<array-key, mixed>
     */
    private static function withoutEmptyArrays(array $tree): array
    {
        foreach ($tree as $key => $value) {
            if (is_array($value)) {
                $value = self::withoutEmptyArrays($value);

                if ($value === []) {
                    unset($tree[$key]);

                    continue;
                }

                $tree[$key] = $value;
            }
        }

        return $tree;
    }

    private function namespacePath(string $namespace): ?string
    {
        $path = Lang::getLoader()->namespaces()[$namespace] ?? null;

        return is_string($path) ? $path : null;
    }

    private function moduleLangPath(string $namespace): ?string
    {
        if (preg_match('/^[A-Za-z0-9_-]+$/', $namespace) !== 1) {
            return null;
        }

        $module = $this->moduleRegistryPath($namespace) ?? base_path($this->settings->modulePath().'/'.$namespace);

        return is_dir($module) ? $module.'/resources/lang' : null;
    }

    /**
     * Asks internachi/modular where a module lives, when it is installed.
     */
    private function moduleRegistryPath(string $namespace): ?string
    {
        $registry = 'InterNACHI\\Modular\\Support\\ModuleRegistry';

        if (! class_exists($registry) || ! app()->bound($registry)) {
            return null;
        }

        $module = app($registry)->module($namespace);
        $path = is_object($module) && method_exists($module, 'path') ? $module->path() : null;

        return is_string($path) ? $path : null;
    }

    /**
     * @param  array<array-key, mixed>  $value
     */
    private function export(array $value, int $depth = 1): string
    {
        if ($value === []) {
            return '[]';
        }

        $indent = str_repeat('    ', $depth);
        $lines = ['['];

        foreach ($value as $key => $item) {
            $exported = is_array($item) ? $this->export($item, $depth + 1) : var_export(is_scalar($item) ? (string) $item : '', true);
            $lines[] = $indent.var_export((string) $key, true).' => '.$exported.',';
        }

        $lines[] = str_repeat('    ', $depth - 1).']';

        return implode("\n", $lines);
    }
}
