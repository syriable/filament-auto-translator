# 01 — Problem and capability analysis

**Role:** Product Archaeologist (fan-in by Lead Architect)  
**Status:** Research complete. No implementation.  
**Sources:** official plugin listing, public v2 docs, clone at `clone/laravel-filament-auto-translator` (behavior only), Filament v5 `HasLabel` / translator APIs in this app.

---

## The real problem

Filament can already be translated. Laravel already has `__()`, `trans()`, `trans_choice()`, PHP lang files, JSON files, and fallback locales. Filament already has `->label(__('…'))`, `translateLabel()`, resource methods (`getModelLabel()`, `getNavigationLabel()`, `getTitle()`), and vendor lang files for Create/Edit/Delete chrome.

The pain is not “Filament cannot be translated.”

The pain is **authoring and referencing keys** for every visible panel string:

1. Invent a key.
2. Pass it through `label()`, `helperText()`, `heading()`, `emptyStateHeading()`, action modal copy, and so on.
3. Keep that scheme consistent across teammates, nested schemas, actions-in-actions, tables, and relation managers.
4. Open the PHP file again just to add an optional hint.

That is a developer-tool problem. End users, content translators, and Eloquent record translation are out of scope.

---

## Target users

- Developers shipping **multilingual Filament panels**.
- Teams that need one key convention so two people do not invent two trees.
- Apps that want **incremental** adoption (new resources first).

Not: panel end users, TMS workflows, machine translation, or database-content i18n.

---

## Workflow before / after (observable)

**Before**

```text
PHP: TextInput::make('role')->label(__('…'))->helperText(__('…'))
Lang: invent matching keys
Repeat for tables, actions, empty states, navigation
```

**After (source product)**

```text
PHP: TextInput::make('role')          // identifier only
Lang: filament/resources/user-resource.php  // copy lives here
Missing required copy: visible as the key in non-production
Optional copy: appears only when the lang key exists
```

The source does **not** write language files. It **computes a key at runtime** and looks it up.

---

## Capability map

### Core (the product)

| Capability | What it actually does |
| --- | --- |
| Location-derived keys | Build a Laravel key from owning class + UI tree + component name + property |
| Runtime lookup | `__()` / `trans_choice` / `translator->has()` — no codegen |
| Opt-in ownership | Only Livewire/Filament classes that implement a translations contract participate |
| Identifier-only PHP | `make('role')` is a machine id; headings for sections are also treated as ids |
| Required vs optional slots | Labels/headings always resolve; hints/tooltips only if the key exists |
| Shared resource form/table | Create/edit/view forms share the resource catalog; page titles stay page-scoped |
| Nesting | Walk parents so repeaters, sections, nested actions, filter forms, wizards get dotted paths |
| Escape hatches | Relative/absolute key override; “give me a sub-key for this live component” |
| Namespace remap | Map a PHP namespace prefix to a lang-file prefix (multi-panel) |
| Incremental adoption | Per class, not all-or-nothing |

### Convenience (keep only if they earn their keep)

- Drop-in Filament subclasses (Resource, pages, widgets, importers, …) so apps can search-replace `extends`.
- Enum-backed select/radio options injected globally.
- Laravel Boost skill pointing at docs.

### Not the product

- Machine translation / AI.
- Generating or syncing lang files.
- A translator UI.
- Eloquent/Spatie Translatable content.
- Replacing Filament vendor chrome (`filament-actions::…`) as the default.
- Covering every Filament column type (source hooks a whitelist).

---

## Essential value to preserve

If a reconstruction dropped these, it would not solve the same problem:

1. Developers should not invent key names for ordinary panel chrome.
2. PHP should stay structure; copy should live in lang files.
3. Required slots always have a resolution rule; optional slots appear only when authored.
4. Shared resource schemas must not duplicate keys per create/edit/view page.
5. Nesting must match Filament’s real trees, or the convention is unusable.
6. Adoption must be opt-in per class.
7. There must be an explicit override when automatic identity is wrong.
8. Missing keys must be discoverable during development.

---

## Assumptions the source makes (and we must not swallow blindly)

- Apps use Filament panel Resources with abstract bases.
- `make('name')` is a stable machine id, including for sections.
- FQCN contains a `Filament` segment (`App\Filament\…`).
- Relation managers live in a `RelationManagers` folder under the resource.
- Language files are PHP arrays, not JSON.
- Developers will click the panel to find missing keys.
- Global `configureUsing` plus a Livewire contract is an acceptable gate.

Fluxwork violates several of these (InterNACHI modules, no `App\Filament` requirement, Livewire 4 SFCs). That is a first-class design input, not an afterthought.

---

## Evidence

- Plugin page: location-based keys, identifier-only PHP, incremental adoption, “~99% of `__()`”.
- Docs surface is small (install / usage / upgrade); most behavior lives in source.
- Clone: runtime binder, traits, replacement Filament types, unused `Mode` enum, `configureUsing` + `invade()`, `app()->isProduction()` mute switch, `ini_set('max_execution_time', 5)` during boot.
- Filament v5 (this app): `HasLabel::hasCustomLabel()` is public and means “user called `label()`”.
- Filament maintainers discouraged English-as-key `translateLabel()` as the default i18n model (PR discussion on contextual keys).
