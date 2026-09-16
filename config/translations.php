<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Missing Message Policy
    |--------------------------------------------------------------------------
    |
    | What happens when a message has no line in the language file.
    |
    |   keep_vendor_label  Keep Filament's own label and carry on. The default.
    |   debug              Render the compiled key, so you can see what to add.
    |   strict             Throw.
    |
    | This is not about the fallback locale, which is separate: a key missing
    | in the current locale but present in the fallback one is used, and this
    | policy never comes into it.
    |
    */

    'on_missing' => env('TRANSLATIONS_ON_MISSING', 'keep_vendor_label'),

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
    | Discovery Paths
    |--------------------------------------------------------------------------
    |
    | Directories scanned for classes that own a schema but are not Filament
    | resources, such as Livewire form schemas on the public site. A class is
    | collected when it carries a #[TranslationDomain] attribute and exposes a
    | public static form(Schema) or configure(Schema) builder.
    |
    | Register a module once. A service provider can do the same through
    | Translations::discoverIn($path, $namespace), and a panel plugin through
    | TranslationPlugin::make()->discoverIn(in: ..., for: ...).
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
    | Module Path
    |--------------------------------------------------------------------------
    |
    | Where modules live, relative to the base path. A catalog named for a
    | translation namespace that nothing has registered — a module whose
    | resources/lang directory does not exist yet, so the module package never
    | registered it — is looked for here, and extraction registers the
    | namespace itself rather than stopping.
    |
    | A name that matches no module directory stays unknown and still throws.
    | When a module package is installed, it is asked first and this is only
    | the fallback.
    |
    */

    'module_path' => 'modules',

    /*
    |--------------------------------------------------------------------------
    | Maximum Parent Depth
    |--------------------------------------------------------------------------
    |
    | Message identity walks parent schema and action components to build a
    | stable path. This cap stops infinite walks on cyclic or unexpectedly
    | deep trees. Exceeding it throws ParentDepthExceededException.
    |
    */

    'max_parent_depth' => 32,

];
