# 09 — Security and reliability

**Role:** Security & Reliability Reviewer  
**Status:** Architecture review. No implementation.

---

## Threat model

The package runs in the **developer’s** Filament panel process. It does not take end-user text and eval it. Risk is still real: request-time tree walking, translation key construction, logging, and config.

---

## Filesystem

- **Do not** read arbitrary paths from component names.
- Lang files go through Laravel’s translator only.
- Auditor may list `lang/` files; constrain to the configured prefix directories. No `../` from catalog ids.
- Catalog ids and machine names: allow `a-z0-9_-` plus `__` from dot normalization. Reject `/`, `\0`, `..`.

---

## Dynamic evaluation

- **Do not** `eval` closures beyond Filament’s own `evaluate()`.
- **Do not** invoke `Resource::infolist(Schema::make())` as a heuristic (source does this; expensive and can throw without a record).
- **Do not** `invade()` private properties (also a reliability/compat issue).

---

## Reflection

- Forbidden on Filament/Livewire internals.
- PHPStan stubs are compile-time only.

---

## User-controlled values in keys

Component names usually come from developers. Some columns use relationship names from models. Still:

- Compiled keys are used as translator keys, not as Blade/PHP.
- Log the compiled key; do not log record payloads from closures.

If a future feature interpolates record data into keys, reject it. Record data belongs in `__($key, $replace)`, never in the key.

---

## DoS / recursion / memory

- Parent depth cap (`max_parent_depth`, default 32) → exception.
- **Never** `ini_set('max_execution_time')`.
- Memoize per request so 10 slots × 50 fields do not walk the tree 500 times from scratch.
- Auditor CLI on huge apps: stream catalogs; do not load every lang file for every locale unless asked (`--locale`).
- Binder must be idempotent: two panels registering the plugin must not stack duplicate hooks (boot guard).

---

## Cache poisoning

- v1 memo is **request-scoped** (`WeakMap` / array on a scoped singleton). Not the Laravel cache store.
- Do not persist compiled keys across locale switches in a long-lived Octane worker without locale in the memo key. Locale **must** be part of the memo key.
- Octane: binder singleton must not keep identities from the previous request (request-scoped container binding).

---

## Side effects

- Filling a label must not call `options()`, `setUp()`, or reconstruct actions.
- Logging in lenient mode: once per identity per request (or a bounded bag) to avoid log floods.

---

## Reliability

| Property | Rule |
| --- | --- |
| Determinism | Same identity + locale + lang files → same result |
| Failure isolation | Unknown component type → unbound, not a 500 |
| Predictable fallback | Table in architecture; no hidden headline-as-success |
| Upgrade | Public APIs only |
| Boot failures | Prefix overlap and invalid mode fail **loud** at boot |

---

## Supply chain

- MIT, public Packagist/GitHub when published.
- No license Composer plugin that executes at install.
- No `auth.json` instructions in the package.

---

## Residual risk

Deep custom nested actions without public parent links will be unbound rather than silently wrong. That is an accepted reliability trade-off.
