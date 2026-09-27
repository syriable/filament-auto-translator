# Filament Auto Translator

A Filament plugin that fills your panel's labels, headings, hints, page titles and notifications from Laravel language files — so your PHP keeps only machine names, and nobody has to invent and thread translation keys by hand.

```php
TextInput::make('email');   // no ->label(__('…')) needed
```

```php
// lang/ar/filament/user-resource.php
return [
    'form' => [
        'components' => [
            'email' => ['label' => 'البريد الإلكتروني'],
        ],
    ],
];
```

This is **not** machine translation: it never translates text for you. It decides *where* each string lives, reads it from your language files, writes the keys you are missing, and tells you what is untranslated. It does not translate Eloquent records, and it does not replace Filament's own vendor language files.

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Filament setup](#filament-setup)
- [Usage](#usage)
- [How keys are derived](#how-keys-are-derived)
- [Key reference](#key-reference)
- [Customization](#customization)
- [Schemas outside a panel](#schemas-outside-a-panel)
- [Console commands](#console-commands)
- [Configuration](#configuration)
- [Extending](#extending)
- [Troubleshooting](#troubleshooting)
- [Limitations](#limitations)
- [Upgrading](#upgrading)
- [Development](#development)

## Features

- **Automatic binding.** Fields, infolist entries, sections, fieldsets, wizards, steps, tabs, empty states, callouts, text, actions (with their modals), table columns, filters and notifications fill every *unset* piece of copy from the language file.
- **Explicit copy always wins.** `->label('Work email')` in PHP is never overwritten.
- **Resource, page and cluster chrome.** Model labels, navigation labels and groups, page titles and subheadings, cluster breadcrumbs.
- **Keys derived from structure.** Keys come from machine names and keyed parents, never from visible text, so renaming a PHP class does not move them.
- **Extraction.** `auto-translator:extract` writes every missing key with a readable stub, and removes keys whose component was deleted — without ever overwriting existing copy.
- **Auditing.** `auto-translator:audit` lists untranslated messages per locale and can fail CI.
- **Missing-message policy.** Keep Filament's text, show the key, or throw.
- **Modules.** Map namespaces to domains, including translation namespaces (`billing::`), so each module keeps its copy in its own lang directory.
- **Public pages.** Livewire schemas outside a panel are bound too.

## Requirements

| | Version |
| --- | --- |
| PHP | 8.4+ |
| Laravel | 13 |
| Filament | 5 |

## Installation

```bash
composer require syriable/filament-auto-translator
```

The service provider is auto-discovered. Publishing the config file is optional:

```bash
php artisan vendor:publish --tag=filament-auto-translator-config
```

## Filament setup

Register the plugin on each panel:

```php
use Syriable\Filament\Plugins\AutoTranslator\AutoTranslatorPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // …
        ->plugin(AutoTranslatorPlugin::make());
}
```

Then opt in the classes that should be translated. Nothing is bound for a class that declares no translation domain.

```php
use Syriable\Filament\Plugins\AutoTranslator\Concerns\HasResourceTranslations;

class UserResource extends Resource
{
    use HasResourceTranslations;
}
```

```php
use Syriable\Filament\Plugins\AutoTranslator\Concerns\HasPageTranslations;

class EditUser extends EditRecord
{
    use HasPageTranslations;
}
```

| Trait | Use it on | What it binds |
| --- | --- | --- |
| `HasResourceTranslations` | Resources | Model labels, navigation label and group |
| `HasPageTranslations` | Resource pages and standalone panel pages | Title, subheading, navigation label — and every component the page renders |
| `HasClusterTranslations` | Clusters | Breadcrumb and navigation label |

Components find their domain through the Livewire component that renders them — the **page** — so every page that renders a form or table needs `HasPageTranslations`. A resource page shares its resource's domain.

## Usage

1. Opt in your resources and pages as above.
2. Write PHP with machine names only:

   ```php
   public static function form(Schema $schema): Schema
   {
       return $schema->components([
           Section::make()->key('account')->schema([
               TextInput::make('email'),
               TextInput::make('name'),
           ]),
       ]);
   }
   ```

3. Generate the language file:

   ```bash
   php artisan auto-translator:extract --locale=en
   ```

   ```php
   // lang/en/filament/user-resource.php
   return [
       'model_label' => 'Model label',
       'navigation_label' => 'Navigation label',
       'pages' => [
           'edit-user' => ['title' => 'Edit user', 'navigation_label' => 'Edit user'],
       ],
       'form' => [
           'components' => [
               'account' => [
                   'schema' => [
                       'email' => ['label' => 'Email'],
                       'name' => ['label' => 'Name'],
                   ],
               ],
           ],
       ],
   ];
   ```

4. Replace the stubs with real copy, and add optional copy (`helper_text`, `placeholder`, a section `heading`…) wherever you want it.
5. Run `auto-translator:extract --locale=ar` for each further locale, and `auto-translator:audit --fail-on-missing` in CI.

## How keys are derived

Every message has an identity: **domain + scope + parent path + machine name + slot**. It compiles to one Laravel translation key:

```text
filament/user-resource . form . components . account.schema . email . label
└──── domain ────────┘  scope   (wrapper)     parent path     name    slot
```

### Domains

A **translation domain** is the language file a class's copy lives in.

- By default it is derived: `{prefix}.{class-kebab}`. With the default prefix `filament`, `UserResource` becomes `filament.user-resource`, read from `lang/{locale}/filament/user-resource.php`.
- A standalone panel page gets `{prefix}.pages.{class-kebab}`: `Dashboard` reads `lang/{locale}/filament/pages/dashboard.php`.
- The prefix comes from the longest matching namespace in `domain_prefixes`, else `default_domain_prefix`.
- A prefix ending in `::` names a registered translation namespace: `'Modules\\Billing' => 'billing::'` puts `InvoiceResource` in `billing::invoice-resource`, i.e. `{locale}/invoice-resource.php` inside the billing module's lang directory.
- `#[TranslationDomain]` names a domain outright and wins over derivation. Use it to keep keys stable across a class rename:

  ```php
  use Syriable\Filament\Plugins\AutoTranslator\Attributes\TranslationDomain;

  #[TranslationDomain('filament.user-resource')]
  class MemberResource extends Resource
  {
      use HasResourceTranslations;
  }
  ```

A domain is dotted segments of letters, digits, `-` and `_`, optionally prefixed by `namespace::`. Dots become directories.

### Paths and names

- A component's **name** is its `make()` argument: `TextInput::make('email')` → `email`.
- A layout joins the path only when it has a machine name: `Section::make()->key('account')`. A section built with a visible heading, `Section::make('Account')`, adds nothing — visible copy is never used as a key.
- `Step`, `Tab`, `Tabs`, `EmptyState`, `Callout` and `Text` take their machine name from `make()`: `Tab::make('profile')`. A `make()` argument that is not a valid machine name (a sentence) is treated as visible copy and left alone.
- Names are lowercased and dots become `__`, so `author.name` → `author__name`. Valid segments contain `a-z`, `0-9`, `-`, `_`; anything else throws `InvalidMessageNameException`.
- Renaming a PHP class does not change keys when the domain is declared; renaming a field or key always does.

## Key reference

Everything below sits inside one domain's file. `{name}` is a machine name.

```text
# Resource chrome (HasResourceTranslations)
model_label
plural_model_label                         optional
plural_label                               optional
navigation_label
navigation_group                           optional

# Cluster chrome (HasClusterTranslations)
cluster_breadcrumb
navigation_label

# Resource pages, by page class kebab (EditUser → edit-user)
pages.{page}.title
pages.{page}.subheading                    optional
pages.{page}.navigation_label
pages.{page}.actions.{name}.label          header actions
pages.{page}.actions.{name}.schema.components.{field}.label
pages.{page}.actions.{name}.extra_modal_footer_actions.{name}.label

# Standalone pages own their file, so the same keys sit at its root
title | subheading | navigation_label | actions.{name}.label

# Forms (infolists are the same under infolist.components)
form.components.{field}.label
form.components.{field}.helper_text | hint | hint_icon_tooltip | placeholder
form.components.{field}.before_content | after_content | below_label
form.components.{field}.validation_attribute      what validation messages call it
form.components.{field}.actions.{name}.label      hint, prefix and suffix actions
form.components.{layout}.heading | description    keyed Section, optional
form.components.{layout}.label                    keyed Fieldset or Wizard, optional
form.components.{layout}.schema.{field}.label
form.components.{tab-or-step}.label
form.components.{empty-state-or-callout}.heading | description
form.components.{text}.body | tooltip
form.components.actions.{name}.label
form.components.actions.{name}.modal_heading | modal_description
form.components.actions.{name}.modal_cancel_action_label | modal_submit_action_label
form.components.actions.{name}.schema.components.{field}.label
form.components.actions.{name}.notifications.{status}.title
form.components.actions.{name}.notifications.{status}.body   optional

# Tables
table.columns.{name}.label
table.columns.{name}.prefix                optional
table.filters.{name}.label
table.filters.{name}.indicator             optional
table.record_actions.{name}.label
table.toolbar_actions.{name}.label
table.header_actions.{name}.label
table.empty_state_actions.{name}.label

# Actions outside a schema, table or resource page
actions.{name}.label
```

A notification is bound when it is sent from inside an action and has a status (`success`, `danger`, `info`, `warning`); the status is its name.

**Required** slots are `label`, `title`, `cluster_breadcrumb` and a `Text` `body`: a missing one follows the [missing-message policy](#missing-message-policy). Every other slot is **optional**: missing, it stays empty and Filament's default applies.

A key present only in the **fallback locale** is shown, but reported by `auto-translator:audit` as `used_fallback_locale`.

## Customization

### Explicit copy

Any Filament setter called after `make()` wins over the language file for that slot:

```php
TextInput::make('email')->label('Work email');   // the label is not bound
```

Vendor actions such as `DeleteAction` keep Filament's own label until your domain defines one.

### Component macros

The plugin adds four macros to every Filament component:

```php
// use another name as the key's leaf
TextInput::make('email')->messageName('billing_email');

// move this component and its children to another domain
AddressFields::make()->messageDomain('shared.address');

// fill :placeholders in the line; closures run when the slot renders
Text::make('terms')->messageReplace([
    'url' => fn (): string => route('terms'),
]);

// the component itself can be injected, or the whole map can be a closure
Textarea::make('bio')->maxLength(500)
    ->messageReplace(fn (Textarea $component): array => ['max' => $component->getMaxLength()]);

// render this component's line as HTML; replacements are escaped
Text::make('footer')
    ->messageHtml()
    ->messageReplace(['url' => fn (): string => route('terms')]);
// 'By signing up you accept our <a href=":url">terms</a>.'
```

A line is never treated as HTML because it contains a tag: only `messageHtml()` opts in. Under it, every replacement is escaped unless you pass an `Illuminate\Support\HtmlString`.

For PHPStan, add the shipped stub:

```neon
parameters:
    stubFiles:
        - vendor/syriable/filament-auto-translator/stubs/filament-components.stub
```

### Missing-message policy

What a missing **required** message does:

| Policy | Result |
| --- | --- |
| `keep_vendor_label` (default) | Filament's own text stays |
| `debug` | The key is rendered, so you can see what to add |
| `strict` | `MissingMessageException` is thrown |

Set it with `AUTO_TRANSLATOR_ON_MISSING`, or per panel:

```php
use Syriable\Filament\Plugins\AutoTranslator\Enums\MissingMessagePolicy;

AutoTranslatorPlugin::make()->onMissing(MissingMessagePolicy::Debug);
```

### Hint-icon tooltips

Set the icon with one argument and keep the tooltip in the language file:

```php
TextInput::make('password')->hintIcon('heroicon-o-question-mark-circle');
// form.components.password.hint_icon_tooltip
```

A second argument to `hintIcon()`, or `->hintIconTooltip()`, sets the tooltip explicitly.

### Copy the binder does not reach

For select options and similar, look a message up by hand at a component's identity:

```php
use Syriable\Filament\Plugins\AutoTranslator\AutoTranslator;

Select::make('role')->options(fn (Select $component): array => [
    'admin' => AutoTranslator::message($component, relative: 'options.admin'),
    'member' => AutoTranslator::message($component, relative: 'options.member'),
]);
// form.components.role.options.admin
```

`message()` also takes `replace:` and `number:` (for `trans_choice()` pluralisation).

### Debugging a key

```php
$resolution = AutoTranslator::explain($component);   // or pass a MessageSlot

$resolution->key;       // filament/user-resource.form.components.email.label
$resolution->outcome;   // ResolutionOutcome::Bound, Missing, UsedFallbackLocale, NoDomain, Unbound
$resolution->text;
$resolution->reason;
```

## Schemas outside a panel

A Livewire form on a public page never boots a panel. Give its schema class a domain, and register the directory it lives in:

```php
use Syriable\Filament\Plugins\AutoTranslator\Attributes\TranslationDomain;

#[TranslationDomain('site.sign-up')]
final class SignUpForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('email')]);
    }
}
```

```php
// a service provider's boot()
use Syriable\Filament\Plugins\AutoTranslator\AutoTranslator;

AutoTranslator::discoverIn(app_path('Livewire/Schemas'), 'App\\Livewire\\Schemas');
```

`discoverIn()` also starts binding, so it is the only call such an application needs. It is idempotent, so every module may call it for its own directory. The same directories can be registered from the panel (`AutoTranslatorPlugin::make()->discoverIn(in: …, for: …)`) or in `discover_paths`.

A **schema domain** is any class there that carries `#[TranslationDomain]` and a public static `form(Schema)` or `configure(Schema)`. Resources and abstract classes are skipped. The commands walk its schema under the `form` scope.

The Livewire component that renders the schema needs no trait: point it at the schema's domain.

```php
public static function translationDomain(): string
{
    return AutoTranslator::domainFor(SignUpForm::class);
}
```

If the class also has a public static `make()` returning the component that wraps the schema (a section with a heading and footer around an `EmbeddedSchema`), that wrapper is walked too. A keyed wrapper lends its path segment to the fields it embeds: keying a wrapper that already has copy underneath moves those keys, so run `auto-translator:extract` and review the diff.

## Console commands

Each command boots every registered panel first, so prefixes, discovery paths and the policy configured on a plugin apply exactly as in the browser.

### `auto-translator:audit`

Reports messages a locale is missing, without writing anything.

```bash
php artisan auto-translator:audit --locale=ar --fail-on-missing --fail-on-fallback
```

| Option | Effect |
| --- | --- |
| `--locale=` | Locale to audit; default is the application locale |
| `--fail-on-missing` | Exit 1 when a required message is missing |
| `--fail-on-fallback` | Exit 1 when the locale shows the fallback locale's copy |

### `auto-translator:extract`

Writes missing keys and removes obsolete ones.

```bash
php artisan auto-translator:extract --locale=en,ar
php artisan auto-translator:extract --dry-run
php artisan auto-translator:extract --no-prune
```

| Option | Effect |
| --- | --- |
| `--locale=` | One or more locales, comma-separated; default is the application locale |
| `--dry-run` | Show every change without writing |
| `--no-prune` | Only add keys |

- Existing copy is never overwritten.
- A new key gets a humanized stub (`is_featured` → `Is featured`), or the fallback locale's copy when it has one.
- A key is removed only when its component is gone **and** the part of the UI it belonged to (a resource's form, infolist or table; its registered pages) was built successfully. A builder that throws — say, one reading the signed-in user — leaves its keys alone. Optional slots and `options.*` under a live component are kept.
- A namespaced domain whose module has no lang directory yet gets its namespace registered, under `module_path`, so the first file can be written.

### `auto-translator:inline`

The reverse, for teams that prefer explicit keys in PHP: writes `->label(__('…'))` and friends onto matching `::make('name')` chains, and `getModelLabel()`-style methods onto resources, pages and clusters, for keys that already have copy.

```bash
php artisan auto-translator:inline --locale=en --dry-run
```

Only the resource's own file and the files in its `Schemas/`, `Infolists/` and `Tables/` folders are edited, never `vendor/`. Existing setters are left alone; a setter passed the bare key as a string is wrapped in `__()`.

## Configuration

`config/filament-auto-translator.php`:

| Key | Default | Purpose |
| --- | --- | --- |
| `on_missing` | `keep_vendor_label` (`AUTO_TRANSLATOR_ON_MISSING`) | The [missing-message policy](#missing-message-policy) |
| `default_domain_prefix` | `filament` | Prefix of a derived domain when no namespace matches |
| `domain_prefixes` | `[]` | `['Modules\\Billing' => 'billing']`; longest namespace wins; `::` suffix names a translation namespace |
| `discover_paths` | `[]` | `[['path' => …, 'namespace' => …]]` — directories of [schema domains](#schemas-outside-a-panel) |
| `module_path` | `modules` | Where modules live, for registering a new module's lang directory |
| `max_parent_depth` | `32` | Cap on how deep a key's parent path may go |

Plugin options are layered on top of the config:

```php
AutoTranslatorPlugin::make()
    ->domainPrefixes(['Modules\\Billing' => 'billing::'])
    ->discoverIn(in: base_path('modules/billing/src/Livewire'), for: 'Modules\\Billing\\Livewire')
    ->onMissing(MissingMessagePolicy::Strict);
```

## Extending

- **Components from other packages** are bound when they extend `Filament\Schemas\Components\Component`, carry a label, and are named with `->key()`: `Separator::make()->key('divider')` reads `form.components.divider.label`. Whatever label the component was built with stays the fallback.
- **Custom Livewire owners** take part by carrying `#[TranslationDomain]` or a static `translationDomain(): string`.
- **Tests of your own schemas** can assert that every message resolves:

  ```php
  use Syriable\Filament\Plugins\AutoTranslator\Scanning\MessageScanner;

  $findings = app(MessageScanner::class)->scanComponents($schema->getComponents())->findings;

  expect($findings)->toBe([]);
  ```

## Troubleshooting

**A label shows Filament's default text.** The page rendering it has no domain: add `HasPageTranslations` to the page (not only the resource). Then check the key with `AutoTranslator::explain($component)` or set `on_missing` to `debug` to see keys in the UI.

**A key has an unexpected path.** Only keyed layouts join the path. `Section::make('Account')` does not; `Section::make()->key('account')` does.

**Arabic shows English.** The key exists only in the fallback locale. `auto-translator:extract --locale=ar` copies it over for translation; `auto-translator:audit --fail-on-fallback` catches it in CI.

**Extraction wrote copy to an unexpected file.** Domain prefixes registered on the plugin apply only to panels the command can boot. Check `domain_prefixes`, or declare `#[TranslationDomain]`.

**`UnknownTranslationNamespaceException`.** A `billing::` domain needs a registered translation namespace, or a module directory under `module_path` so extraction can register one.

**`InvalidMessageNameException`.** A machine name contains a space or punctuation. Use `->key()` or `->messageName()` with a snake-case name.

## Limitations

- Widgets, relation-manager chrome, import/export and select options are not bound automatically; use explicit setters or `AutoTranslator::message()`.
- Notifications are discovered by reading the action's closure for `Notification::make()` and a status call; notifications built elsewhere are bound at runtime but not extracted.
- A layout's scope (`form` or `infolist`) is inferred from its direct children: a keyed section holding only other sections inside an infolist is placed under `form`.

## Upgrading

From the unreleased `syriable/laravel-translation`:

| Before | Now |
| --- | --- |
| `syriable/laravel-translation` | `syriable/filament-auto-translator` |
| `Syriable\Translation\…` | `Syriable\Filament\Plugins\AutoTranslator\…` |
| `TranslationPlugin` | `AutoTranslatorPlugin` |
| `Translations::slot()` | `AutoTranslator::message()` |
| `HasModelTranslations` | `HasResourceTranslations` |
| `->domain()` | `->messageDomain()` |
| `config/translations.php`, `TRANSLATIONS_ON_MISSING` | `config/filament-auto-translator.php`, `AUTO_TRANSLATOR_ON_MISSING` |
| `translations:debug` / `extract` / `inline` | `auto-translator:audit` / `extract` / `inline` |
| `ResolutionOutcome::NoCatalog`, `$resolution->decision` | `ResolutionOutcome::NoDomain`, `$resolution->outcome` |

Every compiled key is unchanged, so existing language files keep working.

## Development

```bash
composer install
composer test       # Pest
composer analyse    # PHPStan, level 8
composer format     # Pint
```

`tests/Snapshots/compiled-keys.txt` pins every key the fixture schemas compile to. It changes only deliberately: a moved key silently orphans a translation in every application using the package.

## License

MIT. See [LICENSE](LICENSE).
