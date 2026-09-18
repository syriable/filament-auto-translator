# Changelog

All notable changes to `syriable/laravel-translation` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

First release. Nothing has been published yet, so there is no upgrade path to
document and no earlier behaviour to preserve.

### Fixed

- **Keyed Filament layout wrappers no longer invent a label.** The generic
  “components from other packages” binder treated any keyed schema component
  with `HasLabel` as needing `form.components.{key}.label`. Filament’s own
  `Actions` container is often keyed only for Livewire identity — SettingsPage
  uses `->key('form-actions')` — and has no action of its own, so debug mode
  showed a spurious key. First-party `Filament\…` classes are skipped; only
  third-party keyed components still use that path.

### Added

- **Standalone panel pages own `{prefix}/pages/{page}.php`.** Custom Filament
  pages outside a resource default to catalog `{prefix}.pages.{class-kebab}`
  (same domain prefix as resources, usually `filament`). Chrome
  (`title`, `navigation_label`, `subheading`) and header `actions` sit at the
  catalog root so keys are `filament/pages/{page}.title` — not
  `filament/{page}.pages.{page}.title`. Resource pages keep nesting under
  `pages.{kebab}` inside the resource catalog. Extract / debug / inline walk
  panel pages that expose `translationDomain()`.

- **Cluster chrome from the catalog.** `HasClusterTranslations` binds
  `getClusterBreadcrumb()` to root `cluster_breadcrumb` and reuses root
  `navigation_label` for `getNavigationLabel()`, matching resource chrome.
  Extract, debug, and inline walk panel clusters that expose
  `translationDomain()`.

- **A keyed wrapper reaches the schema it embeds.** A form class describes its
  chrome in `make()` and its fields in `configure()`, and the chrome reaches
  the fields through `EmbeddedSchema` — which Filament renders by name rather
  than by nesting, so the fields' container has no parent component and the
  walk stopped there. A button in a section's footer read
  `form.components.account.schema.actions.register.label` while a field the
  same section wrapped read `form.components.nickname.label`. The walk now
  crosses that gap, so both read the section's path. An unkeyed wrapper still
  contributes nothing, so chrome without keys leaves every key where it is;
  adding `->key()` to a wrapper that already has copy underneath moves those
  keys, and `translations:extract` writes the new ones and prunes the old.

- **`messageHtml()`.** Marks a component's catalog line as markup, so footer
  copy carrying a link renders as a link instead of printing its own source.
  Nothing is guessed from the line's contents: a stray `<` never changes how a
  catalog is escaped. Under `messageHtml()` the line is trusted and every
  replacement poured into it is escaped, unless the caller hands over an
  `HtmlString` of its own.

- **A module with no language directory no longer stops extraction.** Laravel
  knows a module's translation namespace only because the module package
  registered it, which it does only once `resources/lang` exists — so a module
  that has never been translated threw `UnknownDomainNamespaceException` and
  `translations:extract` wrote nothing at all, for any catalog. It now
  registers the namespace itself and reports it. The module has to exist on
  disk, asked of the module package when one is installed and otherwise looked
  for under the new `module_path` config key, so a typo is still unknown and
  still throws. Only the namespace is registered: the directory arrives with
  the first file written into it, so `--dry-run` still writes nothing.

- **Components from other packages are bound.** Anything extending
  `Filament\Schemas\Components\Component` with a label — a separator shipped by
  a plugin, say — was invisible: its copy could not come from the catalog
  however it was written. Identity already worked; only the binding was
  missing. It applies to a component that names itself with `->key()`, and
  keeps whatever label the component was built with as the fallback.

- **`validation_attribute` and `below_label` are bound slots.** A field's name in
  validation messages, and the line under its label, come from the catalog at
  the field's own path rather than from copy an application had to keep
  somewhere else and look up by hand. Both are optional: with no key,
  `validation_attribute` leaves Filament its default (the label lowercased),
  so write it only where the two differ. Adding them to `MessageSlot` is also
  what keeps the keys from being pruned — the pruner recognises a trailing
  slot name and checks the field above it, which a key outside the enum could
  never benefit from.

- **`messageReplace()`.** Declares what a bound line's `:placeholder` stands for,
  so copy needing a URL, a count or a name stays automatic instead of falling
  back to a hand-written `__()`. A closure is resolved when the slot renders,
  not when the component is built. Replacements are per component and never
  enter the resolution cache.
- **The chrome builder.** A public static `make()` taking no required argument
  and returning a schema component is walked alongside `form()`/`configure()`,
  so the section a form class wraps its fields in — its heading, its footer —
  no longer needs its copy written by hand. A builder that throws leaves its
  scope unpruned.
- **Every child schema is walked**, not only a component's default one, so copy
  in a section's footer or header is reached. Catalogs using those will see new
  optional keys offered on the next `translations:extract`; nothing already
  translated stops resolving.

- **Message binding.** Filament fills an unset label, heading, hint, placeholder,
  description or modal string from the language file, so PHP keeps machine names
  and the copy lives in `lang/`. An explicit `->label('…')` always wins; the
  package only fills slots you left unset.
- **Vocabulary.** The public surface is named for **translation**
  (`Syriable\Translation`, `Translations::discoverIn()`, `translations:*`,
  `config/translations.php`, `HasModelTranslations`), while the unit of one
  translatable string stays a **message** internally (`MessageIdentity`,
  `MessageSlot`, `MessageKeyBuilder`) — the split `symfony/translation` makes
  with its own `MessageCatalogue`. It also keeps the package from colliding
  with an application's own `messaging` or `catalog` modules.
- **Translation domains.** A class names its domain with the
  `#[TranslationDomain]` attribute. A domain is either dotted
  (`identity.user-edit` → `lang/{locale}/identity/user-edit.php`) or namespaced
  against a registered translation namespace (`identity::user-edit` → that
  namespace's own `{locale}/user-edit.php`), so a module keeps its copy beside
  its code.
- **Resource domains from a prefix map.** Resources and their pages derive a
  domain from `domainPrefixes()` instead of declaring one, via
  `HasModelTranslations` and `HasPageTranslations`. A prefix ending in `::` names a
  translation namespace rather than a folder, so `'Modules\Identity' =>
  'identity::'` puts a module's resource copy in the module. A resource or page
  that declares `#[TranslationDomain]` keeps that domain instead.
- **Discovery.** `Translations::discoverIn($path, $namespace)` registers a directory
  the way `discoverResources()` does. It works with or without a Filament panel,
  because a schema may render on a public Livewire page where no panel boots, and
  it is idempotent, so every module can call it from its own service provider.
- **`translations:extract`** creates the language keys a walked schema needs, and
  removes keys for components that no longer exist. It never overwrites copy that
  is already translated. `--dry-run` previews; `--no-prune` keeps orphans.
- **Console commands see panel configuration.** `translations:extract`, `translations:debug`
  and `translations:inline` boot every registered panel before walking it, so
  prefixes, discovery paths and the missing-message policy registered on a panel
  plugin apply on the CLI exactly as they do in the browser.
- **`translations:debug`** reports missing and obsolete messages for a locale without
  writing anything, so it is safe in CI. `--fail-on-missing` and
  `--fail-on-fallback` turn findings into a non-zero exit.
- **`translations:inline`** writes `__('domain.key')` onto matching `::make()` chains
  for keys that already exist, for teams that prefer explicit setters.
- **Missing-message policy.** `on_missing` chooses what a missing message does:
  `keep_vendor_label` (default) keeps Filament's own label, `debug` renders the
  compiled key so you can see what to add, `strict` throws. It is deliberately
  not called `fallback`: that word already means the fallback **locale** here,
  which is a separate thing reported as `used_fallback_locale`.
- **`Translations::explain()`** reports how a component's message resolved — which
  key, which locale, and why — for debugging a binding that did not take.
- **`Translations::slot()`** looks up a single message by hand where automatic
  binding does not reach, such as select option labels.
- **Identity overrides.** `->messageName('…')` renames a component's leaf and
  `->domain('…')` moves it to another domain, without changing what is displayed.
- **Coverage** for forms, infolists, tables (columns, filters, record, toolbar,
  header and empty-state actions), schema and page actions, action modal chrome,
  notifications raised inside actions, wizard steps, tabs, callouts, schema text,
  and resource and page chrome.
