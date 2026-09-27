<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Scanning;

/**
 * A piece of translatable UI that belongs to one domain: a resource's form,
 * its table, a page's chrome, a schema domain's schema.
 */
final readonly class Surface
{
    /**
     * @param  list<ChromeMessage>  $chrome
     * @param  list<object>  $components  every component reached, depth first
     * @param  list<string>  $files  the PHP files the components are written in
     * @param  list<string>  $builtScopes  scopes whose builder ran to completion
     * @param  list<string>  $failedScopes  scopes whose builder threw, which must never be pruned
     * @param  list<string>  $pages  resource pages registered under the domain, by class kebab
     */
    public function __construct(
        public string $domain,
        public array $chrome = [],
        public array $components = [],
        public array $files = [],
        public array $builtScopes = [],
        public array $failedScopes = [],
        public array $pages = [],
    ) {}
}
