# 03 — Framework analysis

**Role:** Ecosystem & Framework Analyst (fan-in by Lead Architect)  
**Host app:** PHP 8.4, Laravel 13, Filament 5, Livewire 4, InterNACHI modules.  
**Status:** Research complete. No implementation.

---

## What Filament already gives us (stable)

| Surface | Use |
| --- | --- |
| `__()` / `trans()` / `trans_choice()` / `Translator::has()` | Lookup, placeholders, pluralization |
| `handleMissingKeysUsing()` | Discovery without clicking the UI |
| PHP lang files vs JSON | Prefer dotted PHP groups for contextual keys |
| Fallback locale | Must be queried **without** treating fallback as “present” |
| `->label(__('dotted.key'))` | Documented, version-resilient |
| `translateLabel()` | Translates the English auto-label; Filament maintainers discouraged it as the default i18n model |
| `HasLabel::hasCustomLabel()` | Public: user called `label()` (`vendor/filament/schemas/.../HasLabel.php`) |
| Resource/page methods | `getModelLabel()`, `getNavigationLabel()`, `getTitle()`, `getBreadcrumb()` |
| Vendor lang | `filament-actions::`, `filament-panels::`, `filament-tables::` — do not compete |
| `Filament\Contracts\Plugin` | Panel registration / config |
| `Configurable::configureUsing()` | Global defaults; `$isImportant` and scoped `$during` exist |
| `Filament\Support\Contracts\HasLabel` on enums | Enum option labels |

This app already has `laravel-lang/lang`. Vendor chrome is not our job.

---

## Extension points the source uses (conceptual)

| Mechanism | Stability |
| --- | --- |
| Package discovery + Spatie package tools | Stable |
| Panel plugin `boot()` starting interception | Stable entry, fragile effect |
| `configureUsing` replacing `label` / `heading` / `options` | Fragile as overwrite-all |
| `isImportant: true` on actions | Fragile; fights user labels |
| Replacement Filament subclasses | Fragile; every Filament type is a consumer breaking change |
| Traits overriding public Resource/Page methods | Stable if opt-in |
| Macros writing undeclared component properties | Fragile on PHP 8.4 / fatal on PHP 9 |
| `invade()` of private `label`, `setUp`, `cachedMountedActions`, Select option forms | Implementation detail |
| `Livewire::current()` / component stack | Livewire 4 sensitive |
| FQCN `after('Filament')` | App-structure detail, wrong for modules |
| `ini_set('max_execution_time', 5)` in boot | Process-global hazard |

Changelog is a fragility log: v4 rewrite, v4 resource folders, v5 enum nav groups, recursion in form-component actions, table groups after Filament added closures, wizards, filter forms, query-builder constraints.

---

## What survives Filament majors

Documented `label(__())`, public Resource methods, Plugin, and “configureUsing for defaults” survived v3→v4→v5.

Runtime label interception, private-property recovery, and a parallel Filament class tree did **not**. They track every Filament minor.

---

## Future-proof choices for Filament 5

**Do**

1. Treat Laravel’s translator as the store. Never invent a second dictionary.
2. Opt-in traits on the app’s abstract bases — do not ship replacement `Resource` / `Page` trees.
3. Preserve identifier-only PHP **without** stomping `hasCustomLabel()`.
4. Use `configureUsing` only to fill **unset** slots, never `isImportant` overwrite, never `options()`.
5. Walk public parent/child APIs. No `invade()`, no action-cache reflection.
6. Declare extra component state (`WeakMap` or a package concern), no dynamic properties.
7. Resolve catalog ids with an explicit prefix table (modules first-class). Longest prefix wins; overlap is an error.
8. Leave `filament-actions::` chrome alone unless the catalog defines an override key.
9. Support **one** Filament generation (v5) until a second is demanded. Isolate any version-specific adapter.

**Don’t**

- Subclass Filament’s entire type tree.
- Reconstruct prebuilt actions to recover defaults.
- Gate identity on `Livewire::current()`.
- Slice namespaces at the word `Filament`.
- Touch `max_execution_time`.
- Make `translateLabel()` global.

---

## Fluxwork-specific constraints

- InterNACHI modules: `Modules\{Name}\…` will not match `after('Filament')`.
- Multiple future panels are likely; hooks must not leak across panels.
- Livewire 4 single-file components: “current Livewire” is a bad authority for keys.
- Other plugins (`filament-shield`, Spatie Filament plugins) also use `configureUsing`. Compose, do not rebind `label()` unconditionally.

---

## Implication

The stable i18n story is **contextual keys + public APIs**. The source’s essential DX (do not thread `__()` through every setter) can still be offered by **filling unset slots from a compiled identity**, not by impersonating Filament’s `getLabel()` internals.
