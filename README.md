# syriable/filament-auto-translator

Stable phrase catalogs for Filament panel UI copy.

Filament already translates strings. This package removes the work of inventing and threading keys through every `label()`, heading, hint, and nested action. PHP keeps machine names. Language files keep the copy.

This is **not** machine translation. It does not translate Eloquent records. It does not replace Filament vendor language files (`filament::` / `filament-panels::`).

```text
PHP: identifiers only          lang/{locale}/…: copy
TextInput::make('email')  →    'form' => ['components' => ['email' => ['label' => 'البريد']]]
```

- [What this package does](#what-this-package-does)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [How a phrase key is built](#how-a-phrase-key-is-built)
- [Language file layout](#language-file-layout)
- [Catalog ids and prefixes](#catalog-ids-and-prefixes)
- [What is bound automatically](#what-is-bound-automatically)
- [Resource chrome](#resource-chrome)
- [Modes](#modes)
- [Overrides](#overrides)
- [Manual lookup](#manual-lookup)
- [Inspector](#inspector)
- [Audit command](#audit-command)
- [Sync command](#sync-command)
- [Apply command](#apply-command)
- [Configuration](#configuration)
- [Name rules](#name-rules)
- [PHPStan](#phpstan)
- [Current limits](#current-limits)
- [Testing](#testing)

Shipped developer docs are this README. `docs/01`–`docs/14` are the original design notes; they can lag the grammar here.

## What this package does

A **phrase catalog** is a stable owner for UI copy (usually one Filament resource and its pages). A **binder** fills **unset** Filament slots from Laravel’s translator. An **inspector** explains why a key was chosen. An **auditor** checks completeness without clicking the panel.

Opt in per class. Classes that do not implement `PhraseCatalog` are left alone.

## Requirements

- PHP 8.4+
- Laravel 13
- Filament 5

## Installation

```bash
composer require syriable/filament-auto-translator
```

The service provider is auto-discovered. It publishes config and registers `php artisan phrases:audit`, `phrases:sync`, and `phrases:apply`. Binding hooks are registered automatically when the package boots — no panel or plugin required. This also covers Livewire components that implement `HasSchemas` / `HasActions` outside of any Resource (standalone pages, custom components): as long as the class implements `PhraseCatalog`, its slots bind.

Publish the config file:

```bash
php artisan vendor:publish --tag=auto-translator-config
```

Register the plugin on each Filament panel:

```php
use Syriable\Filament\Plugins\AutoTranslator\PhrasePlugin;

$panel->plugin(
    PhrasePlugin::make()
        ->catalogPrefixes([
            'Modules\\Billing' => 'billing',
            'App\\Filament' => 'filament',
        ])
);
```

The plugin is only needed for **panel-scoped** overrides — `catalogPrefixes()` and `mode()` merged on top of `config/auto-translator.php` for that specific panel. Binding itself (resource chrome, schema, table, and action slots) does not depend on the plugin or on any panel booting; it works from the published config alone.

## Quick start

### 1. Opt in on the resource

```php
use Filament\Resources\Resource;
use Syriable\Filament\Plugins\AutoTranslator\Concerns\BindsPhrases;
use Syriable\Filament\Plugins\AutoTranslator\Contracts\PhraseCatalog;

class UserResource extends Resource implements PhraseCatalog
{
    use BindsPhrases;
}
```

The default catalog id is `{prefix}.{basename-kebab}`. With prefix `filament`, `UserResource` becomes `filament.user-resource`.

Copy lives in `lang/{locale}/filament/user-resource.php`.

### 2. Share that catalog on resource pages

Schema components resolve the catalog from the **Livewire owner** (the page), not from the resource class. Use `BindsPagePhrases` on create / edit / list / view. Do **not** use `BindsPhrases` on a page: Filament pages declare instance `getModelLabel()`, and `BindsPhrases` declares it static.

```php
use Filament\Resources\Pages\EditRecord;
use Syriable\Filament\Plugins\AutoTranslator\Concerns\BindsPagePhrases;
use Syriable\Filament\Plugins\AutoTranslator\Contracts\PhraseCatalog;

class EditUser extends EditRecord implements PhraseCatalog
{
    use BindsPagePhrases;
}
```

`BindsPagePhrases` reads `getResource()` and reuses the resource catalog id.

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

## How a phrase key is built

Every UI string has a **phrase identity**:

```text
catalog id + scope + parent machine names + leaf name + slot
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
| Catalog id | `filament.user-resource` | file group `filament/user-resource` |
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

Renaming the PHP class does **not** change keys unless you change `phraseCatalogId()`. Renaming a field **does**.

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
form.components.{field}.helper_text|hint|placeholder|before_content|after_content   # optional
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

`lang/en/billing/user-resource.php` for catalog id `billing.user-resource`:

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
| `notification_title` | Extra action notification copy via `Phrase::slot()` | No |

Required slots follow [mode](#modes) when missing. Optional slots stay empty when missing.

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

Give layout a `->key()` (or `->phrase()`) when you want it in the catalog path.

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

## Catalog ids and prefixes

Default id:

```text
{prefix}.{class-basename-kebab}
```

`Modules\Billing\Filament\Resources\InvoiceResource` with prefix `billing` → `billing.invoice-resource` → file `lang/{locale}/billing/invoice-resource.php`.

### Prefix map

Longest matching namespace wins. Equal-length overlaps throw `CatalogPrefixOverlapException`.

Configure in `config/auto-translator.php`, on the plugin, or both. Plugin values are merged on top of config (same namespace key: plugin wins).

```php
PhrasePlugin::make()
    ->catalogPrefixes([
        'Modules\\Billing' => 'billing',
        'App\\Filament' => 'filament',
    ]);
```

Classes that match no namespace use `default_prefix` (`filament`).

### Override the catalog id

Keep keys stable when you rename a class:

```php
public static function phraseCatalogId(): string
{
    return 'filament.user-resource';
}
```

Point a page or relation manager at the resource catalog as shown in [Quick start](#quick-start).

## What is bound automatically

After `PhrasePlugin` boots, the binder fills **unset** slots on:

| Component | Slots | When it binds |
| --- | --- | --- |
| `Filament\Forms\Components\Field` | `label`, `helper_text`, `hint`, `placeholder`, `before_content`, `after_content` | Label if `hasCustomLabel()` is false; hint if `hasHint()` is false; helper, placeholder, before content, and after content from catalog unless you set them after `make()`; placeholder only when the field has `placeholder()` |
| `Filament\Infolists\Components\Entry` | `label`, `helper_text`, `hint`, `placeholder`, `before_content`, `after_content` | Same rules as fields; `TextEntry::make('info')` is the identifier |
| `Filament\Schemas\Components\Section` | `heading`, `description` | Only when the heading is empty; optional; omit the catalog keys to keep the section untitled; description is optional, like helper text; use `->key()` for the machine name |
| `Filament\Schemas\Components\Fieldset` | `label` | Only when the label is unset; optional; `Fieldset::make()->key('authorization')` fills `form.components.authorization.label`; omit the key to keep the fieldset untitled; children nest under `{layout}.schema` |
| `Filament\Schemas\Components\Wizard` | `label` | When the wizard is keyed and the label is unset |
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

The Livewire owner must implement `PhraseCatalog`. Notifications inherit the catalog from the action that sent them. Otherwise the decision is `no_catalog` and Filament defaults stay.

Vendor actions such as `DeleteAction` keep their Filament language file until **your** catalog defines that action’s label.

## Resource chrome

`BindsPhrases` is for **resources** (static Filament chrome):

| Method | Identity |
| --- | --- |
| `getModelLabel()` | `model_label` |
| `getPluralModelLabel()` | `plural_model_label` |
| `getPluralLabel()` | `plural_label` |
| `getNavigationLabel()` | `navigation_label` |
| `getNavigationGroup()` | `navigation_group` |

`BindsPagePhrases` is for **resource pages**. It shares the resource catalog. Page keys use the class kebab (`EditUser` → `edit-user`), not the registered route name (`edit`):

| Method | Identity |
| --- | --- |
| `getTitle()` | `pages.{page}.title` |
| `getSubheading()` | `pages.{page}.subheading` |
| `getNavigationLabel()` | `pages.{page}.navigation_label` |

If the phrase is missing, the traits fall back to the parent Filament implementation.

## Modes

Set globally with `PHRASE_MODE` / `config('auto-translator.mode')`, or per panel:

```php
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseMode;

PhrasePlugin::make()->mode(PhraseMode::Inspect);
```

| Mode | Missing **required** slot (`label`, `title`, schema `body`) | Missing **optional** slot |
| --- | --- | --- |
| `inspect` (default) | Render the compiled key in the UI so you can paste it into a lang file | Empty |
| `strict` | Throw `MissingPhraseException` | Empty |
| `lenient` | Keep Filament’s default text | Empty |

A key present only in the **fallback** locale (for example English while the app locale is Arabic) is **not** treated as present in the current locale. Decision: `used_fallback`. Inspect/strict do not rewrite that text; you still see the fallback string. `phrases:audit --fail-on-fallback` fails CI until the current locale has its own line.

Unknown `PHRASE_MODE` values fall back to `inspect`.

## Overrides

Priority, highest first:

1. An explicit Filament setter after `make()` — `->label('Work email')` always wins for that slot.
2. `->phrase('…')` / `->catalog('…')` change identity, not the visible string.
3. Inferred identity from machine names + catalog.

```php
TextInput::make('email');                          // leaf = email
TextInput::make('email')->phrase('billing_email'); // leaf = billing_email
TextInput::make('email')->label('Work email');     // catalog not used for label

AddressSchema::make()->catalog('shared.address');  // this subtree uses another catalog id
```

`phrase()` and `catalog()` are macros on `Filament\Support\Components\Component`. They store bindings in a `WeakMap` (no dynamic properties on Filament objects).

## Manual lookup

For option labels and other 1% cases the binder does not hook:

```php
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseSlot;
use Syriable\Filament\Plugins\AutoTranslator\Phrase;

Select::make('role')
    ->options([
        'admin' => Phrase::slot($this, PhraseSlot::Label, relative: 'options.admin'),
        'member' => Phrase::slot($this, PhraseSlot::Label, relative: 'options.member'),
    ]);

Phrase::slot($action, PhraseSlot::NotificationTitle, relative: 'success');
Phrase::slot($field, PhraseSlot::Label, replace: ['name' => $user->name]);
Phrase::slot($field, PhraseSlot::Label, number: $count);
```

`relative` is appended to the compiled key (dots become nested array keys). `replace` and `number` use Laravel `__()` / `trans_choice()` on the compiled key.

## Inspector

```php
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseSlot;
use Syriable\Filament\Plugins\AutoTranslator\Inspection\PhraseInspector;

$resolution = PhraseInspector::explain($component, PhraseSlot::Label);

$resolution->key;      // filament/user-resource.form.components.email.label
$resolution->decision; // bound | missing | used_fallback | no_catalog | unbound | …
$resolution->text;
$resolution->reason;
$resolution->presentInCurrentLocale;
$resolution->presentInFallbackLocale;
```

Use this when a label looks wrong and you need to know which identity was chosen.

## Audit command

```bash
php artisan phrases:audit
php artisan phrases:audit --locale=ar
php artisan phrases:audit --locale=ar --fail-on-missing --fail-on-fallback
```

Walks opted-in Filament resources: form fields, table columns/filters/actions, action notification titles from `Notification::make()->success()` (and danger/info/warning) inside action closures, model and navigation labels, and page titles. Requires a booted panel with registered resources.

| Option | Effect |
| --- | --- |
| `--locale=` | Switch locale for the run, then restore |
| `--fail-on-missing` | Exit `1` if any required phrase is missing |
| `--fail-on-fallback` | Exit `1` if the current locale is using the fallback locale’s copy |

A clean run prints `No phrase issues found.`

You can also audit a list of identities in PHP:

```php
use Syriable\Filament\Plugins\AutoTranslator\Audit\PhraseAuditor;

app(PhraseAuditor::class)->auditIdentities([$identity]);
```

## Sync command

```bash
php artisan phrases:sync
php artisan phrases:sync --locale=ar
php artisan phrases:sync --locale=en,ar
php artisan phrases:sync --locale=ar --dry-run
php artisan phrases:sync --no-prune
```

Creates missing catalog language files and nested keys. It also **removes** keys whose component is no longer in the walked resource (for example `Text::make('or')` after you delete that component). It does **not** overwrite copy that is already in the file. Optional slots under a live component stay (`placeholder`, `options.*`, notification `body`). Page action keys stay while that page is still registered. Missing keys get a humanized stub (`is_featured` → `Is featured`). A key present only in the fallback locale is copied into the current locale file so you can translate it.

If `form()` or `table()` throws, that scope is not pruned. `syncIdentities()` never prunes.

| Option | Effect |
| --- | --- |
| `--locale=` | Locale to write. Comma-separated for several locales. Default: app locale |
| `--dry-run` | Print what would be created or removed, write nothing |
| `--no-prune` | Create missing keys only; keep language keys for deleted components |

Keep `phrases:audit` for CI. Use `phrases:sync` while scaffolding a resource, then replace stubs with real copy.

## Apply command

```bash
php artisan phrases:apply
php artisan phrases:apply --locale=en
php artisan phrases:apply --locale=en --dry-run
```

The reverse of `phrases:sync`. Reads keys that **already exist** in the catalog language file and writes matching Filament setters onto `::make('…')` chains, plus resource chrome methods (`getModelLabel()`, `getPluralModelLabel()`, `getPluralLabel()`, `getNavigationLabel()`, `getNavigationGroup()`) and page `getTitle()`, `getSubheading()`, and `getNavigationLabel()` when those keys exist.

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

Identifier-only PHP remains the default. Use `phrases:apply` only when you want keys visible in PHP.

## Configuration

`config/auto-translator.php` (after publish):

| Key | Env | Default | Role |
| --- | --- | --- | --- |
| `mode` | `PHRASE_MODE` | `inspect` | `inspect`, `strict`, or `lenient` |
| `default_prefix` | — | `filament` | Prefix when no namespace map matches |
| `catalog_prefixes` | — | `[]` | `['Modules\\Billing' => 'billing']` |
| `inspect_query` | — | `phrases` | Reserved query-string key for request-level dumps (not consumed by the binder yet) |
| `max_parent_depth` | — | `32` | Cap when walking parent schema components; exceeding it throws `ParentDepthExceededException` |

Panel plugin options:

```php
PhrasePlugin::make()
    ->catalogPrefixes([/* … */])  // merged over config
    ->mode(PhraseMode::Inspect);  // overrides config for this panel
```

## Name rules

- Machine names are lowercased.
- Dots in Filament names become `__` so Laravel does not nest the key: `author.name` → `author__name` → `form.components.author__name.label`.
- Valid segments: `a-z`, `0-9`, `_`, `-`, and `__` as the dot stand-in. Spaces and other punctuation throw `InvalidPhraseNameException`.
- Prefer `->key('authorization', isInheritable: false)` on sections instead of putting copy in `Section::make('…')`.

## PHPStan

`phrase()` and `catalog()` are runtime macros. This package ships `stubs/filament-components.stub`. Point PHPStan at it:

```neon
parameters:
    stubFiles:
        - vendor/syriable/filament-auto-translator/stubs/filament-components.stub
```

Inside this monorepo the stub is already listed in the application `phpstan.neon`.

## Current limits

v1 binds the slots listed above. Widgets, relation-manager chrome, and import/export are not hooked yet; use `->label()` / `Phrase::slot()` there.

`phrases:audit` and `phrases:sync` walk registered resource forms, tables, action notification titles from action closures, model/navigation chrome, and page titles. They do not yet walk widgets or relation-manager chrome.

`inspect_query` is reserved configuration only.

Heading-only sections never become catalog path segments.

## Testing

Run the suite **from the package**, not from the host application:

```bash
cd packages/filament-auto-translator
composer install
composer test
```

## License

MIT. See `LICENSE`.
