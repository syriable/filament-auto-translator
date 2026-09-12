# 12 — Independence audit

**Role:** Differentiation Auditor  
**Status:** Conceptual (architecture vs source). Code audit after implementation.

The goal is not artificial difference. The goal is **independent engineering decisions**.

---

## Problem overlap (allowed)

Both products address: **stop inventing and threading translation keys through Filament PHP.**

That overlap is required. A solution to the same problem will share:

- Laravel translator as the store
- Dotted keys
- Opt-in (do not translate vendor plugins)
- Shared resource form keys across create/edit/view
- Nested paths for nested UI
- An override when inference is wrong
- Required vs optional copy

These are problem-driven, not evidence of a clone.

---

## Structural differences (required)

| Dimension | Source (conceptual) | This design |
| --- | --- | --- |
| Identity | FQCN slice after `Filament` + live tree | Explicit catalog id + machine path |
| Opt-in | Replacement Filament subclasses + contract | Trait on **app** types |
| Binding | Global `configureUsing`, actions `isImportant`, recover defaults via `invade` | Fill **unset** slots only; vendor actions untouched unless keyed |
| Internals | Private action caches, Livewire stack, infolist probes | Public APIs + unbound if parent unknown |
| Missing keys | `isProduction()` mute; `has()` with fallback | inspect/strict/lenient; fallback is a first-class state |
| Recursion | `max_execution_time = 5` | Depth cap exception |
| Overrides | Macro `translationKey($key, $absolute)` | `phrase()` / `catalog()` real API |
| Manual lookup | Three methods + group enums | One `Phrase::slot()` |
| Completeness | Click the panel | `phrases:audit` |
| Debugging | None | `PhraseInspector::explain()` |
| Modules | Substring remap | Longest prefix, overlap errors |
| Versions | Filament 3–5 | Filament 5 only |
| Extra behavior | Global enum `options()` | Out of scope |
| Config | Almost none | Published config + plugin prefixes |

---

## Terminology differences

Avoided on purpose: `HasTranslations`, `AutoTranslator::translate*Text`, `translationGroups`, `translationKey()`, `SchemaGroup`/`ActionGroup`/`TableGroup` as the public grouping model, `Mode::Balanced`.

Used instead: phrase, catalog, slot, scope, inspector, auditor.

Composer name `syriable/filament-auto-translator` is a **product name request**, not a class-tree copy. Docs must not claim we translate languages.

---

## Suspicious similarities to watch during implementation

If code review finds these, stop and redesign that area:

1. A `src/Filament/**` mirror of Filament’s type tree.
2. Method names that are the source names with a synonym (`getTranslation` vs `getPhrase` with the same signature soup).
3. The same boot function that registers the same closed list of `configureUsing` targets in the same order with the same “re-instantiate prebuilt action” algorithm.
4. FQCN `after('Filament')` anywhere.
5. `invade()` of `cachedMountedActions`.
6. Lang trees copied from their docs (`form.components…` as the only grammar) without our `schema` / `pages.{registeredName}` contract.

`form` vs `schema` as a word is **not** automatically copying. Filament v5’s own vocabulary is `Schema`. Using `schema` is framework-driven.

---

## Verdict (architecture stage)

The proposed architecture is independently derived: different identity model, different adoption mechanism, different failure modes, different observability, different compatibility policy.

It still delivers the same essential value (identifier-only PHP, copy in lang files).

**Gate 8 (architecture):** Pass, pending implementation audit against this file.
