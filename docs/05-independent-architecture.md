# 05 — Independent architecture

**Role:** Independent Architect  
**Status:** Proposed. Awaiting approval. **Do not implement yet.**

This architecture can be explained without referring to the source package’s class tree.

---

## Problem

Multilingual Filament apps spend most of their i18n effort **naming and referencing keys**, not writing copy. We solve that by giving every translatable panel string a **stable phrase identity**, compiling it to a Laravel key, and binding the resolved copy onto Filament **only when the developer did not set the slot themselves**.

We do not translate languages. We do not own Eloquent content. We do not replace Filament vendor chrome.

---

## Principles

1. **Small surface area.** One catalog, one identity, one binder, one inspector.
2. **Identifier-only PHP is the default.** Copy lives in lang files.
3. **Explicit wins.** `->label('…')`, `->phrase()`, `->catalog()` always beat inference.
4. **Identity ≠ filesystem path.** Class moves must not rename phrases unless the developer changes the catalog id.
5. **Missing is observable.** Headlines are not translations.
6. **Public Filament APIs only.** No `invade()`, no private action caches, no process ini hacks.
7. **Opt-in per catalog class.** No inheritance tax (no replacement Filament types).
8. **Fill unset slots.** Never overwrite a custom label.
9. **Explainability.** Every bound string can answer “why this key?”
10. **Audit without clicking.** Runtime bind is convenience; CI extract is correctness.

---

## Boundaries (non-goals)

- Machine translation, AI, locale detection, locale switcher UI.
- Spatie Translatable / database content.
- Generating English copy. We bind keys; humans write strings.
- Translating Filament vendor files (`filament-actions::`). Override only if the catalog defines a key.
- Supporting Filament 3/4 in v1.
- A second dictionary besides Laravel’s translator.
- Automatically mutating `options()` from enum casts.

---

## Major components

```text
PhraseCatalog (contract on Resource/Page/Widget/…)
        │
        ▼
PhraseIdentity  ──►  PhraseKeyCompiler  ──►  Laravel translator
        ▲
        │
PhraseBinder  (fills unset Filament slots at evaluation time)
        │
        ▼
PhraseInspector  (explain)     PhraseAuditor (Artisan + CI)
```

| Component | Responsibility |
| --- | --- |
| `PhraseCatalog` | Declares a stable catalog id and optional prefix |
| `BindsPhrases` | Trait: navigation/title/model-label methods + catalog wiring |
| `PhraseIdentity` | Immutable value: catalog + scope + path + name + slot |
| `PhraseKeyCompiler` | Deterministic identity → dotted Laravel key + file |
| `PhraseBinder` | Evaluation-time: if slot unset and catalog active, resolve |
| `PhraseSlot` | Enum of bindable properties (label, heading, helper_text, …) |
| `PhraseScope` | Enum of surfaces (schema, table, actions, pages, navigation, …) |
| `PhraseInspector` | Structured explanation of a resolution |
| `PhraseAuditor` | Walks registered catalogs, reports missing/fallback/collisions |
| `PhrasePlugin` | Panel registration, prefix map, mode, inspect query |
| `PhraseMemo` | Request-scoped memo of (identity, locale) → result |

No god `AutoTranslator` service. No Filament subclass tree. No `Mode` leftover.

---

## Data flow

1. App Resource uses `BindsPhrases` and implements `PhraseCatalog`.
2. Panel registers `PhrasePlugin`.
3. Binder registers **non-important** `configureUsing` hooks that close over public getters only.
4. At evaluation, binder asks: is the current Livewire (or schema owner) a `PhraseCatalog`? If no, no-op.
5. If the slot is already custom (`hasCustomLabel()` or equivalent), no-op.
6. Build `PhraseIdentity` from catalog id + scope + public parent names + component name + slot.
7. Compile `PhraseKey`.
8. Lookup **current locale, fallback disabled** for presence.
9. Apply mode (inspect / strict / lenient).
10. Memoize. Record inspector frame.

Parent walking uses public component/container APIs. If a parent cannot be named, the binder stops and the inspector marks `unresolved_parent` — it does not guess from private caches.

---

## Translation-key identity model

A translatable UI element is a **phrase**, not “a string that became a key.”

```text
PhraseIdentity =
    catalogId          // developer-stable, explicit default
  + scope              // schema | table | actions | pages | navigation | …
  + path[]             // ancestor machine names
  + name               // this component’s machine name
  + slot               // label | heading | helper_text | …
```

### Signal table

| Signal | Stable? | Role |
| --- | --- | --- |
| `phraseCatalogId()` | Yes, developer-owned | Survives namespace/folder fashion |
| Config prefix (`Modules\Billing` → `billing`) | Yes, config | Distinguishes modules |
| Scope | Yes | Separates form vs table vs page chrome |
| Ancestor machine names | Contextual | Distinguishes nested `email` fields |
| Component `getName()` / `getKey()` | Contextual | Leaf identity |
| Registered Filament page name (`edit`, `create`) | More stable than class basename | Page chrome |
| PHP FQCN | **Unstable** | Not in the key |
| Heading / label text | **Unstable** | Never in the key |
| Panel id | Optional | Only if two panels share a class with different copy |
| Explicit `->phrase()` / `->catalog()` | Developer-controlled | Escape hatch |

### Default `phraseCatalogId()`

```text
{prefix}.{basename-kebab}

UserResource in Modules\Billing → billing.user-resource
UserResource in App\Filament\Resources → filament.user-resource
```

Prefix comes from **longest matching configured namespace**. No match → configured default prefix. Overlapping prefixes that would compile to the same catalog id → **boot exception**.

Resource create/edit/view **schema and table** use the **resource catalog**, not the page catalog. Page titles, subheadings, and header actions use:

```text
{resourceCatalog}.pages.{registeredPageName}.…
```

`registeredPageName` is Filament’s page name (`edit`), not `EditUser` snake.

Relation managers declare `phraseCatalogId()` or `phraseParentCatalogId()` explicitly. Folder guessing is forbidden. Unresolved parent → exception in strict/inspect, skip in lenient with inspector error.

---

## Collision policy

| Situation | Policy |
| --- | --- |
| Two `TextInput::make('name')` in different catalogs | Different identities — correct |
| Two `name` fields in one schema without distinct parents | **Collision** — auditor fails; inspect mode surfaces both |
| Same reusable address schema in two resources, no override | Separate identities (semantic: different owners) |
| Same reusable address schema with `->catalog('shared.address')` | Shared identity — intended |
| Two sibling actions named `confirm` | Collision — require `->phrase('archive_confirm')` |
| Layout component with no machine name (heading-only section) | **Unbound**; auditor warning; Filament default remains |

We never key off the visible heading. `Section::make('authorization')` is valid **if** Filament treats that as the name/key. If it is only a heading, developers must `->key('authorization')` or `->phrase('authorization')`. Heading copy comes from the `heading` slot in lang.

---

## Refactoring policy

| Change | Identity |
| --- | --- |
| Rename field `role` → `job_role` | New identity (semantic change). Auditor reports orphan + missing. |
| Rename class `UserResource` → `MemberResource` without changing catalog id | **Preserved** |
| Change namespace / move module file | **Preserved** (prefix still matches) |
| Change `phraseCatalogId()` | New identity. Developer-controlled migration. |
| Rename registered page `edit` → `edit-profile` | Page chrome keys change; shared schema keys do not |
| Extract reusable schema without `->catalog()` | New identities under each owner (duplication, not collision) |
| Extract with `->catalog('shared.address')` | Shared identity preserved |
| Introduce abstract Resource base | No identity change (catalog is the concrete class id unless overridden) |

Structural identity (FQCN) is rejected. Semantic identity (catalog id + machine names) is the contract.

---

## Override strategy

1. **Explicit Filament slot** (`->label()`, `->heading()`, …) — binder no-ops.
2. **`->phrase('billing_email')`** — replaces the leaf name in the identity.
3. **`->catalog('shared.address')`** — replaces the catalog id (and optionally resets path) for this subtree.
4. **`Phrase::slot($component, PhraseSlot::HelperText, 'options.admin')`** — manual sub-key for closures (options, notification bodies).

No macros. These are real methods on a small `HasPhraseBinding` concern applied via `configureUsing` **or** a dedicated wrapper trait documented for PHPStan stubs we ship.

Preferred implementation: a Filament-safe concern registered through the plugin that adds declared methods, plus a PHPStan stub. If Filament’s base cannot be extended, a thin `Phrase::bind($component)` helper plus attributes on the component via `WeakMap`.

---

## Fallback / missing strategy

| Mode | Required slot missing | Optional slot missing | Fallback locale hit |
| --- | --- | --- | --- |
| `inspect` (default `local`/`testing`) | Render the compiled key (visual lint) | Omit (Filament default) + auditor record | Record as `used_fallback` |
| `strict` (CI, optional local) | Throw `MissingPhraseException` | Auditor fail if `--fail-on-optional` | Fail CI |
| `lenient` (default `production`) | Leave slot unset (Filament native default) + log once | Omit | Log `used_fallback` |

`Translator::has($key, locale: current, fallback: false)` is the presence check.

Never treat `Str::headline($name)` as a successful translation.

---

## Extensibility

New Filament component types: register a `SlotMap` (class → list of slots) on the plugin. Default maps cover schema components, actions, table columns/filters/summarizers, widgets, import/export columns. Unknown types are unbound + auditor “unsupported type” — not a whitelist that silently English-ifies.

---

## Compatibility

| Platform | v1 support |
| --- | --- |
| PHP | `^8.4` |
| Laravel | `^13` |
| Filament | `^5` |
| Livewire | `^4` (as required by Filament 5) |

No v3/v4. If a second Filament major is needed later, isolate adapters behind `SlotMap` / parent-walk — keep identity and compiler version-agnostic.

---

## Performance

- Request-scoped memo: `(spl_object_id, slot, locale)` and `(PhraseIdentity, locale)`.
- No filesystem in the request path except Laravel’s translator (already cached).
- Parent walk bounded by depth (e.g. 32) with exception, never `max_execution_time`.
- Auditor is CLI, not request.

See `10-performance-strategy.md`.

---

## Debugging

Simplest excellent visibility:

```php
PhraseInspector::explain($component, PhraseSlot::Label);
```

Returns: catalog id, path, compiled key, locale, presence in current locale, presence in fallback, mode, decision (`bound` | `custom_slot` | `missing` | `unbound` | `used_fallback`), reason.

Local-only query flag: `?phrases=explain` adds a debugbar / log channel dump of all resolutions this request. No overlay required for v1.

---

## Migration

1. Add trait to one abstract Resource. Old `__()` and `translateLabel()` keep working because custom slots win.
2. Delete `__()` as keys appear in lang files.
3. `php artisan phrases:audit` until clean.
4. Mixed mode is the default, not a special case.

No all-or-nothing cutover. No forced lang-file rewrite from a previous commercial plugin (different keys). If someone migrates from the source package, we can later offer a **one-way mapping cookbook** in docs — not a compatibility layer that clones their grammar.

---

## Package layout (after approval)

```text
packages/filament-auto-translator/
  composer.json                 # syriable/filament-auto-translator
  src/
  tests/
  config/auto-translator.php
  docs/                         # this design set
```

Wired into fluxwork via a Composer path repository. Publishing as a standalone GitHub repo can happen when the API is stable — not a v1 blocker.

---

## Quality gates (architecture)

| Gate | Status |
| --- | --- |
| 1 Understanding | Pass — key authorship, not MT |
| 2 Behavioral model | Pass — runtime location keys + mute switch |
| 3 Weakness discovery | Pass — silent production headlines, FQCN, invade |
| 4 Independent architecture | Pass — identity/binder/inspector; no Filament subclass tree |
| 5 API | Proposed in `06-public-api.md` |
| 6 Tests | Proposed in `08-testing-strategy.md` |
| 7–9 Implementation | Blocked on approval |
