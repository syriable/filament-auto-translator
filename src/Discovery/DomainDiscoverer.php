<?php

declare(strict_types=1);

namespace Syriable\Translation\Discovery;

use Filament\Resources\Resource as FilamentResource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Filesystem\Filesystem;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Syriable\Translation\Exceptions\InvalidTranslationDomainException;

/**
 * Turns a directory and its PSR-4 namespace into the message catalogs it holds,
 * the same way a panel turns a directory into resources.
 */
class DomainDiscoverer
{
    /**
     * Static builders a catalog class may expose, in the order they are tried.
     *
     * @var array<int, string>
     */
    public const SCHEMA_METHODS = ['form', 'configure'];

    /**
     * Static builders that return a whole component rather than a schema.
     *
     * A form class often describes its chrome — the section that wraps the
     * fields, its heading, its footer — in a second builder that takes no
     * Schema, so the schema walk never reaches it and its copy has to be
     * written by hand. This is that builder.
     *
     * @var array<int, string>
     */
    public const CONTENT_METHODS = ['make'];

    public function __construct(
        private Filesystem $filesystem,
        private DomainResolver $domains,
    ) {}

    /**
     * @return array<class-string, DiscoveredDomain>
     */
    public function discover(string $directory, string $namespace): array
    {
        if (blank($directory) || blank($namespace) || ! $this->filesystem->isDirectory($directory)) {
            return [];
        }

        $catalogs = [];

        foreach ($this->filesystem->allFiles($directory) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $class = $this->classFor($namespace, $file->getRelativePathname());

            if (! class_exists($class)) {
                continue;
            }

            $catalog = $this->catalogFor($class);

            if (! $catalog instanceof DiscoveredDomain) {
                continue;
            }

            $catalogs[$class] = $catalog;
        }

        return $catalogs;
    }

    /**
     * @param  class-string  $class
     */
    public function catalogFor(string $class): ?DiscoveredDomain
    {
        // Resources carry their own chrome and pages, so they stay on the resource walk.
        if (is_subclass_of($class, FilamentResource::class)) {
            return null;
        }

        $reflection = new ReflectionClass($class);

        if ($reflection->isAbstract() || $reflection->isEnum()) {
            return null;
        }

        $method = $this->schemaMethodOn($reflection);

        if ($method === null) {
            return null;
        }

        $contentMethod = $this->contentMethodOn($reflection);

        $catalogId = $this->domains->for($class);

        if ($catalogId === null) {
            return null;
        }

        if (! $this->isCatalogId($catalogId)) {
            throw InvalidTranslationDomainException::make($class, $catalogId);
        }

        return new DiscoveredDomain(
            class: $class,
            method: $method,
            catalogId: $catalogId,
            contentMethod: $contentMethod,
        );
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     */
    private function schemaMethodOn(ReflectionClass $reflection): ?string
    {
        foreach (self::SCHEMA_METHODS as $name) {
            if (! $reflection->hasMethod($name)) {
                continue;
            }

            $method = $reflection->getMethod($name);

            if ($this->buildsSchema($method)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     */
    private function contentMethodOn(ReflectionClass $reflection): ?string
    {
        foreach (self::CONTENT_METHODS as $name) {
            if (! $reflection->hasMethod($name)) {
                continue;
            }

            if ($this->buildsContent($reflection->getMethod($name))) {
                return $name;
            }
        }

        return null;
    }

    private function buildsContent(ReflectionMethod $method): bool
    {
        if (! $method->isStatic() || ! $method->isPublic()) {
            return false;
        }

        if ($method->getNumberOfRequiredParameters() > 0) {
            return false;
        }

        $type = $method->getReturnType();

        if (! $type instanceof ReflectionNamedType) {
            return false;
        }

        return is_a($type->getName(), Component::class, true);
    }

    private function buildsSchema(ReflectionMethod $method): bool
    {
        if (! $method->isStatic() || ! $method->isPublic()) {
            return false;
        }

        $parameters = $method->getParameters();

        if ($parameters === [] || $method->getNumberOfRequiredParameters() > 1) {
            return false;
        }

        $type = $parameters[0]->getType();

        if (! $type instanceof ReflectionNamedType) {
            return $type === null;
        }

        return $type->getName() === Schema::class || is_a(Schema::class, $type->getName(), true);
    }

    private function classFor(string $namespace, string $relativePathname): string
    {
        $relative = substr($relativePathname, 0, -strlen('.php'));

        return trim($namespace, '\\').'\\'.str_replace(['/', DIRECTORY_SEPARATOR], '\\', $relative);
    }

    private function isCatalogId(string $catalogId): bool
    {
        $segment = '[A-Za-z0-9][A-Za-z0-9_-]*';

        return (bool) preg_match("/^({$segment}::)?{$segment}(\.{$segment})*$/", $catalogId);
    }
}
