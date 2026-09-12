# 02 — Source behavior analysis

**Role:** Documentation Analyst (fan-in by Lead Architect)  
**Status:** Research complete. No implementation.  
**Public corpus:** plugin listing, v2 documentation (three pages: installation, usage, upgrade), changelog. GitHub/Packagist for the source are private.

This is not a paraphrase of the marketing site. For each observable feature: problem, probable design reason, assumptions, failure if false, DX, alternatives.

---

## 1. Location-derived keys

**Problem.** Key naming is a design problem with no Filament standard.  
**Why chosen.** Make the lang tree isomorphic to the UI tree so nobody debates names.  
**Assumptions.** Component `make()` names are stable identifiers; one owner class per rendered tree.  
**If false.** Rename field → orphaned keys. English heading in `Section::make()` becomes the key. Shared schemas follow the *current* Livewire owner.  
**DX.** After one resource, keys “explain themselves.” Before that, the idea is easy to miss (the plugin’s own public review says this).  
**Alternatives.** Explicit `__('filament.users.fields.role.label')`; `translateLabel()`; extractors; JSON keys.

## 2. Opt-in via subclass or contract+trait

**Problem.** Global magic would translate vendor plugins and block incremental adoption.  
**Why chosen.** Identity = “this Livewire implements the contract.” Subclasses are the zero-thought path; traits exist because real apps already have abstract bases.  
**Assumptions.** You can change `extends`. Filament’s class tree is yours.  
**If false.** Third-party bases never translate. `make:resource` keeps generating vendor parents. One forgotten relation manager is silently English.  
**DX.** Two recipes, both inheritance-heavy.  
**Alternatives.** Namespace allowlist; attributes; decorate without replacing parents.

## 3. Required vs optional slots + production mute

**Problem.** Requiring every hint drowns apps; silently headline-casing labels hides gaps.  
**Why chosen.** Labels required; hints optional; non-production shows the raw key; production returns null and lets Filament headline.  
**Assumptions.** `APP_ENV=production` is the right switch; visual QA finds gaps.  
**If false.** Staging-as-production is mute. Unvisited modals never warn. Optional keys are invisible even locally. English headline looks “translated.”  
**DX.** The missing key *is* the editor. There is no audit command.  
**Alternatives.** Strict mode (an unused enum exists in source); CI extractor; never fallback; always fallback.

## 4. Redirect resource forms/tables to the resource file

**Problem.** Create/edit/view share one `Resource::form()`. Page-scoped keys would triplicate copy.  
**Why chosen.** Ownership follows definition site for the main form/table; titles/header actions stay under the page.  
**Assumptions.** Canonical page names (`create`, `edit`, `view`, `index`).  
**If false.** A custom `edit-profile` page does not redirect. Multiple named schemas need extra context that docs never specify.  
**DX.** One file per resource — the cleanliness win.

## 5. Nesting as nested PHP arrays

**Problem.** Repeaters, affix actions, wizards, extra modal footers have no flat key.  
**Why chosen.** Lang array *is* the schema.  
**Assumptions.** Sibling names are unique; `make()` values do not contain dots that Laravel will split.  
**If false.** Collisions; `foo.bar` names split; changelog is full of recursion/wizard/modal fixes.  
**DX.** Powerful once learned; unspecified at the edges.

## 6. Manual “translate this sub-key”

**Problem.** Select options, notification bodies, closures cannot be hooked.  
**Why chosen.** Pass the live component plus a group plus a relative suffix.  
**Assumptions.** Caller has the component in scope and picks the right group enum.  
**If false.** Wrong group → wrong file, silently. Docs mention a summarizer group the clone enum does not have.  
**DX.** The admitted 1%. The public API is three methods + enums.

## 7. Namespace remap

**Problem.** Default FQCN slicing at `Filament` is wrong for multi-panel apps.  
**Why chosen.** One fluent map on the panel plugin.  
**Assumptions.** FQCN contains `Filament`; prefix match is unambiguous.  
**If false.** Modules (`Modules\Billing\Filament\…`) collide after the first `Filament` segment. Substring replace can rewrite the wrong prefix.  
**DX.** Entire documented configuration surface is this one map.

## 8. Key override macro

**Problem.** Reusable actions should not inherit `…/user-resource.pages.edit_user.actions.foo`.  
**Why chosen.** Macro: relative rename or absolute root.  
**Assumptions.** Macros are acceptable DX; absolute flag is remembered.  
**If false.** IDE/PHPStan miss the method. Forgotten absolute keys duplicate or collide. Changelog: absolute keys on a relation-manager `create` action were redirected wrong.  
**Alternatives.** A real method on a concern; a `HasFixedCatalog` interface.

## 9. Global `configureUsing` binder

**Problem.** Need to attach copy without the app calling `label()`.  
**Why chosen.** Hook every relevant Filament type; closures run at evaluation time; `isImportant` on actions so package wins.  
**Assumptions.** Overwriting user `->label()` on actions is fine; reconstructing prebuilt actions via `invade()->setUp()` recovers vendor defaults.  
**If false.** Custom action labels vanish. `setUp()` side effects. Private cache walks break on Filament patches. Enum `options()` injection fights app option maps. Plugin boot on one panel installs process-global hooks.

## 10. FQCN → lang path

**Problem.** Need a file per resource without configuration.  
**Why chosen.** Slice after `Filament`, kebab, slashes.  
**Assumptions.** `App\Filament\Resources\UserResource` is the world.  
**If false.** Rename/move class → new file, old keys dead. Acronym kebab can change across Laravel versions. Modular apps collide.

## 11. Author-stated limits

- Goal is ~99% `__()` removal, not 100%.
- Complex nesting: email the author if a key is missing (no coverage matrix).
- V4 merged forms/infolists into schemas → breaking key rename; upgrade by search-replace and clicking the app.
- Roadmap is empty.
- Docs Laravel 10+ vs clone `illuminate/contracts` 11+.
- Storefront still advertises Filament v3 on purchase SKUs while the clone targets v4/v5.

## 12. Documentation gaps a five-year maintainer would feel

- No published key grammar (required vs optional method lists live only in source).
- Docs use kebab page keys (`edit-user`); implementation uses snake class basenames (`edit_user`).
- No extractor, no CI, no debug overlay, no test recipe.
- Widgets, importers, relation managers listed in the install table and then abandoned in Usage.
- Unused `Mode` enum never documented.
- `max_execution_time = 5` during boot is undocumented and dangerous if it ships.

## 13. Lead Architect note

Public docs successfully sell **one idea**. They do not specify the **grammar, coverage, failure modes, or upgrade machine**. Those live in private source and changelog bug titles. Our package must publish the grammar as a contract, not as folklore.
