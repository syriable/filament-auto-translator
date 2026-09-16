# Changelog

All notable changes to `syriable/laravel-translation` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

First release. Nothing has been published yet, so there is no upgrade path to
document and no earlier behaviour to preserve.

### Added

- **`messageReplace()`.** Declares what a bound line's `:placeholder` stands for,
  so copy needing a URL, a count or a name stays automatic instead of falling
  back to a hand-written `__()`. A closure is resolved when the slot renders,
  not when the component is built. Replacements are per component and never
  enter the resolution cache.
- **The chrome builder.** A public static `make()` taking no required argument
  and returning a schema component is walked alongside `form()`/`configure()`,
  so the section a form class wraps its fields in — its heading, its footer —
  no longer needs its copy written by hand. The embedded schema node is skipped,
  so no existing key moves; a builder that throws leaves its scope unpruned.
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
