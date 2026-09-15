# Changelog

All notable changes to `syriable/laravel-message-catalog` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

First release. Nothing has been published yet, so there is no upgrade path to
document and no earlier behaviour to preserve.

### Added

- **Message binding.** Filament fills an unset label, heading, hint, placeholder,
  description or modal string from the language file, so PHP keeps machine names
  and the copy lives in `lang/`. An explicit `->label('…')` always wins; the
  package only fills slots you left unset.
- **Translation domains.** A class names its domain with the
  `#[TranslationDomain]` attribute. A domain is either dotted
  (`identity.user-edit` → `lang/{locale}/identity/user-edit.php`) or namespaced
  against a registered translation namespace (`identity::user-edit` → that
  namespace's own `{locale}/user-edit.php`), so a module keeps its copy beside
  its code.
- **Resource domains from a prefix map.** Resources and their pages derive a
  domain from `domainPrefixes()` instead of declaring one, via
  `HasModelMessages` and `HasPageMessages`.
- **Discovery.** `Messages::discoverIn($path, $namespace)` registers a directory
  the way `discoverResources()` does. It works with or without a Filament panel,
  because a schema may render on a public Livewire page where no panel boots, and
  it is idempotent, so every module can call it from its own service provider.
- **`messages:extract`** creates the language keys a walked schema needs, and
  removes keys for components that no longer exist. It never overwrites copy that
  is already translated. `--dry-run` previews; `--no-prune` keeps orphans.
- **`messages:debug`** reports missing and obsolete messages for a locale without
  writing anything, so it is safe in CI. `--fail-on-missing` and
  `--fail-on-fallback` turn findings into a non-zero exit.
- **`messages:inline`** writes `__('domain.key')` onto matching `::make()` chains
  for keys that already exist, for teams that prefer explicit setters.
- **Missing-message policy.** `on_missing` chooses what a missing message does:
  `fallback` (default) keeps Filament's own label, `debug` renders the compiled
  key so you can see what to add, `strict` throws.
- **`Messages::explain()`** reports how a component's message resolved — which
  key, which locale, and why — for debugging a binding that did not take.
- **`Messages::slot()`** looks up a single message by hand where automatic
  binding does not reach, such as select option labels.
- **Identity overrides.** `->messageName('…')` renames a component's leaf and
  `->domain('…')` moves it to another domain, without changing what is displayed.
- **Coverage** for forms, infolists, tables (columns, filters, record, toolbar,
  header and empty-state actions), schema and page actions, action modal chrome,
  notifications raised inside actions, wizard steps, tabs, callouts, schema text,
  and resource and page chrome.
