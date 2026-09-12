# 08 — Testing strategy

**Role:** Test Architect  
**Status:** Spec before implementation. Prefer Pest.

Tests verify **behavior**, not “binder called compiler.” No coverage theater.

---

## Quality target

Meaningful behavioral coverage of:

- Identity compilation (including modules, page names, dotted columns).
- Binder precedence (custom slot > phrase() > inferred).
- Modes (inspect / strict / lenient).
- Fallback-disabled presence.
- Collisions and unnamed layout.
- Overrides (`phrase`, `catalog`).
- Auditor CLI exit codes.
- Inspector payload shape.
- “No invade / no Filament subclass required” as an architecture test (Pest arch): package `src/Filament` tree does not exist; `invade(` does not appear.

Not a target: 100% line coverage of SlotMap arrays.

---

## Layers

### 1. Unit (no Filament boot when possible)

- `PhraseKeyCompiler` given a `PhraseIdentity` returns the documented dotted key.
- `.` in names → `__`.
- Prefix matcher: longest wins; overlap throws.
- Default `phraseCatalogId()` kebab of basename.

### 2. Integration with Laravel translator

- PHP lang files loaded under `{prefix}/{catalog}`.
- `has(..., fallback: false)` per locale.
- `trans_choice` via `Phrase::slot(..., number:)`.
- `handleMissingKeysUsing` not required if we do our own presence check — do not double-report.

### 3. Integration with Filament (Orchestra/Testbench + Livewire)

Minimal panel, one Resource with `BindsPhrases`:

- Field without `label()` renders translated label.
- Field with `label('Hardcoded')` keeps it.
- Resource create/edit share schema keys.
- Edit page title uses `pages.edit-user.title`.
- Header action keys under the page; table action keys under table.
- `DeleteAction` without catalog key keeps vendor wording.
- Inspect mode: missing required label shows compiled key in the HTML.
- Strict mode: Livewire request throws `MissingPhraseException`.
- Lenient: Filament default, log assertion.
- Arabic locale missing, English present: inspector `used_fallback`; audit `--fail-on-fallback` exits non-zero.
- Two modules with the same resource basename: different keys.
- Collision: two `email` fields, audit fails.
- `->catalog('shared.address')` shares keys across resources.
- Relation manager without parent id: strict throws.
- `PhraseInspector::explain()` returns catalog, path, key, decision.

### 4. Regression cases (from adversarial review)

Named tests, not tickets:

- `it does not treat english fallback as a present arabic translation`
- `it does not change keys when the resource class is renamed but catalog id is kept`
- `it does not install important configureUsing that overwrites action labels`
- `it does not call invade`
- `it does not set max_execution_time`
- `it does not guess relation manager parents from folders`
- `it does not use section heading text as identity`

### 5. Compatibility

- Filament 5 only: testbench constrained in package `composer.json`.
- Livewire 4 pages as Filament resource pages (not SFC) for v1 harness.

### 6. Configuration

- Plugin prefixes vs config file merge (plugin wins for that panel).
- Invalid mode → boot exception.
- `max_parent_depth` exceeded → exception (synthetic deep tree).

---

## Test data

Factories: one `Post` model, `PostResource`, `AuthorResource`, `Billing\InvoiceResource` (module-like namespace via test stubs), shared address schema class, reusable action class.

Lang fixtures in `tests/lang/{en,ar}/…`.

Do not use production fluxwork modules.

---

## Commands

```bash
vendor/bin/pest packages/filament-auto-translator/tests --compact
```

After implementation, also run fluxwork’s `composer check` only if the app requires the path package.

---

## What not to test

- Private parent-walk internals.
- Filament’s own `filament-actions::` strings beyond “we did not overwrite them.”
- Visual CSS.
- Performance microseconds (see `10`; one smoke: 200 fields bind under a loose budget if cheap).

---

## Gate 6

Behavior in this file is specified enough to implement against. If a scenario in `07` has `Test=Yes` and no counterpart here, add it before coding.
