<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Missing Message Policy
    |--------------------------------------------------------------------------
    |
    | What a required message (a label, a title) does when neither the current
    | locale nor the fallback locale has it. Optional copy simply stays empty.
    |
    |   keep_vendor_label  Keep Filament's own text. The default.
    |   debug              Render the message key, so you can see what to add.
    |   strict             Throw a MissingMessageException.
    |
    | A panel can override this with AutoTranslatorPlugin::make()->onMissing().
    |
    */

    'on_missing' => env('AUTO_TRANSLATOR_ON_MISSING', 'keep_vendor_label'),

    /*
    |--------------------------------------------------------------------------
    | Default Domain Prefix
    |--------------------------------------------------------------------------
    |
    | The prefix of a derived translation domain when a class matches none of
    | the domain_prefixes below. App\Filament\Resources\UserResource becomes
    | "filament.user-resource", read from lang/{locale}/filament/user-resource.php.
    |
    */

    'default_domain_prefix' => 'filament',

    /*
    |--------------------------------------------------------------------------
    | Domain Prefixes
    |--------------------------------------------------------------------------
    |
    | Map PHP namespaces to domain prefixes; the longest matching namespace
    | wins. A prefix ending in "::" names a registered translation namespace,
    | so a module keeps its copy in its own lang directory:
    |
    |   'Modules\Billing'  => 'billing'    lang/{locale}/billing/invoice-resource.php
    |   'Modules\Identity' => 'identity::' {identity lang path}/{locale}/user-resource.php
    |
    | A panel can add to these with AutoTranslatorPlugin::make()->domainPrefixes().
    |
    */

    'domain_prefixes' => [
        // 'Modules\\Billing' => 'billing',
    ],

    /*
    |--------------------------------------------------------------------------
    | Discovery Paths
    |--------------------------------------------------------------------------
    |
    | Directories holding schema classes that are not resources — Livewire
    | form schemas on a public page, say. A class is collected when it carries
    | #[TranslationDomain] and a public static form(Schema) or configure(Schema).
    |
    */

    'discover_paths' => [
        // ['path' => app_path('Livewire/Schemas'), 'namespace' => 'App\\Livewire\\Schemas'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Module Path
    |--------------------------------------------------------------------------
    |
    | Where modules live, relative to the base path. When extraction writes the
    | first language file of a module whose translation namespace is not
    | registered yet, it registers {module_path}/{name}/resources/lang.
    |
    */

    'module_path' => 'modules',

    /*
    |--------------------------------------------------------------------------
    | Maximum Parent Depth
    |--------------------------------------------------------------------------
    |
    | How many parent components a key's path may walk through. The cap stops a
    | cyclic tree from looping forever; exceeding it throws.
    |
    */

    'max_parent_depth' => 32,

];
