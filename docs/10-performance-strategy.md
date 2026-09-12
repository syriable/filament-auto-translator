# 10 — Performance strategy

**Role:** Performance Engineer  
**Status:** Architecture review. No premature optimization.

---

## Cost centers (expected)

| Work | Frequency | Cost | Notes |
| --- | --- | --- | --- |
| `configureUsing` registration | Once per process boot | Low | Guard against double boot |
| Parent walk | Per unbound slot per component per request | Medium | Dominates if unmemoized |
| `Translator::has` + `get` | Per slot | Low (Laravel file cache) | Presence + fetch |
| Inspector bag | Per bound slot in inspect query | Low | Only when enabled |
| Auditor | CLI | High on large apps | Offline |

Source-shaped costs we refuse: `invade` of action caches, reconstructing actions, `Resource::infolist()` probes, `max_execution_time` as a recursion brake, per-slot uncached full walks (they shipped a cache and reverted it).

---

## Goals (v1, measurable later)

- A resource schema of **50 fields × 4 slots** binds in well under typical request time; target **< 10ms** additional CPU on a warm translator in local benchmarks (not a CI gate until measured).
- Parent walk **O(depth)** per component with memo so slots share the path.
- Boot hooks **O(number of component classes in SlotMap)**, not O(resources).
- No extra SQL.
- No extra HTTP.
- No disk except translator.

Do not set these as test failures until we have a benchmark harness. First implementation is correctness.

---

## Justified optimizations (v1)

1. **Request memo** of compiled path per component object. Cost is real (many slots). Complexity is a WeakMap. **Yes.**
2. **Memo identity → translation result** including locale. **Yes.**
3. **Skip binder** if Livewire/owner is not `PhraseCatalog`. Cheap instanceof. **Yes.**
4. **Skip if custom slot.** `hasCustomLabel()` is a property check. **Yes.**
5. **Compile-time SlotMap** as PHP arrays in code, not attributes reflection on every request. **Yes.**

## Not v1

- Persistent cache of keys across requests (invalidation vs lang file edits is messy).
- Generating PHP catalogs from AST (extractor v2).
- Precomputing keys at `optimize`.
- Replacing Laravel translator.

---

## Request lifecycle

```text
Panel boot → PhrasePlugin (once) → register non-important configureUsing
Request → Filament evaluates slot closures
       → binder: catalog? custom? memo? compile? has? get?
Response → optional inspector dump if query flag
```

Octane: bind `PhraseMemo` as **scoped** / reset on `RequestReceived`.

---

## Auditor performance

- Iterate registered panel resources/pages/widgets (Filament discovery), not `vendor/`.
- One translator `has` per key per locale requested.
- `--locale=ar` default to current app locale, not all locales.

---

## When to optimize further

Only after a profile (Laravel Debugbar / Clockwork) shows binder time as a top bucket. Then: reduce SlotMap methods for unused slots, or lazy-bind optional slots only when lang has the group (still needs a cheap has() on the parent key).

Every later optimization must cite measured cost, frequency, and complexity — this document’s rule.
