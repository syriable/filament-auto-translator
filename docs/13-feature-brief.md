# 13 — Feature brief

**Status:** Shipped. Use `README.md` as the developer contract. This brief is the original product note.

## Problem

Filament panel i18n requires inventing and referencing translation keys on every label, heading, hint, action, and nested UI string. Teams drift. PHP files fill with copy that belongs in lang files.

## Target user

Laravel developers building multilingual Filament v5 panels, including modular apps (InterNACHI).

## Current pain

- Dual editing (PHP + lang) for every string.
- No standard key grammar.
- Nested UI has no obvious key owner.
- Missing keys are silent in production (English headlines).
- Existing commercial solution couples keys to FQCN and Filament internals.

## Proposed solution

`syriable/filament-auto-translator`: a **phrase catalog** plugin. Each opt-in Resource/Page/Widget declares a stable catalog id. A binder fills **unset** Filament slots from a deterministic `PhraseIdentity`. An inspector explains resolutions. An auditor proves completeness without clicking the admin.

## Core workflow

1. `use BindsPhrases` on the abstract Resource.
2. Register `PhrasePlugin` with namespace prefixes.
3. Keep `TextInput::make('email')`.
4. Author `lang/{locale}/{prefix}/{catalog}.php`.
5. `php artisan phrases:audit` in CI.

## Non-goals

Machine translation; Eloquent content; Filament vendor chrome; Filament 3/4; replacement Filament subclasses; `invade()`; overwriting custom labels; enum `options()` injection.

## Functional requirements

- F1 Opt-in per class via `PhraseCatalog` + `BindsPhrases`.
- F2 Identifier-only PHP for ordinary schema/table/action chrome.
- F3 Explicit Filament setters always win.
- F4 `phrase()` / `catalog()` overrides.
- F5 Shared resource schema/table keys across create/edit/view.
- F6 Page chrome under `pages.{registeredName}`.
- F7 Nested machine-name paths.
- F8 Required vs optional slots.
- F9 Modes: inspect / strict / lenient.
- F10 Presence checks ignore fallback locale.
- F11 Inspector explain API.
- F12 `phrases:audit` CLI.
- F13 Incremental adoption with existing `__()` / `translateLabel()`.
- F14 Modular namespace prefixes, overlap = error.
- F15 Relation manager parent is explicit.
- F16 Layout components without machine names stay unbound + audited.
- F17 Manual `Phrase::slot()` for the 1% (options, notifications).
- F18 Do not bind prebuilt Filament action vendor strings unless a catalog key exists.

## Non-functional requirements

- PHP 8.4, Laravel 13, Filament 5, strict types.
- No private Filament/Livewire API.
- Request-scoped memo; parent depth cap.
- Pest tests of behavior, not internals.
- PHPStan level 8 friendly (ship stubs for `phrase()` / `catalog()`).

## User experience

PHP stays structure. Copy stays in lang files. Missing required keys scream in inspect/strict. Production does not pretend headlines are translations. Developers can ask why a key was chosen.

## Public API

See `06-public-api.md`. Surface: plugin, one trait, one contract, two component methods, one manual lookup, inspector, auditor, config.

## Architecture

See `05-independent-architecture.md`. Identity → compiler → translator; binder fills unset slots; inspector/auditor observe.

## Edge cases

See `07-edge-case-matrix.md`. Collisions, reusable catalogs, dynamic schemas, refactors, locales.

## Failure behavior

| Case | Behavior |
| --- | --- |
| No catalog on Livewire | No-op |
| Custom label set | No-op |
| Missing required, inspect | Show compiled key |
| Missing required, strict | Exception |
| Missing required, lenient | Filament default + log |
| Fallback locale used | Record; CI can fail |
| Prefix overlap | Boot exception |
| Parent walk exceeds depth | Exception |
| Dotted machine name | Auditor error; compiler rejects |

## Testing requirements

See `08-testing-strategy.md`. Behavioral coverage of identity, binder precedence, modes, audit, modules, nesting, overrides.

## Performance requirements

See `10-performance-strategy.md`. No request-path disk crawls. Memoize. Depth cap. Auditor is CLI.

## Compatibility

Filament 5 + Laravel 13 + PHP 8.4 only.

## Migration strategy

Per-class opt-in. Custom slots win, so old `__()` stays valid until deleted. Audit drives completion. No forced rewrite from other plugins.

## Success criteria

- A resource can ship with zero `__()` on ordinary fields/columns/actions.
- Renaming the PHP class without changing `phraseCatalogId()` does not orphan lang files.
- Two modules cannot silently share a catalog.
- `phrases:audit --fail-on-missing` catches a missing Arabic label that English fallback would hide.
- `explain()` tells a developer the catalog, path, key, and decision.
- Implementation does not subclass Filament Resource/Page types.
- Implementation does not `invade()` Filament or Livewire.
