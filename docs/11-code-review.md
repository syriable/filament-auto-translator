# 11 — Code review

**Role:** Code Reviewer  
**Status:** Implementation has **not** started. This is the review checklist that will be applied after code exists.

---

## When this file becomes a real review

After `src/` and `tests/` land, a reviewer fills each item with **pass / fail / n/a** and evidence (test name or file).

Until then: do not treat the package as implemented.

---

## Checklist

### Requirements (`13-feature-brief.md`)

- [ ] F1–F18 implemented or explicitly deferred in the brief
- [ ] Non-goals not smuggled in (MT, invade, Filament subclasses, options injection)

### Architecture (`05`)

- [ ] `PhraseIdentity` / compiler / binder / inspector / auditor exist as separate types
- [ ] No god service
- [ ] No `src/Filament/Resources/Resource.php` replacement tree
- [ ] Catalog id is not FQCN-after-`Filament`

### Public API (`06`)

- [ ] Names match the table (or decision log updated first)
- [ ] `phrase()` / `catalog()` / `Phrase::slot()` / `explain()` / `phrases:audit`
- [ ] No macros as the documented surface
- [ ] No `translateSchemaText`-shaped triplets

### Tests (`08`)

- [ ] Precedence, modes, fallback, modules, collisions, inspector
- [ ] Arch test: no `invade(`
- [ ] Arch test: no `max_execution_time`

### Performance (`10`)

- [ ] Request-scoped memo with locale in the key
- [ ] Boot guard against duplicate hooks
- [ ] Depth cap

### Maintainability

- [ ] SlotMap is the extension point for new component types
- [ ] Lang grammar documented and versioned
- [ ] No dead enums

### Filament / Laravel conventions

- [ ] Plugin on the panel
- [ ] Config publish
- [ ] Traits override public methods only
- [ ] `hasCustomLabel()` respected
- [ ] Strict types, Pest, PHPStan-friendly stubs

### Independence (`12`)

- [ ] Similarities are documented as problem-driven, not copy-driven

---

## Immediate anti-patterns (reject on sight)

- `invade(`
- `ini_set('max_execution_time'`
- `isImportant: true` on label hooks
- `options(fn () => $enum)` global configure
- Subclassing `Filament\Resources\Resource` in this package for apps to extend
- `translator->has($key)` without disabling fallback
- Keying off heading text
