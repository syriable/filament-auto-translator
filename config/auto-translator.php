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

    'mode' => env('PHRASE_MODE', 'inspect'),

    /*
    |--------------------------------------------------------------------------
    | Default Catalog Prefix
    |--------------------------------------------------------------------------
    |
    | Catalog ids are "{prefix}.{resource-basename}". When a class does not
    | match any entry in catalog_prefixes, this prefix is used. For example,
    | App\Filament\Resources\UserResource becomes "filament.user-resource".
    |
    */

    'default_prefix' => 'filament',

    /*
    |--------------------------------------------------------------------------
    | Catalog Prefixes
    |--------------------------------------------------------------------------
    |
    | Map PHP namespaces to catalog prefixes. The longest matching namespace
    | wins, so a module can override a broader App\Filament prefix. These
    | values can also be registered on the panel plugin.
    |
    */

    'catalog_prefixes' => [
        // 'Modules\\Billing' => 'billing',
        // 'App\\Filament' => 'filament',
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

    'inspect_query' => 'phrases',

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
