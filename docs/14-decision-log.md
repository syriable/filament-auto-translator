# 14 — Decision log

Architectural decisions. Fan-in resolutions included.

---

## Decision: Product is phrase identity, not machine translation

**Context:** Package name contains “auto-translator”; source marketing is easy to misread as MT.  
**Options:** (a) MT/AI layer (b) key authorship engine (c) both.  
**Chosen:** (b).  
**Reason:** Same underlying problem as the source; MT is a different product.  
**Trade-offs:** Name vs behavior mismatch — docs must lead with the problem.  
**Rejected:** Shipping translators or locale detection in v1.

---

## Decision: Preserve identifier-only PHP

**Context:** Framework analyst recommended only `->label(__())`. Product archaeologist said identifier-only PHP is the essential value.  
**Disagreement:** Explicit `__()` is more future-proof; identifier-only is the reason the product exists.  
**Evidence:** Plugin review (“aha” is removing key authorship); Filament `hasCustomLabel()` lets us fill unset slots without overwrite.  
**Chosen:** Identifier-only default + explicit wins.  
**Reason:** Solving the same problem without the DX is a different, weaker product. Filling **unset** slots avoids the source’s overwrite bugs.  
**Trade-offs:** Still uses `configureUsing` (fragile-ish) but not `isImportant` / `invade`.  
**Rejected:** Pure helper API that still requires `__()` on every field.

---

## Decision: Do not ship replacement Filament subclasses

**Context:** Source makes apps extend package Resource/Page/Widget types.  
**Options:** (a) subclass tree (b) traits on app bases (c) global magic.  
**Chosen:** (b) `BindsPhrases` + `PhraseCatalog`.  
**Reason:** `make:resource` drift; Filament majors become consumer breaks; fluxwork already wants app/module abstract bases.  
**Trade-offs:** One-line trait instead of search-replace of `extends`.  
**Rejected:** Parallel `Syriable\Filament\Plugins\AutoTranslator\Filament\…` type tree (source-shaped).

---

## Decision: Catalog id is explicit, not FQCN-after-`Filament`

**Context:** Source keys include kebab FQCN; rename/move orphans files; modules collide.  
**Options:** (a) FQCN (b) content hash (c) explicit catalog id with prefix map.  
**Chosen:** (c) `{prefix}.{basename-kebab}` overridable via `phraseCatalogId()`.  
**Reason:** Survives refactors; modules first-class; still readable.  
**Trade-offs:** Requires prefix config for modular apps (one array).  
**Rejected:** Hash keys (unreadable); FQCN slicing (unstable, colliding).

---

## Decision: Longest prefix wins; overlap is a boot error

**Context:** Source `translationGroups` uses substring replace order.  
**Chosen:** Longest namespace prefix match; two prefixes compiling to the same catalog id throw at boot.  
**Reason:** Silent wrong-file lookup is worse than a loud boot failure.  
**Rejected:** Last-map-wins, contains(), sequential replace.

---

## Decision: Never key off visible headings

**Context:** Source copies section headings into keys. Changing copy renames keys.  
**Chosen:** Machine name / `key()` / `phrase()` only. Heading-only layout is unbound + audited.  
**Reason:** Identity must not be user-visible text.  
**Trade-offs:** Developers must name sections. That is a convention we will document, not hide.

---

## Decision: Presence checks disable fallback

**Context:** Laravel `has()` defaults to fallback=true; Arabic misses look complete.  
**Chosen:** `has($key, locale: current, fallback: false)`. Fallback hits are a first-class auditor state.  
**Rejected:** Treating English fallback as success.

---

## Decision: Three modes with a table, not a dead enum

**Context:** Source has unused Strict/Balanced/Loose and uses `isProduction()` as mute.  
**Chosen:** `inspect` / `strict` / `lenient` with published behavior. Default inspect in local, lenient in production, strict in CI.  
**Reason:** Environment is not the same as “can reviewers see missing copy?”  
**Rejected:** `APP_ENV=production` as the only switch; shipping an unwired Mode enum.

---

## Decision: No invade, no max_execution_time, no private caches

**Context:** Clone uses all three. Changelog is recursion and cache bugs.  
**Chosen:** Public parent APIs + depth cap exception.  
**Reason:** Filament minors must not break us. Timeouts must not kill exports.  
**Trade-offs:** Some deeply custom nested actions may be unbound until a public API exists; inspector marks `unresolved_parent` instead of guessing wrong.

---

## Decision: Do not overwrite custom labels; do not inject options()

**Context:** Source `isImportant` on actions; global enum options.  
**Chosen:** `hasCustomLabel()` (and equivalents) → no-op. Never set `options()`.  
**Reason:** Explicit wins; enum i18n stays Filament `HasLabel`.  
**Rejected:** Reconstructing prebuilt actions via `new` + `invade()->setUp()`.

---

## Decision: Support Filament 5 only in v1

**Context:** Source supports 3/4/5 at high internal cost. Host app is Filament 5.  
**Chosen:** PHP 8.4, Laravel 13, Filament 5.  
**Reason:** Maintenance cost; schema APIs differ by major.  
**Rejected:** Matching the source’s version matrix.

---

## Decision: One manual lookup method

**Context:** Source has three translate*Text methods and group enums that docs get wrong.  
**Chosen:** `Phrase::slot($component, PhraseSlot, relative:)`.  
**Reason:** Smallest API; slot enum is the grouping.  
**Rejected:** Parallel methods per form/table/action.

---

## Decision: Dotted Filament names

**Context:** Table columns like `author.name`; Laravel translator splits on `.`.  
**Chosen:** Normalize `.` → `__` in the identity name segment (`author__name`). Auditor warns.  
**Reason:** Keep a single nest level; reversible; documented.  
**Rejected:** Using only the last segment (collision `user.name` vs `company.name`); splitting into extra nest (ambiguous).

---

## Decision: Mixed adoption is default

**Context:** Existing apps have `__()` and `translateLabel()`.  
**Chosen:** Custom slots win; catalogs opt in per class.  
**Rejected:** All-or-nothing migration; compatibility layer that reads source-plugin keys.

---

## Decision: Package lives in this repo as a path Composer package for v1

**Context:** User asked for `syriable/filament-auto-translator` on branch `feature-auto-translator`.  
**Chosen:** `packages/filament-auto-translator` + path repository; extract to its own GitHub repo when the API is stable.  
**Reason:** Design and first implementation can iterate with fluxwork.  
**Rejected:** Immediate separate repo before architecture approval.

---

## Decision: No implementation until approval

**Context:** Mission gate.  
**Chosen:** Docs 01–10, 13–14 now; 11–12 as checklists/conceptual audit; code after sign-off.  
**Rejected:** Scaffolding `src/` during research.
