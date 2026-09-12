# 06 — Public API

**Role:** API Designer  
**Status:** Shipped. The live grammar and developer contract are `README.md`. This file is the original API sketch; prefer the README when they differ.

Every item exists because a developer needs it. No parallel Filament type tree. No macros as the public surface.

---

## Naming

| Concept | Name | Why |
| --- | --- | --- |
| Composer package | `syriable/filament-auto-translator` | Requested package name |
| Namespace | `Syriable\Filament\Plugins\AutoTranslator` | Matches Composer vendor |
| Product vocabulary | Phrase / catalog | We bind UI phrases; we do not “auto-translate languages” |
| Panel plugin | `PhrasePlugin` | Registration and config only |
| Contract | `PhraseCatalog` | The class that owns a phrase file |
| Opt-in trait | `BindsPhrases` | Behavior without replacing Filament parents |
| Identity | `PhraseIdentity` | Stable id of one UI string |
| Key compiler | `PhraseKeyCompiler` | Identity → Laravel key |
| Binder | `PhraseBinder` | Fills unset slots |
| Slot | `PhraseSlot` | Which property (label, heading, …) |
| Scope | `PhraseScope` | Which surface (`form`, `infolist`, `table`, …) |
| Override leaf | `phrase(string $name)` | Explicit leaf identity |
| Override owner | `catalog(string $id)` | Explicit catalog / reusable subtree |
| Manual lookup | `Phrase::slot()` | Closures / options / notifications |
| Explain | `PhraseInspector::explain()` | “Why this key?” |
| Audit command | `php artisan phrases:audit` | CI / missing keys |
| Config | `config/auto-translator.php` | Prefixes, mode, inspect query |
| Service provider | `AutoTranslatorServiceProvider` | Discovery, config publish, commands |

Rejected names: `HasTranslations`, `translationKey()`, `AutoTranslator::translateSchemaText()`, `translationGroups()`, `Mode::Balanced`. Those are source-shaped.

---

## Why this API exists

### `PhraseCatalog`

Developers need a **stable owner id** independent of FQCN.

```php
interface PhraseCatalog
{
    public function phraseCatalogId(): string;
}
```

Default in `BindsPhrases`: `{prefix}.{basename-kebab}`. Override when renaming a class without renaming copy.

### `BindsPhrases`

One trait for Resource, Page, Widget, Cluster, RelationManager, Importer, Exporter. Internally it may compose smaller private traits; the public story is one name.

Overrides **public** Filament methods only: model labels, navigation label/group, title, heading, subheading, breadcrumb. Missing keys follow the same mode rules.

### `PhrasePlugin`

```php
$panel->plugin(
    PhrasePlugin::make()
        ->catalogPrefixes([
            'Modules\\Billing' => 'billing',
            'App\\Filament' => 'filament',
        ])
        ->mode(PhraseMode::Inspect) // optional; default from config/env
);
```

`catalogPrefixes` is longest-match, reject overlap. That is the only required configuration for modular apps.

### Component methods

```php
TextInput::make('email');                 // identity leaf = email
TextInput::make('email')->phrase('billing_email');
Section::make()->key('authorization');    // machine name; heading from lang
AddressSchema::make()->catalog('shared.address');
DeleteAction::make()->label('Remove access'); // custom slot wins; binder no-ops
```

### Manual lookup (the 1%)

```php
Phrase::slot($select, PhraseSlot::Label, relative: 'options.admin');
Phrase::slot($action, PhraseSlot::NotificationTitle, relative: 'success');
```

One method. Slot enum. Optional relative suffix. Optional replace/choice count. No family of `translateSchemaText` / `translateTableText` / `translateActionText`.

### Inspector

```php
PhraseInspector::explain($component, PhraseSlot::Label);
```

### Auditor

```bash
php artisan phrases:audit
php artisan phrases:audit --locale=ar --fail-on-missing --fail-on-fallback
```

### Config

```php
return [
    'mode' => env('PHRASE_MODE', 'inspect'), // inspect|strict|lenient
    'default_prefix' => 'filament',
    'catalog_prefixes' => [
        // 'Modules\\Billing' => 'billing',
    ],
    'inspect_query' => 'phrases',
    'max_parent_depth' => 32,
];
```

---

## What we refuse to ship in v1

| Tempting API | Why not |
| --- | --- |
| `Syriable\Filament\Plugins\AutoTranslator\Filament\Resources\Resource` | Inheritance tax; `make:resource` drift |
| `translationKey($key, $absolute = true)` macro | IDE-blind; boolean flag API |
| Global `options()` rewriter | Steals app configuration |
| `strict/balanced/loose` with undefined semantics | Dead source enum; we use inspect/strict/lenient with a table |
| Config flag `overwrite_custom_labels` | Violates “explicit wins” |

---

## Mental model (developer-facing)

```text
1. Put BindsPhrases on the Resource.
2. Keep make('machine_name').
3. Put copy in lang/{locale}/{prefix}/{catalog}.php
4. If the binder is wrong, set the Filament slot or call phrase()/catalog().
5. If you need to know why, explain().
6. Before release, phrases:audit.
```

A developer should not read internals to use this.

---

## Lang file shape (contract, published)

```text
lang/ar/billing/user-resource.php

return [
    'navigation_label' => '…',
    'navigation_group' => '…',
    'model_label' => '…',
    'plural_model_label' => '…',
    'form' => [
        'components' => [
            'authorization' => [
                'heading' => '…',
                'description' => '…',
                'schema' => [
                    'role' => [
                        'label' => '…',
                        'helper_text' => '…',
                    ],
                ],
            ],
        ],
    ],
    'table' => [
        'columns' => [
            'email' => ['label' => '…', 'prefix' => '…'],
        ],
        'empty' => ['heading' => '…', 'description' => '…'],
    ],
    'pages' => [
        'edit-user' => [
            'navigation_label' => '…',
            'title' => '…',
            'subheading' => '…',
            'actions' => [
                'publish' => [
                    'label' => '…',
                    'schema' => [
                        'components' => [
                            'reason' => ['label' => '…'],
                        ],
                    ],
                    'extra_modal_footer_actions' => [
                        'preview' => ['label' => '…'],
                    ],
                ],
            ],
        ],
    ],
];
```

Grammar is documented, versioned, and tested. Dots are not allowed in machine names (auditor error); use `phrase()` if the Filament name must contain a dot (`author.name` columns are normalized to a single segment via a documented rule: replace `.` with `__` or use the last segment — **decision: replace `.` with `/` is wrong because it creates extra nest; replace with `__`**. Recorded in the decision log).
