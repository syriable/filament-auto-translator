<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Domains;

/**
 * The syntax of a translation domain.
 *
 * A domain is dotted segments (`identity.user-edit`, a file under the
 * application's lang path) optionally namespaced against a registered
 * translation namespace (`identity::user-edit`, a file in that namespace's
 * own lang directory).
 */
final class DomainName
{
    private const string SEGMENT = '[A-Za-z0-9][A-Za-z0-9_-]*';

    public static function isValid(string $domain): bool
    {
        $segment = self::SEGMENT;

        return preg_match("/^({$segment}::)?{$segment}(\\.{$segment})*$/", $domain) === 1;
    }

    /**
     * The Laravel translation group: dots become directories.
     */
    public static function group(string $domain): string
    {
        return str_replace('.', '/', $domain);
    }

    /**
     * @return array{0: string|null, 1: string} the namespace, or null, and the dotted name
     */
    public static function split(string $domain): array
    {
        if (! str_contains($domain, '::')) {
            return [null, $domain];
        }

        [$namespace, $name] = explode('::', $domain, 2);

        return [$namespace, $name];
    }

    /**
     * The key segments below the domain's group, or an empty list when the
     * key belongs to another domain.
     *
     * @return list<string>
     */
    public static function segmentsOf(string $domain, string $key): array
    {
        $prefix = self::group($domain).'.';

        if (! str_starts_with($key, $prefix) || strlen($key) === strlen($prefix)) {
            return [];
        }

        return explode('.', substr($key, strlen($prefix)));
    }
}
