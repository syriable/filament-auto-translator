# 04 — Adversarial review

**Role:** Adversarial Reviewer (fan-in by Lead Architect)  
**Intent:** Break the concept. Especially incorrect copy with no exception.

Verified against the clone: `configureUsing` + `invade()`, `translator->has()` with production mute, `ini_set('max_execution_time', 5)`, unused `Mode` enum.

---

## The core failure mode

The source is a **location-to-key compiler with a production mute switch**.

Location is not identity. Location changes when you rename a resource, move a namespace, nest a field, extract a reusable schema, or follow a new Filament folder layout. The lang file does not move. Production then treats the miss as success and prints `Str::headline($name)`.

If the source locale is English, **QA cannot see the failure**. `email` → “Email” looks translated.

Incorrect translations without exceptions are not an edge case. They are the production path.

---

## Findings

| ID | Finding | Severity | Signal |
| --- | --- | --- | --- |
| A1 | Missing required keys in production → Filament headline | Critical | Silent |
| A2 | `translator->has()` default fallback=true → other locale looks complete in English | Critical | Silent |
| A3 | FQCN path: rename/move class orphans the entire lang file | Critical | Silent |
| A4 | Two modules both containing `Filament\Resources\InvoiceResource` collide | Critical | Silent |
| A5 | Plugin `boot()` installs **process-global** `configureUsing`; “per panel” is a lie | Critical | Silent |
| A6 | `max_execution_time = 5` on panel boot | Critical | Loud timeout / killed work |
| A7 | Section/tab/wizard identity taken from heading text | High | Silent |
| A8 | Duplicate `make('email')` in one tree share one phrase | High | Silent (wrong sibling copy) |
| A9 | Reusable schema without absolute override duplicates or collides | High | Silent |
| A10 | Dynamic/conditional schemas: unvisited branches never audited | High | Silent |
| A11 | Nested action parent from private caches; cache miss → wrong path | High | Silent (wrong copy) |
| A12 | `isImportant` overwrites user action `->label()` | High | Silent |
| A13 | Global enum `options()` injection fights app option maps | High | Silent |
| A14 | Relation manager parent guessed from folders; miss → all null | High | Silent |
| A15 | Custom page names skip resource-form redirect | High | Silent (wrong bucket) |
| A16 | Table column whitelist: image/checkbox/custom columns stay English | High | Silent |
| A17 | Chart widget heading: missing key → blank, no parent fallback | High | Visible blank |
| A18 | Docs kebab vs code snake (`edit-user` vs `edit_user`) | High | Silent if you follow docs |
| A19 | Optional slots never warn, even locally | High | Silent |
| A20 | No extractor, inspector, or CI | High | DX |
| A21 | Macros invisible to IDE/PHPStan; dynamic properties on 8.4 | Medium | Analysis |
| A22 | Dead `Mode` enum (strict never wired) | Medium | Missed product |
| A23 | Child resource class does not inherit parent lang file | Medium | Silent |
| A24 | Locale switch vs Filament evaluated-closure caches | Medium | Stale copy |
| A25 | Filament minor can rename private caches this package `invade()`s | Critical | Upgrade |
| A26 | Apps must extend plugin subclasses; `make:resource` drifts | Medium | Adoption |
| A27 | Substring `translationGroups` replace order is ambiguous | High | Silent |
| A28 | `getPluralModelLabel()` falling back to singular parent (clone) | Medium | Wrong word |

---

## Attack surfaces (requested)

| Surface | Silent failure |
| --- | --- |
| Ambiguous keys | `tabs`, `wizard`, `name`, `email`, `submit` reused |
| Collisions | Two modules; two panels; schema inlined twice |
| Unstable generation | FQCN kebab, heading-as-key, field rename |
| Rename resource/field/namespace/page | Lang orphans; production headlines |
| Nested components | Path depends on live parent walk + cache |
| Reusable components | No shared root unless absolute macro |
| Closures / conditionals | Keys only for this request’s tree |
| Relation managers | Folder guess fails |
| Nested actions | Name search vs path |
| Anonymous / custom components | Not in whitelist |
| Multiple panels | Global hooks; groups from “current” panel |
| Multiple locales | `has()` + fallback = English pretending to be Arabic |
| Package vs app translations | Slash path vs `filament::` namespace |
| Inheritance | Child ≠ parent catalog |
| Runtime labels | Destroyed on actions; ignored on fields if set |
| Debugging | No dump; docs disagree with runtime |
| Performance | Full tree + invade per slot per field; a key-cache was shipped and reverted |

---

## What a robust design must survive

1. Identity is not filesystem path and not FQCN fashion.
2. Missing is observable per locale, with fallback tracked separately. Headlines are not translations.
3. No global overwrite. No `invade`. No process time-limit hack.
4. Modules and panels are first-class; overlap is an error, not a substring race.
5. Extract + audit, including hidden action schemas — not “click the app.”
6. Public Filament APIs only. Do not force `extends` of package Filament types.

---

## Fan-in: disagreements

See `14-decision-log.md`. The important fight is **preserve identifier-only PHP** (product) vs **never use `configureUsing`** (framework/adversarial). The resolution is: fill **unset** slots only, using `hasCustomLabel()` and equivalents, never `isImportant`, never `invade`.
