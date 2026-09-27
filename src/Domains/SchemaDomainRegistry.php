<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Domains;

use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Filesystem\Filesystem;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Syriable\FilamentAutoTranslator\Exceptions\InvalidTranslationDomainException;
use Syriable\FilamentAutoTranslator\Settings;

/**
 * The schema domains found in registered directories.
 *
 * A directory is registered once per module, the way a panel registers
 * `discoverResources()`, and scanned lazily the first time a console command
 * asks — never while serving a request, where binding needs no list at all.
 */
final class SchemaDomainRegistry
{
    /**
     * @var list<string>
     */
    private const array SCHEMA_METHODS = ['form', 'configure'];

    private const string CHROME_METHOD = 'make';

    /**
     * @var array<string, array{path: string, namespace: string}>
     */
    private array $paths = [];

    /**
     * @var array<class-string, SchemaDomain>|null
     */
    private ?array $discovered = null;

    public function __construct(
        private readonly Filesystem $files,
        private readonly DomainResolver $domains,
        private readonly Settings $settings,
    ) {}

    /**
     * Registers a directory and the PSR-4 namespace it maps to. Idempotent.
     */
    public function register(string $path, string $namespace): void
    {
        $this->paths[$path.'|'.$namespace] = ['path' => $path, 'namespace' => $namespace];
        $this->discovered = null;
    }

    /**
     * @return array<class-string, SchemaDomain>
     */
    public function all(): array
    {
        if ($this->discovered !== null) {
            return $this->discovered;
        }

        $domains = [];

        foreach ([...$this->settings->discoverPaths(), ...array_values($this->paths)] as $entry) {
            $domains = [...$domains, ...$this->discoverIn($entry['path'], $entry['namespace'])];
        }

        return $this->discovered = $domains;
    }

    /**
     * The schema domain a class describes, or null when it is not one: it must
     * declare a domain and expose a public static schema builder.
     *
     * @param  class-string  $class
     *
     * @throws InvalidTranslationDomainException
     */
    public function inspect(string $class): ?SchemaDomain
    {
        // resources keep the richer resource walk, with chrome, pages and table
        if (is_subclass_of($class, Resource::class)) {
            return null;
        }

        $reflection = new ReflectionClass($class);

        if ($reflection->isAbstract() || $reflection->isEnum() || $reflection->isInterface()) {
            return null;
        }

        $schemaMethod = $this->firstMethod($reflection, self::SCHEMA_METHODS, $this->buildsSchema(...));
        $domain = $schemaMethod === null ? null : $this->domains->for($class);

        if ($schemaMethod === null || $domain === null) {
            return null;
        }

        if (! DomainName::isValid($domain)) {
            throw InvalidTranslationDomainException::forClass($class, $domain);
        }

        return new SchemaDomain(
            class: $class,
            domain: $domain,
            schemaMethod: $schemaMethod,
            chromeMethod: $this->firstMethod($reflection, [self::CHROME_METHOD], $this->buildsChrome(...)),
        );
    }

    /**
     * @return array<class-string, SchemaDomain>
     */
    private function discoverIn(string $path, string $namespace): array
    {
        if (! $this->files->isDirectory($path)) {
            return [];
        }

        $domains = [];

        foreach ($this->files->allFiles($path) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $class = trim($namespace, '\\').'\\'.str_replace(['/', '\\'], '\\', substr($file->getRelativePathname(), 0, -4));

            if (class_exists($class) && ($domain = $this->inspect($class)) !== null) {
                $domains[$class] = $domain;
            }
        }

        return $domains;
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     * @param  list<string>  $names
     * @param  callable(ReflectionMethod): bool  $accepts
     */
    private function firstMethod(ReflectionClass $reflection, array $names, callable $accepts): ?string
    {
        foreach ($names as $name) {
            if ($reflection->hasMethod($name) && $accepts($reflection->getMethod($name))) {
                return $name;
            }
        }

        return null;
    }

    private function buildsSchema(ReflectionMethod $method): bool
    {
        $parameter = $method->getParameters()[0] ?? null;

        if (! $method->isPublic() || ! $method->isStatic() || $parameter === null || $method->getNumberOfRequiredParameters() > 1) {
            return false;
        }

        $type = $parameter->getType();

        if (! $type instanceof ReflectionNamedType) {
            return $type === null;
        }

        return is_a(Schema::class, $type->getName(), true);
    }

    private function buildsChrome(ReflectionMethod $method): bool
    {
        $type = $method->getReturnType();

        return $method->isPublic()
            && $method->isStatic()
            && $method->getNumberOfRequiredParameters() === 0
            && $type instanceof ReflectionNamedType
            && is_a($type->getName(), Component::class, true);
    }
}
