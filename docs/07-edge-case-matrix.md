# 07 — Edge-case matrix

**Role:** Edge-Case Engineer  
**Status:** Spec for tests. No implementation.

Legend: **Det** = deterministic · **Amb** = ambiguity risk · **Fail** = failure behavior · **FB** = developer feedback · **Test** = required

Modes: inspect / strict / lenient as in `05-independent-architecture.md`.

---

## Simple fields

| Scenario | Expected | Amb | Det | Fail | FB | Test |
| --- | --- | --- | --- | --- | --- | --- |
| TextInput `email` label | `{catalog}.form.components.email.label` | Low | Yes | Mode table | inspect shows key | Yes |
| Select `role` label | Same pattern | Low | Yes | Mode table | — | Yes |
| Toggle `active` label | Same | Low | Yes | Mode table | — | Yes |
| DatePicker `starts_at` | Same | Low | Yes | Mode table | — | Yes |
| FileUpload `avatar` | Same | Low | Yes | Mode table | — | Yes |
| Custom `->label('Full name')` | Binder no-op; string used | None | Yes | — | explain=`custom_slot` | Yes |
| Optional `helper_text` missing | Slot omitted | None | Yes | Auditor records optional miss | inspect does not show key on UI | Yes |
| Optional helper present | Bound | None | Yes | — | — | Yes |

---

## Schemas

| Scenario | Expected | Amb | Det | Fail | FB | Test |
| --- | --- | --- | --- | --- | --- | --- |
| Nested section with machine name `authorization` + field `role` | `form.components.authorization.schema.role.label` | Low | Yes | — | — | Yes |
| Section heading-only, no name/key | Unbound; Filament default heading | High if we guessed | Yes (unbound) | Auditor warning `unnamed_layout` | explain=`unbound` | Yes |
| Tabs | Each tab requires a name; labels from catalog | Med | Yes if named | Unnamed tab unbound | — | Yes |
| Fieldset named | Path includes fieldset name | Low | Yes | — | — | Yes |
| Repeater `phones` + inner `number` | `form.components.phones.schema.number.label` | Low | Yes | Row UUID must not enter identity | — | Yes |
| Builder blocks | Block machine name in path | Med | Yes if blocks named | Unnamed block unbound | — | Yes |
| Group without name | Transparent (not in path) if Filament group has no name | Med | Document | Auditor note | — | Yes |
| Conditional `visible(fn)` field | Identity exists; audit must include it | High | Identity yes; presence depends on visit | Auditor extracts static tree, not only visible | CI still requires the key | Yes |
| Two `email` fields, no distinct parents | Collision | High | Auditor fail | strict/audit fail | Collision report | Yes |
| Two `email` fields under `user` vs `billing` sections | Distinct paths | None | Yes | — | — | Yes |

---

## Actions

| Scenario | Expected | Amb | Det | Fail | FB | Test |
| --- | --- | --- | --- | --- | --- | --- |
| Resource header action on edit page | `{catalog}.pages.edit-user.actions.{name}.label` | Low | Yes | Uses class kebab, not registered `edit` | — | Yes |
| Resource table row action | `{catalog}.table.actions.{name}.label` | Low | Yes | — | — | Yes |
| Nested extra modal footer action | Path includes parent action + `modal` + name | High | Yes if public parent available | `unresolved_parent` if not | explain | Yes |
| Action containing schema | Schema keys under that action path | Med | Yes | — | — | Yes |
| `DeleteAction::make()` no catalog key | Filament vendor string remains | Low | Yes | Must not stomp | explain=`vendor_default` | Yes |
| `DeleteAction` with catalog key | App copy | Low | Yes | — | — | Yes |
| `DeleteAction->label('Remove')` | Custom wins | None | Yes | — | custom_slot | Yes |
| Action named `submit` / `cancel` | Allowed as app actions; do not skip silently | Med | Yes | — | — | Yes |

---

## Tables

| Scenario | Expected | Amb | Det | Fail | FB | Test |
| --- | --- | --- | --- | --- | --- | --- |
| Text column `email` | `table.columns.email.label` | Low | Yes | — | — | Yes |
| Relationship column `author.name` | `table.columns.author__name.label` | Med | Yes | Auditor warns normalized | — | Yes |
| Image / checkbox / custom column | Bound if SlotMap has Column; not a whitelist hole | Low | Yes | Unknown subclass → unsupported+audit | — | Yes |
| Column description / tooltip optional | Optional slot rules | Low | Yes | — | — | Yes |
| Empty state heading/description | Not bound in v1. Use a schema `EmptyState` (`form.components.{name}.heading`) or Filament table defaults | Low | Yes | — | — | No |
| Filter `status` | `table.filters.status.label` | Low | Yes | — | — | Yes |
| Filter form schema | Nested under filter | Med | Yes | — | — | Yes |
| Query builder constraint | Nested under filter/constraints | Med | Yes | — | — | Yes |
| Column group | Group machine name in path | Med | Yes | Heading-only unbound | — | Yes |
| Summarizer | Under column, not a fake enum case | Low | Yes | — | — | Yes |

---

## Pages

| Scenario | Expected | Amb | Det | Fail | FB | Test |
| --- | --- | --- | --- | --- | --- | --- |
| Resource edit title | `{catalog}.pages.edit-user.title` | Low | Yes | Uses class kebab `edit-user`, not registered `edit` | — | Yes |
| Custom page `edit-profile` | `pages.edit-profile.*` for chrome; schema still resource catalog | High | Yes | — | — | Yes |
| Standalone custom page | Page’s own `phraseCatalogId()` | Low | Yes | — | — | Yes |
| Dashboard page | Same as standalone | Low | Yes | — | — | Yes |
| Auth pages (login) | Unbound unless they use the trait | Low | Yes | No silent half-binding | — | Yes |

---

## Widgets

| Scenario | Expected | Amb | Det | Fail | FB | Test |
| --- | --- | --- | --- | --- | --- | --- |
| Chart heading | Catalog heading slot; missing follows mode (never blank-without-explain) | Low | Yes | — | — | Yes |
| Stats description | Optional slot | Low | Yes | — | — | Yes |
| Table widget | Widget catalog + table scope | Med | Yes | — | — | Yes |
| Custom widget | Unbound until SlotMap/trait | Low | Yes | — | — | Yes |

---

## Reusable code

| Scenario | Expected | Amb | Det | Fail | FB | Test |
| --- | --- | --- | --- | --- | --- | --- |
| Schema method reused in two resources, no `catalog()` | Two identities | None | Yes | Duplicated lang | Auditor shows both | Yes |
| `->catalog('shared.address')` | Shared identity | None | Yes | — | — | Yes |
| Reusable action without catalog() | Identity follows usage site | Med | Yes | Often unwanted duplication | Docs + explain | Yes |
| Reusable action with catalog() | Shared | None | Yes | — | — | Yes |
| Abstract Resource base with trait | Concrete `phraseCatalogId()` | Low | Yes | — | — | Yes |

---

## Dynamic code

| Scenario | Expected | Amb | Det | Fail | FB | Test |
| --- | --- | --- | --- | --- | --- | --- |
| Closure label | Custom slot; binder no-op | None | Yes | — | — | Yes |
| `TextInput::make($type.'_value')` | Identity follows runtime name | High | Per request | Audit cannot see all names | Auditor: `dynamic_name` if name is Closure | Yes |
| Schema from closure | Bind at evaluation; audit uses a recorded/fixture schema if provided | High | Partial | Document limit | `phrases:audit` `--schema-factory` later; v1: document | Yes |
| Conditional components | Keys required for declared components | Med | Yes | — | — | Yes |

---

## Refactoring

| Scenario | Expected | Amb | Det | Fail | FB | Test |
| --- | --- | --- | --- | --- | --- | --- |
| Rename field | New identity; old key orphan | None | Yes | Audit missing+orphan | — | Yes |
| Rename resource class, same catalog id | Identity preserved | None | Yes | — | — | Yes |
| Move namespace, prefix still matches | Preserved | None | Yes | — | — | Yes |
| Change catalog id | New identity | None | Yes | — | — | Yes |
| Rename registered page | Page chrome keys change | None | Yes | — | — | Yes |
| Extract reusable schema | See reusable | — | Yes | — | — | Yes |

---

## Translation / locales / panels

| Scenario | Expected | Amb | Det | Fail | FB | Test |
| --- | --- | --- | --- | --- | --- | --- |
| Missing key, inspect | Show key | None | Yes | — | — | Yes |
| Missing key, strict | Exception | None | Yes | — | — | Yes |
| Missing key, lenient | Filament default + log | None | Yes | Not headline-as-success | — | Yes |
| Key exists | Bound | None | Yes | — | — | Yes |
| Locale missing, fallback has it | `used_fallback`; CI can fail | None | Yes | Must not look “complete” | audit `--fail-on-fallback` | Yes |
| Missing locale file | All keys missing for that locale | None | Yes | — | — | Yes |
| Two panels, same resource, same catalog | Shared copy (default) | Low | Yes | — | — | Yes |
| Two panels need different copy | Override catalog id or panel suffix (v1: document as override, no auto panel id) | Med | Dev-controlled | — | — | Doc + one test |
| Package lang vs app lang | Laravel load order; our files live in the app `lang/` | Low | Yes | Do not ship colliding `filament::` | — | Yes |
| Vendor `filament-actions::` | Untouched unless catalog key exists | Low | Yes | — | — | Yes |

---

## Modules / prefixes

| Scenario | Expected | Amb | Det | Fail | FB | Test |
| --- | --- | --- | --- | --- | --- | --- |
| `Modules\Billing` vs `App\Filament` same basename | Different prefixes | None | Yes | — | — | Yes |
| Overlapping prefixes | Boot exception | None | Yes | Loud | — | Yes |
| No prefix match | `default_prefix` | Low | Yes | — | — | Yes |

---

## Relation managers / import / export

| Scenario | Expected | Amb | Det | Fail | FB | Test |
| --- | --- | --- | --- | --- | --- | --- |
| RM with `phraseParentCatalogId()` | Nested under parent catalog `relation_managers.{id}` | Low | Yes | — | — | Yes |
| RM without parent declared | Exception inspect/strict; skip+log lenient | None | Yes | No folder guess | — | Yes |
| Shared RM used on two resources | Must pass catalog per usage or accept two identities | High | Dev-controlled | — | — | Yes |
| Import/export column labels | Catalog of importer/exporter class | Low | Yes | — | — | Yes |
| Completed notification body + choice | `Phrase::slot` + trans_choice | Med | Yes | — | — | Yes |
