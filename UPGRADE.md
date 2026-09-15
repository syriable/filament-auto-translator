# Upgrading

## v1 → v2

v2 renames the package and its whole public surface around the vocabulary
i18n already uses. Nothing about how keys are compiled changed, so **your
language files keep working untouched** — a snapshot test in the suite pins
every compiled key byte for byte.

The upgrade is mechanical. The consumer surface is small on purpose.

### 1. Change the dependency

```jsonc
// composer.json
"require": {
-   "syriable/filament-auto-translator": "^1.0"
+   "syriable/laravel-message-catalog": "^2.0"
}
```

```bash
composer update syriable/laravel-message-catalog
```

The namespace changes from `Syriable\Filament\Plugins\AutoTranslator\` to
`Syriable\MessageCatalog\`. The package is no longer named for Filament,
because schema domains render outside a panel too.

### 2. Declare domains with an attribute

The `PhraseCatalog` interface is gone. It declared a **static** method, so
any base class implementing it made every subclass that did not implement
the method unloadable — fatal for the anonymous classes Livewire single-file
components are built from.

```diff
-use Syriable\Filament\Plugins\AutoTranslator\Contracts\PhraseCatalog;
+use Syriable\MessageCatalog\Attributes\TranslationDomain;

-class EditForm implements PhraseCatalog
-{
-    public static function phraseCatalogId(): string
-    {
-        return 'identity::user-edit';
-    }
-
+#[TranslationDomain('identity::user-edit')]
+class EditForm
+{
     public static function configure(Schema $schema): Schema { … }
 }
```

Classes that derive their domain from the prefix map rather than declaring
one rename the method instead:

```diff
-public static function phraseCatalogId(): string
+public static function translationDomain(): string
```

### 3. Register in one place

`registerHooks()` is no longer part of the flow, and discovery no longer
needs the registry directly:

```diff
-app(SchemaCatalogRegistry::class)->discover(in: $path, for: $namespace);
-app(PhraseBinder::class)->registerHooks();
+Messages::discoverIn($path, $namespace);
```

`Messages::discoverIn()` works with or without a Filament panel, and is
idempotent, so it is safe to call from every module's service provider.

### 4. Rename the plugin and its methods

```diff
-PhrasePlugin::make()
-    ->catalogPrefixes(['Modules\\Identity' => 'identity'])
-    ->discoverSchemaCatalogs(in: $path, for: $namespace)
-    ->mode(PhraseMode::Lenient)
+MessageCatalogPlugin::make()
+    ->domainPrefixes(['Modules\\Identity' => 'identity'])
+    ->discoverIn(in: $path, for: $namespace)
+    ->onMissing(MissingMessagePolicy::Fallback)
```

### 5. Rename the config file and its keys

`config/auto-translator.php` becomes `config/messages.php`. If you never
published it, there is nothing to do.

| v1 | v2 |
|---|---|
| `mode` | `on_missing` |
| `catalog_prefixes` | `domain_prefixes` |
| `default_prefix` | `default_domain_prefix` |
| `schema_catalog_paths` | `discover_paths` |
| `inspect_query` | `debug_query` |
| `PHRASE_MODE` | `MESSAGES_ON_MISSING` |

### 6. Update commands

| v1 | v2 |
|---|---|
| `phrases:sync` | `messages:extract` |
| `phrases:audit` | `messages:debug` |
| `phrases:apply` | `messages:inline` |

### 7. Note the new default for missing messages

**This is the one change that alters what users see.**

`mode` defaulted to `inspect`, which rendered a missing message's raw
compiled key into the page. Ship a screen before its copy and users saw
`identity::user-edit.form.components.tabs-user.label`.

`on_missing` now defaults to `fallback`: Filament's own label is used and
nothing leaks. `messages:debug` remains the reliable way to find gaps.

To keep the old behaviour, opt into it explicitly:

```php
// config/messages.php
'on_missing' => 'debug',
```

The policy names changed with it: `inspect` → `debug`, `lenient` →
`fallback`, `strict` unchanged.

### 8. Verify

```bash
php artisan messages:extract --locale=en,ar --dry-run
```

It should report **no changes**. That is the proof the upgrade moved no
keys: every message still resolves to the file it resolved to before.

```bash
php artisan messages:debug --locale=ar
```

### Renamed classes

| v1 | v2 |
|---|---|
| `Contracts\PhraseCatalog` | `Attributes\TranslationDomain` |
| `PhraseBinder` | `Binding\MessageBinder` |
| `PhraseBindings` | `Binding\ComponentBindings` |
| `PhraseMemo` | `Binding\ResolutionCache` |
| `PhraseRegistry` | `Binding\MessageOverrides` |
| `Inspection\PhraseInspector` | `Binding\ResolutionExplainer` |
| `PhraseResolver` | `Catalog\MessageResolver` |
| `Sync\PhraseLangWriter` | `Catalog\CatalogWriter` |
| `Sync\PhraseOrphanPruner` | `Catalog\ObsoleteMessagePruner` |
| `Sync\PhraseCatalogSyncer` | `Extraction\MessageExtractor` |
| `Sync\CatalogWalkLivewire` | `Extraction\ExtractionHost` |
| `Audit\PhraseAuditor` | `Extraction\MessageScanner` |
| `Discovery\SchemaCatalog` | `Discovery\DiscoveredDomain` |
| `Discovery\SchemaCatalogDiscoverer` | `Discovery\DomainDiscoverer` |
| `Discovery\SchemaCatalogRegistry` | `Discovery\DomainRegistry` |
| `CatalogPrefixResolver` | `Discovery\DomainPrefixResolver` |
| `PhraseIdentity` | `MessageIdentity` |
| `PhraseKeyCompiler` | `MessageKeyBuilder` |
| `PhraseResolution` | `Resolution` |
| `Phrase` | `Messages` |
| `PhrasePlugin` | `MessageCatalogPlugin` |
| `Enums\PhraseScope` | `Enums\MessageSurface` |
| `Enums\PhraseSlot` | `Enums\MessageSlot` |
| `Enums\PhraseDecision` | `Enums\ResolutionOutcome` |
| `Enums\PhraseMode` | `Enums\MissingMessagePolicy` |
| `Concerns\BindsPhrases` | `Concerns\HasModelMessages` |
| `Concerns\BindsPagePhrases` | `Concerns\HasPageMessages` |
| `Concerns\ResolvesPhrases` | `Concerns\ResolvesTranslationDomain` |
