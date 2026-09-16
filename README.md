# syriable/laravel-translation

Translations for Filament and Livewire UI copy: identifiers in PHP, copy in language files.

Filament already translates strings. This package removes the work of inventing and threading keys through every `label()`, heading, hint, and nested action. It works inside a Filament panel and on public Livewire pages alike. PHP keeps machine names. Language files keep the copy.

This is **not** machine translation. It does not translate Eloquent records. It does not replace Filament vendor language files (`filament::` / `filament-panels::`).

```text
PHP: identifiers only          lang/{locale}/…: copy
TextInput::make('email')  →    'form' => ['components' => ['email' => ['label' => 'البريد']]]
```

- [What this package does](#what-this-package-does)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [How a message key is built](#how-a-message-key-is-built)
- [Language file layout](#language-file-layout)
- [Translation domains and prefixes](#translation-domains-and-prefixes)
- [Schema domains outside resources](#schema-domains-outside-resources)
- [What is bound automatically](#what-is-bound-automatically)
- [Resource chrome](#resource-chrome)
- [Missing-message policy](#missing-message-policy)
- [Overrides](#overrides)
- [Manual lookup](#manual-lookup)
- [Explaining a resolution](#explaining-a-resolution)
- [`translations:debug`](#messagesdebug)
- [`translations:extract`](#messagesextract)
- [`translations:inline`](#messagesinline)
- [Configuration](#configuration)
- [Name rules](#name-rules)
- [PHPStan](#phpstan)
- [Current limits](#current-limits)
- [Testing](#testing)

This README is the documentation. `docs/01`–`docs/14` are design notes kept for the reasoning behind the architecture; they predate the current naming and are not a reference.

## What this package does

A **message catalog** is a stable owner for UI copy (usually one Filament resource and its pages). A **binder** fills **unset** Filament slots from Laravel’s translator. An **inspector** explains why a key was chosen. An **auditor** checks completeness without clicking the panel.

Opt in per class. Classes without a `#[TranslationDomain]` attribute are left alone.

## Requirements

- PHP 8.4+
- Laravel 13
- Filament 5

## Installation

```bash
composer require syriable/laravel-translation
```

The service provider is auto-discovered. It publishes config and registers `php artisan translations:debug`, `translations:extract`, and `translations:inline`.

Publish the config file:

```bash
php artisan vendor:publish --tag=translations-config
```

### Register where your schemas live

**In a Filament panel** — register the plugin on each panel:

```php
use Syriable\Translation\TranslationPlugin;

$panel->plugin(
    TranslationPlugin::make()
        ->domainPrefixes([
            'Modules\\Billing' => 'billing',
            'App\\Filament' => 'filament',
        ])
);
```

**Outside a panel** — a Livewire schema on a public page never boots a panel, so
register its directory instead. This also starts binding, so there is nothing
else to call:

```php
use Syriable\Translation\Translations;

// in a module service provider's boot()
Translations::discoverIn(
    __DIR__.'/../Livewire/Schemas',
    'Modules\\Identity\\Livewire\\Schemas',
);
```

`discoverIn()` is idempotent, so every module can call it for its own directory,
and using both together is fine.

## Quick start

### 1. Opt in on the resource

```php
use Filament\Resources\Resource;
use Syriable\Translation\Concerns\HasModelTranslations;

class UserResource extends Resource
{
    use HasModelTranslations;
}
```

The default translation domain is `{prefix}.{basename-kebab}`. With prefix `filament`, `UserResource` becomes `filament.user-resource`.

Copy lives in `lang/{locale}/filament/user-resource.php`.

A prefix ending in `::` names a registered translation namespace instead of a folder, so a module keeps its copy in its own lang directory:

```php
TranslationPlugin::make()
    ->domainPrefixes(['Modules\\Identity' => 'identity::']);
```

`Modules\Identity\Filament\Resources\UserResource` then becomes `identity::user-resource`, written to `modules/identity/resources/lang/{locale}/user-resource.php`.

To name the domain outright rather than derive it, put `#[TranslationDomain]` on the class. It wins over the prefix map:

```php
#[TranslationDomain('identity::people')]
class UserResource extends Resource
{
    use HasModelTranslations;
}
```

### 2. Share that catalog on resource pages

Schema components resolve the catalog from the **Livewire owner** (the page), not from the resource class. Use `HasPageTranslations` on create / edit / list / view. Do **not** use `HasModelTranslations` on a page: Filament pages declare instance `getModelLabel()`, and `HasModelTranslations` declares it static.

```php
use Filament\Resources\Pages\EditRecord;
use Syriable\Translation\Concerns\HasPageTranslations;
use Syriable\Translation\Attributes\TranslationDomain;

class EditUser extends EditRecord
{
    use HasPageTranslations;
}
```

`HasPageTranslations` reads `getResource()` and reuses the resource translation domain, unless the page declares its own `#[TranslationDomain]`.

### 3. Keep identifiers in PHP

```php
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

Section::make()->key('authorization', isInheritable: false)
    ->schema([
        TextInput::make('email'),
        TextInput::make('role'),
    ]);
```

Do not call `->label()` unless you want to bypass the catalog.

### 4. Put copy in the language file

`lang/ar/filament/user-resource.php`:

```php
<?php

return [
    'model_label' => 'مستخدم',
    'plural_model_label' => 'المستخدمون',
    'navigation_label' => 'أعضاء الفريق',
    'navigation_group' => 'الوصول',
    'pages' => [
        'edit-user' => [
            'title' => 'تعديل مستخدم',
        ],
    ],
    'form' => [
        'components' => [
            'authorization' => [
                'heading' => 'الصلاحيات',
                'schema' => [
                    'email' => [
                        'label' => 'البريد الإلكتروني',
                        'helper_text' => 'يُستخدم لتسجيل الدخول.',
                    ],
                    'role' => [
                        'label' => 'الدور',
                    ],
                ],
            ],
        ],
    ],
];
```

Laravel key for that email label:

```text
filament/user-resource.form.components.authorization.schema.email.label
```

## How a message key is built

Every UI string has a **message identity**:

```text
translation domain + scope + parent machine names + leaf name + slot
```

The compiler turns that into a Laravel translation key. Schema, table, and page identities stay nested:

```text
{catalog-id-with-dots-as-slashes}.{scope}.{path…}.{name}.{slot}
```

Resource chrome (`model` / `navigation` with an empty path) compiles to a single Filament method name:

```text
{catalog-id-with-dots-as-slashes}.{model_label|plural_model_label|plural_label|navigation_label|navigation_group}
```

| Identity part | Example | Laravel key segment |
| --- | --- | --- |
| Translation domain | `filament.user-resource` | file group `filament/user-resource` |
| Scope | form | `form` |
| Wrapper | components | `components` |
| Path (parents) | section `authorization` | `authorization.schema` |
| Leaf name | field `email` | `email` |
| Slot | label | `label` |

Full key:

```text
filament/user-resource.form.components.authorization.schema.email.label
```

File on disk:

```text
lang/{locale}/filament/user-resource.php
```

Nested array:

```php
['form']['components']['authorization']['schema']['email']['label']
```

Renaming the PHP class does **not** change keys unless you change `translationDomain()`. Renaming a field **does**.

## Language file layout

One file per catalog. Resource chrome is a Filament method name at the root. Nested top-level keys are **scopes**:

| Key | Used for |
| --- | --- |
| `model_label` | `getModelLabel()` |
| `plural_model_label` | `getPluralModelLabel()` |
| `plural_label` | `getPluralLabel()` (deprecated Filament alias) |
| `navigation_label` | `getNavigationLabel()` |
| `navigation_group` | `getNavigationGroup()` |
| `pages` | Page class kebab (`edit-user`) with Filament chrome (`title`, `subheading`, `navigation_label`) and `{page}.actions` |
| `form` | Form components under `form.components`. Keyed layout children nest under `{layout}.schema` |
| `infolist` | Infolist entries under `infolist.components`, same nesting as `form` |
| `table` | Columns under `table.columns`; filters, record actions, toolbar actions, empty-state actions, and header actions |
| `actions` | Header / global actions that are not on a page or table |

### Key map

Every shipped path, in one place. `{name}` is the Filament machine name.

```text
# Resource chrome (catalog root)
model_label
plural_model_label
plural_label
navigation_label
navigation_group

# Pages — class kebab (EditUser → edit-user), not the route name (edit)
pages.{page}.title
pages.{page}.subheading                    # optional
pages.{page}.navigation_label
pages.{page}.actions.{name}.label
pages.{page}.actions.{name}.schema.components.{field}.label
pages.{page}.actions.{name}.extra_modal_footer_actions.{child}.label
pages.{page}.actions.{name}.notifications.{success|danger|info|warning}.title
pages.{page}.actions.{name}.notifications.{status}.body   # optional

# Form
form.components.{field}.label
form.components.{field}.helper_text|hint|placeholder|before_content|after_content|below_label   # optional
form.components.{field}.validation_attribute       # optional; what a validation message calls the field
form.components.{field}.actions.{name}.label
form.components.{layout}.heading|description          # keyed Section, optional
form.components.{layout}.label                        # keyed Fieldset, optional
form.components.{layout}.schema.{field}.label
form.components.{layout}.schema.actions.{name}.label
form.components.actions.{name}.label
form.components.actions.{name}.modal_heading|modal_description|modal_cancel_action_label|modal_submit_action_label   # optional
form.components.actions.{name}.schema.components.{field}.label
form.components.actions.{name}.notifications.{status}.title
form.components.actions.{name}.notifications.{status}.body   # optional

# Infolist — same nesting as form
infolist.components.{entry}.label

# Table
table.columns.{name}.label
table.columns.{name}.prefix                 # optional; TextColumn and similar
table.filters.{name}.label
table.filters.{name}.indicator              # optional
table.record_actions.{name}.label
table.toolbar_actions.{name}.label
table.empty_state_actions.{name}.label
table.header_actions.{name}.label

# Global actions (not on a page or table)
actions.{name}.label
```

Notification status is `success`, `danger`, `info`, or `warning`. Do not add `error`.

### Full example

`lang/en/billing/user-resource.php` for translation domain `billing.user-resource`:

```php
<?php

return [
    'model_label' => 'User',
    'plural_model_label' => 'Users',
    'navigation_label' => 'Team members',
    'navigation_group' => 'Access',

    'pages' => [
        'list-users' => [
            'title' => 'Users',
        ],
        'create-user' => [
            'title' => 'Create user',
        ],
        'edit-user' => [
            'navigation_label' => 'Edit user',
            'title' => 'Edit user',
            'subheading' => 'Change the user and run page actions.',
            'actions' => [
                'publish' => [
                    'label' => 'Publish',
                    'schema' => [
                        'components' => [
                            'reason' => [
                                'label' => 'Reason',
                            ],
                        ],
                    ],
                    'extra_modal_footer_actions' => [
                        'preview' => [
                            'label' => 'Preview',
                        ],
                    ],
                ],
            ],
        ],
    ],

    'form' => [
        'components' => [
            'authorization' => [
                'heading' => 'Authorization',
                'description' => 'Who can access this user.',
                'schema' => [
                    'email' => [
                        'label' => 'Email address',
                        'helper_text' => 'Used to sign in.',
                        'hint' => 'Must be unique',
                    ],
                    'role' => [
                        'label' => 'Role',
                    ],
                    'actions' => [
                        'action' => [
                            'label' => 'Do this',
                            'modal_heading' => 'Confirm this',
                            'modal_description' => 'This cannot be undone.',
                            'modal_cancel_action_label' => 'Never mind',
                            'modal_submit_action_label' => 'Save',
                            'schema' => [
                                'components' => [
                                    'name_action' => [
                                        'label' => 'Action name',
                                    ],
                                ],
                            ],
                            'notifications' => [
                                'success' => [
                                    'title' => 'Saved',
                                    'body' => 'The user was saved.',
                                ],
                                'danger' => [
                                    'title' => 'Failed',
                                    'body' => 'The user was not saved.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],

    'table' => [
        'columns' => [
            'name' => [
                'label' => 'Name',
            ],
            'email' => [
                'label' => 'E-mail',
                'prefix' => 'E-mail: ',
            ],
        ],
        'filters' => [
            'is_featured' => [
                'label' => 'Is featured',
            ],
        ],
        'record_actions' => [
            'view' => [
                'label' => 'View',
            ],
        ],
        'toolbar_actions' => [
            'bulk_action' => [
                'label' => 'Bulk action',
            ],
        ],
        'empty_state_actions' => [
            'create' => [
                'label' => 'Create user',
            ],
        ],
        'header_actions' => [
            'export' => [
                'label' => 'Export',
            ],
        ],
    ],

    'actions' => [
        'invite' => [
            'label' => 'Invite user',
        ],
    ],
];
```

### Slots inside a leaf

| Slot key | Typical Filament property | Required? |
| --- | --- | --- |
| `label` | Field, infolist entry, column, action, model, navigation. Keyed Fieldset legend is optional | Yes for fields, columns, and actions. No for Fieldset |
| `heading` | Section / empty-state heading | No |
| `title` | Page title; notification title keyed by status | Yes |
| `helper_text` | Field helper | No |
| `hint` | Field hint | No |
| `placeholder` | Placeholder | No |
| `before_content` | Field before content | No |
| `after_content` | Field after content | No |
| `modal_heading` | Action modal heading | No |
| `modal_description` | Action modal description | No |
| `modal_cancel_action_label` | Action modal cancel label | No |
| `modal_submit_action_label` | Action modal submit label | No |
| `indicator` | Table filter indicator | No |
| `prefix` | Table column prefix | No |
| `tooltip` | Tooltip | No |
| `description` | Description / empty state | No |
| `subheading` | Subheading | No |
| `group` | Navigation group | No |
| `plural` | Plural model label | No |
| `body` | Schema text content; optional notification body keyed by status | Schema text: yes. Notification: no |
| `notification_title` | Extra action notification copy via `Translations::slot()` | No |

Required slots follow [mode](#missing-message-policy) when missing. Optional slots stay empty when missing.

### Nested layouts

Only parents with a **machine name** join the path:

```php
Section::make()->key('authorization', isInheritable: false) // path segment: authorization
    ->schema([
        TextInput::make('email'), // form.components.authorization.schema.email.label
    ]);
```

A heading-only section is **unbound**. Visible English/Arabic headings are never used as keys:

```php
Section::make('Authorization') // not a path segment
    ->schema([
        TextInput::make('email'), // form.components.email.label
    ]);
```

Give layout a `->key()` (or `->messageName()`) when you want it in the catalog path.

A keyed fieldset uses `label`, not `heading`:

```php
Fieldset::make()
    ->key('authorization', isInheritable: false) // form.components.authorization.label (optional)
    ->schema([
        TextInput::make('email'), // form.components.authorization.schema.email.label
    ]);
```

Omit `form.components.authorization.label` to keep the fieldset untitled. `Fieldset::make('Authorization')` is visible copy and bypasses the catalog.

Wizard steps use `make()` as the machine name. Do not pass visible copy:

```php
Wizard::make([
    Step::make('first_step') // form.components.first_step.label
        ->schema([
            TextInput::make('name'), // form.components.first_step.schema.name.label
        ]),
]);
```

An unkeyed wizard is transparent. Key it only when the wizard itself should nest:

```php
Wizard::make([Step::make('first_step')])
    ->key('onboarding', isInheritable: false);
```

Tabs use `make()` as the machine name as well:

```php
Tabs::make('user_tabs') // form.components.user_tabs.label
    ->tabs([
        Tab::make('user') // form.components.user_tabs.schema.user.label
            ->schema([
                TextInput::make('name'), // form.components.user_tabs.schema.user.schema.name.label
            ]),
    ]);
```

Empty states use `make()` as the machine name. The constructor argument is not visible copy:

```php
EmptyState::make('user_empty_state'); // form.components.user_empty_state.heading
                                      // form.components.user_empty_state.description (optional)
```

Callouts use `make()` as the machine name. The constructor argument is not visible copy:

```php
Callout::make('alert'); // form.components.alert.heading
                        // form.components.alert.description (optional)
```

Schema text uses `make()` as the machine name. The constructor argument is not visible copy:

```php
Text::make('info'); // form.components.info.body
                    // form.components.info.tooltip (optional)
```

Field `beforeContent` / `afterContent` fill from the catalog when those keys exist, like helper text. Do not call the setters unless you intend to bypass the catalog:

```php
TextInput::make('email'); // form.components.email.before_content (optional)
                          // form.components.email.after_content (optional)
```

Schema actions use `make()` as the machine name. They sit under `form.components.actions`, not under `pages.{page}.actions`:

```php
Action::make('action')
    ->schema([
        TextInput::make('name_action'),
    ]);
// form.components.actions.action.label
// form.components.actions.action.modal_heading (optional)
// form.components.actions.action.modal_description (optional)
// form.components.actions.action.modal_cancel_action_label (optional)
// form.components.actions.action.modal_submit_action_label (optional)
// form.components.actions.action.schema.components.name_action.label
```

Table columns sit under `table.columns`. `prefix` is optional on columns that have `prefix()`:

```php
TextColumn::make('email'); // table.columns.email.label
                           // table.columns.email.prefix (optional)
```

Table filters use `make()` as the machine name. They sit under `table.filters`, not under `form`:

```php
Filter::make('is_featured'); // table.filters.is_featured.label
                             // table.filters.is_featured.indicator (optional)
```

Table actions follow the Filament slot they live in:

```php
$table
    ->recordActions([
        Action::make('view'), // table.record_actions.view.label
    ])
    ->toolbarActions([
        Action::make('bulk_action'), // table.toolbar_actions.bulk_action.label
    ])
    ->emptyStateActions([
        Action::make('create'), // table.empty_state_actions.create.label
    ])
    ->headerActions([
        Action::make('export'), // table.header_actions.export.label
    ]);
```

Hint, prefix, and suffix actions nest under the owning field:

```php
TextInput::make('email')
    ->hintAction(Action::make('hint_action')); // form.components.email.actions.hint_action.label
```

Notifications sent from an action nest under that action. `Notification::make()` has no id. Filament status (`success`, `danger`, `info`, `warning`) is the leaf:

```php
Action::make('action')
    ->action(function (): void {
        Notification::make()
            ->success()
            ->send();
    });
// form.components.actions.action.notifications.success.title
// form.components.actions.action.notifications.success.body (optional)
```

A notification with no owning action or no status is unbound. Do not call `->title()` or `->body()` unless you intend to bypass the catalog.

## Translation domains and prefixes

Default id:

```text
{prefix}.{class-basename-kebab}
```

`Modules\Billing\Filament\Resources\InvoiceResource` with prefix `billing` → `billing.invoice-resource` → file `lang/{locale}/billing/invoice-resource.php`.

### Prefix map

Longest matching namespace wins. Equal-length overlaps throw `CatalogPrefixOverlapException`.

Configure in `config/translations.php`, on the plugin, or both. Plugin values are merged on top of config (same namespace key: plugin wins).

```php
TranslationPlugin::make()
    ->domainPrefixes([
        'Modules\\Billing' => 'billing',
        'App\\Filament' => 'filament',
    ]);
```

Classes that match no namespace use `default_domain_prefix` (`filament`).

### Override the translation domain

Keep keys stable when you rename a class:

```php
public static function translationDomain(): string
{
    return 'filament.user-resource';
}
```

Point a page or relation manager at the resource catalog as shown in [Quick start](#quick-start).

## Schema domains outside resources

Not every schema belongs to a resource. A Livewire form on the public site owns its copy the same way, so it can be a catalog too.

A **schema domain** is any class that:

1. carries a `#[TranslationDomain]` attribute, and
2. exposes a public **static** `form(Schema $schema): Schema` or `configure(Schema $schema): Schema`.

```php
namespace Modules\Identity\Livewire\Schemas\User;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Syriable\Translation\Attributes\TranslationDomain;

#[TranslationDomain('identity::user-edit')]
class EditForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('email')->email(),
        ]);
    }
}
```

`translations:debug`, `translations:extract`, and `translations:inline` walk these alongside resources. `identity.user-edit` writes to `lang/{locale}/identity/user-edit.php`:

```php
return [
    'form' => [
        'components' => [
            'name' => ['label' => 'Name'],
            'email' => ['label' => 'Email'],
        ],
    ],
];
```

The named class owns the domain, not the Livewire component that renders it. A
single-file component points at it instead of repeating the literal:

```php
use Syriable\Translation\Translations;

public static function translationDomain(): string
{
    return Translations::domainFor(EditForm::class);
}
```

`HasModelTranslations` and `HasPageTranslations` stay on Filament resources and pages. A site Livewire component does not need them — the binder reads the catalog from the schema's owner.

### Register a directory, not a class list

Discovery mirrors `discoverResources()`: name a directory and its PSR-4 namespace **once per module**. Never enumerate every form class.

From the panel plugin:

```php
TranslationPlugin::make()
    ->domainPrefixes(['Modules\\Identity' => 'identity'])
    ->discoverIn(
        in: base_path('modules/identity/src/Livewire/Schemas'),
        for: 'Modules\\Identity\\Livewire\\Schemas',
    );
```

From a module service provider, for schemas that never appear in a panel:

```php
use Syriable\Translation\Translations;

public function boot(): void
{
    Translations::discoverIn(
        __DIR__.'/../Livewire/Schemas',
        'Modules\\Identity\\Livewire\\Schemas',
    );
}
```

`Translations::discoverIn()` also starts binding, which the panel plugin does on boot.
Outside a panel nothing else does, so this one call is all a module needs.

Or from `config/translations.php`:

```php
'discover_paths' => [
    [
        'path' => base_path('modules/identity/src/Livewire/Schemas'),
        'namespace' => 'Modules\\Identity\\Livewire\\Schemas',
    ],
],
```

A directory is scanned once however many times it is registered. Subdirectories are included. Classes are collected when they carry a `#[TranslationDomain]` attribute **and** expose a static schema builder; everything else in the directory is skipped, so a plain schema class or a catalog with no builder costs nothing. Abstract classes and Filament resources are skipped too — resources keep the richer resource walk with chrome, pages, and table.

### Translation domains are dotted

A translation domain is dotted segments, and only dotted segments:

| Id | File | |
| --- | --- | --- |
| `identity.user-edit` | `lang/{locale}/identity/user-edit.php` | ✅ |
| `identity::users.edit` | — | ❌ throws `InvalidTranslationDomainException` |

A namespaced translation key (`vendor::group.key`) is not a translation domain. Discovery throws `InvalidTranslationDomainException` on one rather than writing a file you did not mean.

### What is walked

The schema the builder returns, under the `form` scope, **and the chrome a
second builder wraps it in**. Pruning is scoped to `form`: a component you
delete loses its key on the next `translations:extract`, and keys in other
scopes are left alone. Model, navigation, page, and table chrome stay
exclusive to resources.

Every child schema of a component is walked, not only its default one, so copy
in a section's footer or header is reached like any other.

### The chrome builder

A form class often describes its wrapper separately from its fields — the
section around them, its heading, its footer — in a builder that takes no
`Schema`. The schema walk cannot reach that, so its copy used to be written by
hand. A public **static** method named `make()` taking no required argument and
returning a `Filament\Schemas\Components\Component` is now walked too:

```php
#[TranslationDomain('auth::login')]
final class LoginForm
{
    public static function make(): Form
    {
        return Form::make([
            Section::make()
                ->key('login')                        // the machine name
                ->schema([EmbeddedSchema::make('form')])
                ->footer([Text::make('terms')]),
        ]);
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('email')]);
    }
}
// form.components.login.heading                   optional, never stubbed
// form.components.login.schema.terms.body
// form.components.login.schema.email.label        the field, under the section
```

A keyed wrapper lends its segment to the schema it embeds, exactly as it does
to the components beside it: the field above reads
`form.components.login.schema.email.label`, the same path its neighbour in the
footer reads. Being reached through an `EmbeddedSchema` is how Filament renders
a field, not where the field lives.

**Key a wrapper only when you mean to.** An unkeyed `Section::make()` adds
nothing, so chrome without keys leaves every existing key where it is; adding
`->key()` to a wrapper that already has copy underneath moves those keys, and
the copy has to move with them. `translations:extract` writes the new keys and
prunes the old ones, so run it and check the diff.

A chrome builder that throws, one reading the signed-in user say, leaves the
`form` scope unpruned for that catalog rather than letting its keys look
orphaned. Deleting live copy is the one failure a rerun cannot undo.

### A module with no language directory

A module keeps its copy under its own translation namespace, and Laravel knows
that namespace only because something registered it — which a module package
does only once `resources/lang` is there. A module that has never been
translated therefore has no namespace, and `translations:extract`, the command
whose job is to write its first language file, used to stop on it:

```
Catalog id [billing::invoice] is namespaced under [billing], but no translation
namespace by that name is registered.
```

Extraction now registers the namespace itself and carries on, reporting it:

```
Registered the translation namespace [billing] at modules/billing/resources/lang;
the module had no language directory yet.
```

The module has to exist: the name is looked up through the module package when
one is installed, and otherwise under [`module_path`](#configuration). A name
matching no module is still unknown and still throws, so a typo stays loud.

Only the namespace is registered — the directory arrives with the first file
written into it, so `--dry-run` writes nothing. That first file is also the
lasting fix, because the directory is what the module package looks for when it
registers the namespace on the next boot.

## What is bound automatically

After `TranslationPlugin` boots, the binder fills **unset** slots on:

| Component | Slots | When it binds |
| --- | --- | --- |
| `Filament\Forms\Components\Field` | `label`, `helper_text`, `hint`, `placeholder`, `before_content`, `after_content`, `below_label`, `validation_attribute` | Label if `hasCustomLabel()` is false; hint if `hasHint()` is false; helper, placeholder, below label, before content, and after content from catalog unless you set them after `make()`; placeholder only when the field has `placeholder()`; `validation_attribute` names the field in validation messages, and with no key Filament keeps its own default, the label lowercased — so write it only where the two differ |
| `Filament\Infolists\Components\Entry` | `label`, `helper_text`, `hint`, `placeholder`, `before_content`, `after_content` | Same rules as fields; `TextEntry::make('info')` is the identifier |
| `Filament\Schemas\Components\Section` | `heading`, `description` | Only when the heading is empty; optional; omit the catalog keys to keep the section untitled; description is optional, like helper text; use `->key()` for the machine name |
| `Filament\Schemas\Components\Fieldset` | `label` | Only when the label is unset; optional; `Fieldset::make()->key('authorization')` fills `form.components.authorization.label`; omit the key to keep the fieldset untitled; children nest under `{layout}.schema` |
| `Filament\Schemas\Components\Wizard` | `label` | When the wizard is keyed and the label is unset; optional, like a section heading: `translations:extract` keeps the key if you write one but never stubs it |
| `Filament\Schemas\Components\Wizard\Step` | `label` | `Step::make('machine_name')` is the identifier; catalog copy fills the visible label unless `->label()` is set after `make()` |
| `Filament\Schemas\Components\Tabs` | `label` | `Tabs::make('machine_name')` is the identifier |
| `Filament\Schemas\Components\Tabs\Tab` | `label` | `Tab::make('machine_name')` is the identifier; same override rule as steps |
| `Filament\Schemas\Components\EmptyState` | `heading`, `description` | `EmptyState::make('machine_name')` is the identifier; catalog heading fills unless `->heading()` is set after `make()`; description is optional |
| `Filament\Schemas\Components\Callout` | `heading`, `description` | `Callout::make('machine_name')` is the identifier; catalog heading fills unless `->heading()` is set after `make()`; description is optional; a visible sentence in `make()` is left as copy |
| `Filament\Schemas\Components\Text` | `body`, `tooltip` | `Text::make('machine_name')` is the identifier; catalog body fills the visible content unless `->content()` is set after `make()`; tooltip is optional |
| `Filament\Actions\Action` | `label`, `modal_heading`, `modal_description`, `modal_cancel_action_label`, `modal_submit_action_label` | Schema-embedded: `form.components.actions.{name}` (or `form.components.{layout}.schema.actions.{name}`); field hint/prefix/suffix: `form.components.{parents}.schema.{field}.actions.{name}`; table record: `table.record_actions.{name}`; table toolbar: `table.toolbar_actions.{name}`; table empty state: `table.empty_state_actions.{name}`; table header: `table.header_actions.{name}`; page header: `pages.{page-class-kebab}.actions.{name}` (modal fields: `schema.components.{field}`; extra footer actions: `extra_modal_footer_actions.{name}`); otherwise `actions.{name}`. Catalog copy when present; otherwise the captured Filament / vendor label. Modal chrome is optional, like helper text: omit the keys to keep Filament defaults (heading falls back to the action label; cancel/submit fall back to Filament vendor strings). Do not call empty `->modalHeading()` unless you intend to bypass the catalog |
| `Filament\Notifications\Notification` | `title`, `body` | Sent from an action: `{actionPath}.notifications.{status}`; `Notification::make()->success()` has no id; catalog title fills unless `->title()` is set after `make()`; body is optional; no owning action or no status is unbound |
| `Filament\Tables\Columns\Column` | `label`, `prefix` | Path is `table.columns.{name}`; catalog label fills unless `->label()` is set after `make()`; `prefix` is optional on columns that have `prefix()`, like helper text |
| `Filament\Tables\Filters\BaseFilter` | `label`, `indicator` | `Filter::make('is_featured')` is the identifier; path is `table.filters.{name}`; catalog label fills unless `->label()` is set after `make()`; indicator is optional and falls back to the filter label |

The Livewire owner must carry a `#[TranslationDomain]` attribute, or expose a `translationDomain()` method. Notifications inherit the catalog from the action that sent them. Otherwise the decision is `no_catalog` and Filament defaults stay.

Vendor actions such as `DeleteAction` keep their Filament language file until **your** catalog defines that action’s label.

### Components from other packages

Filament is extensible, and a component another package ships — a separator, a
divider, anything extending `Filament\Schemas\Components\Component` with a
label — is bound too, on one condition: **it has to name itself with
`->key()`.**

```php
Separator::make()->key('separator');
// form.components.separator.label
```

The key is required rather than guessed. A component with no key has no
identity, and the `make()` argument is not a safe substitute — on a separator
that argument *is* the visible label, not a name.

Whatever label the component carries when it is built stays as the fallback,
so a component that sets its own default keeps it until the catalog has a
line. An explicit `->label()` after `make()` wins, as everywhere else.

## Resource chrome

`HasModelTranslations` is for **resources** (static Filament chrome):

| Method | Identity |
| --- | --- |
| `getModelLabel()` | `model_label` |
| `getPluralModelLabel()` | `plural_model_label` |
| `getPluralLabel()` | `plural_label` |
| `getNavigationLabel()` | `navigation_label` |
| `getNavigationGroup()` | `navigation_group` |

`HasPageTranslations` is for **resource pages**. It shares the resource catalog unless the page declares its own `#[TranslationDomain]`. Page keys use the class kebab (`EditUser` → `edit-user`), not the registered route name (`edit`):

| Method | Identity |
| --- | --- |
| `getTitle()` | `pages.{page}.title` |
| `getSubheading()` | `pages.{page}.subheading` |
| `getNavigationLabel()` | `pages.{page}.navigation_label` |

If the message is missing, the traits fall back to the parent Filament implementation.

## Missing-message policy

What happens when a message has no line in the language file. Set it globally
with `TRANSLATIONS_ON_MISSING` / `config('translations.on_missing')`, or per panel:

```php
use Syriable\Translation\Enums\MissingMessagePolicy;

TranslationPlugin::make()->onMissing(MissingMessagePolicy::Debug);
```

| Policy | Missing **required** slot (`label`, `title`, schema `body`) | Missing **optional** slot |
| --- | --- | --- |
| `keep_vendor_label` (default) | Keep Filament's own text | Empty |
| `debug` | Render the compiled key in the UI so you can paste it into a language file | Empty |
| `strict` | Throw `MissingMessageException` | Empty |

`keep_vendor_label` is the default because `debug` puts raw keys in front of users: ship a
screen before its copy and someone reads
`identity::user-edit.form.components.tabs-user.label`. Use `debug` locally when
you want to see what to add, and `translations:debug` to find gaps anywhere.

A key present only in the **fallback locale** — English while the app runs in
Arabic, say — is **not** treated as present in the current locale. The outcome is
`used_fallback_locale`, no policy rewrites that text, and you still see the
English string. `translations:debug --fail-on-fallback` fails CI until the current locale has
its own line.

An unknown `TRANSLATIONS_ON_MISSING` value falls back to `keep_vendor_label`.

## Overrides

Priority, highest first:

1. An explicit Filament setter after `make()` — `->label('Work email')` always wins for that slot.
2. `->messageName('…')` / `->domain('…')` change identity, not the visible string.
3. Inferred identity from machine names + catalog.

```php
TextInput::make('email');                          // leaf = email
TextInput::make('email')->messageName('billing_email'); // leaf = billing_email
TextInput::make('email')->label('Work email');     // catalog not used for label

AddressSchema::make()->domain('shared.address');   // this subtree uses another translation domain
```

### Placeholders in bound copy

The binder reads a line from the catalog with no replacements, so a line
holding `:name` would render the placeholder. `messageReplace()` declares what
to put there, and the slot stays automatic:

```php
Text::make('terms')->messageReplace([
    'privacy_url' => fn (): string => route('privacy'),
    'terms_url' => fn (): string => route('terms'),
]);
// 'Read our :privacy_url and :terms_url.'
```

A closure is called when the slot renders, not when the component is built, so
a URL, a count or a signed-in user's name is current. A plain value is used as
given. Replacements apply per component and never enter the resolution cache,
which is keyed by identity and locale.

Without a declared replacement nothing changes, and a slot the catalog did not
supply is left alone — `messageReplace()` never turns a missing message into a
present one.

### Copy that is markup

Filament escapes a `Text` body, which is what copy should be: a line is text,
not HTML. Footer copy that carries a link is the exception, and it says so at
the call site:

```php
Text::make('footer')
    ->messageHtml()
    ->messageReplace(['terms_url' => fn (): string => route('terms')]);
// 'By signing in you accept our <a href=":terms_url">terms</a>.'
```

`messageHtml()` marks **this component's** line as markup, so it renders as
markup. Nothing is guessed: a catalog line is never treated as HTML because it
happens to contain a tag, or one translator's `<` would quietly stop a whole
catalog being escaped.

Replacements are the reason the opt-in is per component rather than per line.
The line is the author's, but what is poured into it may not be, so under
`messageHtml()` every replacement is escaped — pass an `Illuminate\Support\HtmlString`
when a replacement is meant to be markup of its own.

`messageName()`, `domain()`, `messageReplace()` and `messageHtml()` are macros on `Filament\Support\Components\Component`. They store bindings in a `WeakMap` (no dynamic properties on Filament objects).

## Manual lookup

For option labels and other 1% cases the binder does not hook:

```php
use Syriable\Translation\Enums\MessageSlot;
use Syriable\Translation\Translations;

Select::make('role')
    ->options([
        'admin' => Translations::slot($this, MessageSlot::Label, relative: 'options.admin'),
        'member' => Translations::slot($this, MessageSlot::Label, relative: 'options.member'),
    ]);

Translations::slot($action, MessageSlot::NotificationTitle, relative: 'success');
Translations::slot($field, MessageSlot::Label, replace: ['name' => $user->name]);
Translations::slot($field, MessageSlot::Label, number: $count);
```

`relative` is appended to the compiled key (dots become nested array keys). `replace` and `number` use Laravel `__()` / `trans_choice()` on the compiled key.

## Explaining a resolution

```php
use Syriable\Translation\Enums\MessageSlot;
use Syriable\Translation\Binding\ResolutionExplainer;

$resolution = ResolutionExplainer::explain($component, MessageSlot::Label);

$resolution->key;      // filament/user-resource.form.components.email.label
$resolution->decision; // bound | missing | used_fallback_locale | no_catalog | unbound | …
$resolution->text;
$resolution->reason;
$resolution->presentInCurrentLocale;
$resolution->presentInFallbackLocale;
```

Use this when a label looks wrong and you need to know which identity was chosen.

## translations:debug

```bash
php artisan translations:debug
php artisan translations:debug --locale=ar
php artisan translations:debug --locale=ar --fail-on-missing --fail-on-fallback
```

Walks opted-in Filament resources: form fields, table columns/filters/actions, action notification titles from `Notification::make()->success()` (and danger/info/warning) inside action closures, model and navigation labels, and page titles. It boots the registered panels itself, so no request or panel setup is needed.

| Option | Effect |
| --- | --- |
| `--locale=` | Switch locale for the run, then restore |
| `--fail-on-missing` | Exit `1` if any required message is missing |
| `--fail-on-fallback` | Exit `1` if the current locale is using the fallback locale’s copy |

A clean run prints `No message issues found.`

You can also audit a list of identities in PHP:

```php
use Syriable\Translation\Extraction\MessageScanner;

app(MessageScanner::class)->auditIdentities([$identity]);
```

Or audit a list of components you built yourself, against a domain of your
choosing — useful for asserting your own schemas resolve in a test:

```php
use Syriable\Translation\Extraction\MessageScanner;

$findings = app(MessageScanner::class)->auditComponents(
    $schema->getComponents(),
    'identity::user-edit',
);
```

Each finding is `['key' => …, 'catalog' => …, 'decision' => …, 'locale' => …, 'text' => …]`.

## translations:extract

```bash
php artisan translations:extract
php artisan translations:extract --locale=ar
php artisan translations:extract --locale=en,ar
php artisan translations:extract --locale=ar --dry-run
php artisan translations:extract --no-prune
```

Creates missing catalog language files and nested keys. It also **removes** keys whose component is no longer in the walked resource (for example `Text::make('or')` after you delete that component). It does **not** overwrite copy that is already in the file. Optional slots under a live component stay (`placeholder`, `options.*`, notification `body`). Page action keys stay while that page is still registered. Missing keys get a humanized stub (`is_featured` → `Is featured`). A key present only in the fallback locale is copied into the current locale file so you can translate it.

If `form()` or `table()` throws, that scope is not pruned. `syncIdentities()` never prunes.

| Option | Effect |
| --- | --- |
| `--locale=` | Locale to write. Comma-separated for several locales. Default: app locale |
| `--dry-run` | Print what would be created or removed, write nothing |
| `--no-prune` | Create missing keys only; keep language keys for deleted components |

It walks registered Filament resources **and** discovered [schema domains](#schema-domains-outside-resources) in one pass — there is no separate command for them.

The commands boot every registered panel before they walk it. A panel plugin is configured in `Panel::boot()`, which a console command never triggers on its own, so without this the CLI would read no domain prefixes, no plugin discovery paths and no missing-message policy — and write copy to a different file than the browser reads from.

Keep `translations:debug` for CI. Use `translations:extract` while scaffolding a resource, then replace stubs with real copy.

## translations:inline

```bash
php artisan translations:inline
php artisan translations:inline --locale=en
php artisan translations:inline --locale=en --dry-run
```

The reverse of `translations:extract`. Reads keys that **already exist** in the catalog language file and writes matching Filament setters onto `::make('…')` chains, plus resource chrome methods (`getModelLabel()`, `getPluralModelLabel()`, `getPluralLabel()`, `getNavigationLabel()`, `getNavigationGroup()`) and page `getTitle()`, `getSubheading()`, and `getNavigationLabel()` when those keys exist.

```php
TextInput::make('name')
    ->label(__('filament/user-resource.form.components.name.label'))
    ->placeholder(__('filament/user-resource.form.components.name.placeholder'))
    ->required(),

Action::make('action')
    ->action(function (): void {
        Notification::make()
            ->title(__('filament/user-resource.form.components.actions.action.notifications.success.title'))
            ->success()
            ->send();
    }),

public static function getModelLabel(): string
{
    return __('filament/user-resource.model_label');
}
```

`__()` is required so Laravel loads the copy. A raw string of the catalog key is rewritten to `__('…')`. Other existing setters (`->label('Name')`) are left alone. These explicit calls **bypass the binder** for those slots.

| Option | Effect |
| --- | --- |
| `--locale=` | Which language file to read keys from. Default: app locale |
| `--dry-run` | Print what would be written, change no PHP |

It walks registered Filament resources and discovered [schema domains](#schema-domains-outside-resources). A schema domain's setters are written into that catalog class's own file.

Identifier-only PHP remains the default. Use `translations:inline` only when you want keys visible in PHP.

## Configuration

`config/translations.php` (after publish):

| Key | Env | Default | Role |
| --- | --- | --- | --- |
| `on_missing` | `TRANSLATIONS_ON_MISSING` | `keep_vendor_label` | `keep_vendor_label`, `debug`, or `strict` — see [policy](#missing-message-policy) |
| `default_domain_prefix` | — | `filament` | Prefix when no namespace map matches |
| `domain_prefixes` | — | `[]` | `['Modules\\Billing' => 'billing']` |
| `discover_paths` | — | `[]` | `[['path' => …, 'namespace' => …]]` — directories scanned for [schema domains](#schema-domains-outside-resources) |
| `module_path` | — | `modules` | Where modules live, relative to the base path — see [a module with no language directory](#a-module-with-no-language-directory) |
| `max_parent_depth` | — | `32` | Cap when walking parent schema components; exceeding it throws `ParentDepthExceededException` |

Panel plugin options:

```php
TranslationPlugin::make()
    ->domainPrefixes([/* … */])   // merged over config
    ->discoverIn(                 // merged with config paths
        in: base_path('modules/identity/src/Livewire/Schemas'),
        for: 'Modules\\Identity\\Livewire\\Schemas',
    )
    ->onMissing(MissingMessagePolicy::Debug);   // overrides config for this panel
```

## Name rules

- Machine names are lowercased.
- Dots in Filament names become `__` so Laravel does not nest the key: `author.name` → `author__name` → `form.components.author__name.label`.
- Valid segments: `a-z`, `0-9`, `_`, `-`, and `__` as the dot stand-in. Spaces and other punctuation throw `InvalidMessageNameException`.
- Prefer `->key('authorization', isInheritable: false)` on sections instead of putting copy in `Section::make('…')`.

## PHPStan

`messageName()` and `domain()` are runtime macros. This package ships `stubs/filament-components.stub`. Point PHPStan at it:

```neon
parameters:
    stubFiles:
        - vendor/syriable/laravel-translation/stubs/filament-components.stub
```

Inside this monorepo the stub is already listed in the application `phpstan.neon`.

## Current limits

The package binds the slots listed above. Widgets, relation-manager chrome, and import/export are not hooked yet; use `->label()` / `Translations::slot()` there.

`translations:debug` and `translations:extract` walk registered resource forms, tables, action notification titles from action closures, model/navigation chrome, and page titles, plus discovered schema domains. They do not yet walk widgets or relation-manager chrome.

Heading-only sections never become catalog path segments.

## Testing

Run the suite **from the package**, not from the host application:

```bash
composer install
composer test
```

## License

MIT. See `LICENSE`.
