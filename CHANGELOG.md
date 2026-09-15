# Changelog

All notable changes to `syriable/filament-auto-translator` are documented in this file.

## Unreleased

## 0.1.0 - 2026-09-12

### Added

- First release: phrase catalogs, unset-slot binding, inspector, and `phrases:audit`.
- `BindsPagePhrases` for Filament resource pages (shares the resource catalog id).
- Wizard step labels: `Step::make('first_step')` is the machine name; copy is `form.components.{step}.label`.
- Keyed wizard labels: `Wizard::make([...])->key('onboarding', isInheritable: false)` binds `form.components.onboarding.label`.
- Tab labels: `Tabs::make('user_tabs')` / `Tab::make('user')` are machine names; copy is `form.components.user_tabs.label` and `form.components.user_tabs.schema.user.label`.
- Empty state copy: `EmptyState::make('user_empty_state')` is the machine name; copy is `form.components.user_empty_state.heading` and optional `form.components.user_empty_state.description`.
- Field placeholders: optional `form.components.{field}.placeholder` binds when the field has `placeholder()` and the catalog defines the key.
- Heading is optional: keyed `Section::make()->key('user')` without `form.components.user.heading` stays untitled.
- Infolist entries: `TextEntry::make('info')` is the machine name; copy is `infolist.components.info.label` plus optional helper, hint, and placeholder.
- Schema text: `Text::make('info')` is the machine name; copy is `form.components.info.body` and optional `form.components.info.tooltip`.
- Callout copy: `Callout::make('alert')` is the machine name; copy is `form.components.alert.heading` and optional `form.components.alert.description`.
- Schema actions: `Action::make('action')` inside a form schema binds `form.components.actions.action.label`, not `pages.{page}.actions.action.label`.
- Action schema fields: `Action::make('action')->schema([TextInput::make('name_action')])` binds `form.components.actions.action.schema.components.name_action.label`.
- Field hint, prefix, and suffix actions nest under the owning field: `TextInput::make('email')->hintAction(Action::make('hint_action'))` binds `form.components.{parents}.schema.email.actions.hint_action.label`.
- Field before/after content: optional `form.components.{parents}.schema.{field}.before_content` and `after_content`, like helper text. Do not call `->beforeContent()` unless you intend to bypass the catalog.
- Notification title and body: `Notification::make()->success()` inside `Action::make('action')` binds `form.components.actions.action.notifications.success.title` and optional `body`. Do not pass an id to `Notification::make()`. Status is `success`, `danger`, `info`, or `warning`.
- Action modal chrome: optional `modal_heading`, `modal_description`, `modal_cancel_action_label`, and `modal_submit_action_label` on the action leaf, like helper text. Do not call empty `->modalHeading()` unless you intend to bypass the catalog.
- Table filters: `Filter::make('is_featured')` is the machine name; copy is `table.filters.is_featured.label` and optional `table.filters.is_featured.indicator`. Do not `->label('Is featured')`.
- Table record actions: `Action::make('view')` in `recordActions()` binds `table.record_actions.view.label`. Toolbar actions bind `table.toolbar_actions.{name}.label`. Empty-state actions bind `table.empty_state_actions.{name}.label`. Header actions bind `table.header_actions.{name}.label`.
- `php artisan phrases:sync` creates missing catalog language files and nested keys. It also removes keys for components that are no longer in the walked resource. It does not overwrite existing copy. Use `--dry-run` to preview and `--no-prune` to skip deletions. Keep `phrases:audit` read-only for CI. Action closures that call `Notification::make()->success()` (or danger/info/warning) get `notifications.{status}.title` keys; notification `body` stays optional.
- `php artisan phrases:apply` writes `__('catalog.key')` onto matching `::make()` chains for keys that already exist in the lang file, including `Notification::make()->title()` / `->body()` for action notification statuses. Resource chrome keys write `getModelLabel()`, `getPluralModelLabel()`, `getPluralLabel()`, `getNavigationLabel()`, `getNavigationGroup()`, and page `getTitle()`. A setter whose argument is already that catalog key is rewritten to `__('…')`. Other existing setters are not overwritten. Use `--dry-run` to preview. Explicit setters bypass the binder.
- Keyed fieldset labels: `Fieldset::make()->key('authorization')` binds optional `form.components.authorization.label`. Omit the key to keep the fieldset untitled. `Fieldset::make('Visible')` stays unbound.

### Changed

- Package renamed to `syriable/filament-auto-translator`. PHP namespace is `Syriable\Filament\Plugins\AutoTranslator`. Filament plugin id is `syriable-filament-auto-translator`.
- Resource chrome language keys now match Filament method names at the catalog root: `model_label`, `plural_model_label`, `plural_label`, `navigation_label`, `navigation_group`. Nested `model.label` and `navigation.label` are no longer compiled.
- Page keys use the page class kebab (`EditUser` → `edit-user`), not the registered route name (`edit`). Page chrome is `title`, `subheading`, and `navigation_label`. Page action schemas nest under `schema.components`. Extra modal footer actions nest under `extra_modal_footer_actions`.
- Form copy lives under `form.components` instead of `schema`. Keyed layout children nest under `{layout}.schema`. Action modal fields use `schema.components`. Infolist entries use `infolist.components`. Section `description` is optional, like helper text. Nested `schema.{field}` keys are no longer compiled.
- Table columns live under `table.columns` instead of the table root. Optional `prefix` binds on columns that have `prefix()`. Flat `table.{column}.label` keys are no longer compiled.

### Fixed

- Using `BindsPhrases` on a Filament page fatals: page `getModelLabel()` is not static.
- Keyed `Section` headings no longer recurse through `getHeading()` while resolving their own phrase.
- Filament `Section::key()` must pass `isInheritable: false` so child fields keep a flat state path.
- `phrase()` / `catalog()` macros can set bindings again: Laravel rebinds the macro `$this`, so they now call public binder methods instead of a private property.
