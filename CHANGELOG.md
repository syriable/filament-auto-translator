# Changelog

All notable changes to `syriable/filament-auto-translator` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

First release. The package was developed unreleased as `syriable/laravel-translation`;
see [Upgrading](README.md#upgrading) for the renamed classes, config and commands. Every
compiled message key is unchanged.

### Added

- `AutoTranslatorPlugin` binds unset copy on fields, infolist entries, sections,
  fieldsets, wizards, steps, tabs, empty states, callouts, text, actions and their
  modals, table columns, filters and action notifications from language files.
- `HasResourceTranslations`, `HasPageTranslations` and `HasClusterTranslations` bind
  resource, page and cluster chrome.
- Translation domains derived from a namespace prefix map, or declared with
  `#[TranslationDomain]`, including namespaced domains for modules.
- Schema domains for Livewire schemas outside a panel, registered with
  `AutoTranslator::discoverIn()`.
- `messageName()`, `messageDomain()`, `messageReplace()` and `messageHtml()`
  component macros.
- `auto-translator:audit`, `auto-translator:extract` and `auto-translator:inline`.
- `AutoTranslator::explain()` and `AutoTranslator::message()`.
- A missing-message policy: `keep_vendor_label`, `debug` or `strict`.

### Fixed

- Extraction walks a resource's `infolist()`. It used to treat the `infolist`
  scope as walked whenever `form()` built, so infolist copy was deleted as orphaned.
- A schema domain whose chrome builder throws no longer has its `form` copy pruned.
- A scope is pruned only when every builder contributing to it succeeded; a table
  built by a page no longer makes a failed resource table look complete.
