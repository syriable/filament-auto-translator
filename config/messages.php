<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Phrase Mode
    |--------------------------------------------------------------------------
    |
    | This value controls how missing catalog copy is handled. Inspect shows
    | the compiled translation key in the panel so you can add it to a lang
    | file. Strict throws when a required phrase is missing. Lenient keeps
    | Filament's default text and logs the gap.
    |
    | Supported: "inspect", "strict", "lenient"
    |
    */

    'on_missing' => env('MESSAGES_ON_MISSING', 'fallback'),

    /*
    |--------------------------------------------------------------------------
    | Default Domain Prefix
    |--------------------------------------------------------------------------
    |
    | The prefix used when a class matches no entry in domain_prefixes. A
    | dotted prefix makes a folder under the application lang path, so
    | App\Filament\Resources\UserResource becomes "filament.user-resource"
    | in lang/{locale}/filament/user-resource.php.
    |
    */

    'default_domain_prefix' => 'filament',

    /*
    |--------------------------------------------------------------------------
    | Domain Prefixes
    |--------------------------------------------------------------------------
    |
    | Map PHP namespaces to domain prefixes. The longest matching namespace
    | wins, so a module can override a broader App\Filament prefix. These
    | values can also be registered on the panel plugin.
    |
    | End a prefix with "::" to name a registered translation namespace rather
    | than a folder. A module then keeps its copy in its own lang directory:
    |
    |     'Modules\Identity' => 'identity::'
    |         -> identity::user-resource
    |         -> modules/identity/resources/lang/{locale}/user-resource.php
    |
    |     'Modules\Identity' => 'identity'
    |         -> identity.user-resource
    |         -> lang/{locale}/identity/user-resource.php
    |
    */

    'domain_prefixes' => [
        // 'Modules\\Identity' => 'identity::',
        // 'Modules\\Billing' => 'billing',
        // 'App\\Filament' => 'filament',
    ],

    /*
    |--------------------------------------------------------------------------
    | Schema Catalog Paths
    |--------------------------------------------------------------------------
    |
    | Directories scanned for phrase catalogs that own a schema but are not
    | Filament resources, such as Livewire form schemas on the public site.
    | A class is collected when it implements the PhraseCatalog contract and
    | exposes a public static form(Schema) or configure(Schema) builder.
    |
    | Register a module once. Panel plugins can do the same through
    | MessageCatalogPlugin::make()->discoverDiscoveredDomains(in: ..., for: ...).
    |
    */

    'discover_paths' => [
        // [
        //     'path' => base_path('modules/identity/src/Livewire/Schemas'),
        //     'namespace' => 'Modules\\Identity\\Livewire\\Schemas',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Inspect Query Parameter
    |--------------------------------------------------------------------------
    |
    | When this query string key is present on a request, inspect mode may
    | dump phrase resolutions for that request (for example ?phrases=1).
    | Leave this as a dedicated key so it does not collide with app filters.
    |
    */

    'debug_query' => 'messages',

    /*
    |--------------------------------------------------------------------------
    | Maximum Parent Depth
    |--------------------------------------------------------------------------
    |
    | Phrase identity walks parent schema and action components to build a
    | stable path. This cap stops infinite walks on cyclic or unexpectedly
    | deep trees. Exceeding it throws ParentDepthExceededException.
    |
    */

    'max_parent_depth' => 32,

];
