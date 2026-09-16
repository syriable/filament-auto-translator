<?php

declare(strict_types=1);

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\File;
use Syriable\Translation\Binding\MessageBinder;
use Syriable\Translation\Binding\ResolutionExplainer;
use Syriable\Translation\Catalog\CatalogWriter;
use Syriable\Translation\Enums\MessageSlot;
use Syriable\Translation\Enums\MessageSurface;
use Syriable\Translation\Enums\ResolutionOutcome;
use Syriable\Translation\Extraction\ExtractionHost;
use Syriable\Translation\Extraction\MessageExtractor;
use Syriable\Translation\Extraction\MessageScanner;
use Syriable\Translation\MessageIdentity;

beforeEach(function () {
    $this->langPath = sys_get_temp_dir().'/translations-messages-'.uniqid('', true);
    File::ensureDirectoryExists($this->langPath);
    app()->useLangPath($this->langPath);
});

afterEach(function () {
    File::deleteDirectory($this->langPath);
});

it('creates the language file and nested key when both are missing', function () {
    $writes = app(MessageExtractor::class)->syncIdentities([
        new MessageIdentity(
            catalogId: 'filament.user-resource',
            scope: MessageSurface::Form,
            path: [],
            name: 'email',
            slot: MessageSlot::Label,
        ),
    ], 'en');

    $path = lang_path('en/filament/user-resource.php');

    expect($writes)->toHaveCount(1)
        ->and($writes[0]->action)->toBe('created')
        ->and($writes[0]->value)->toBe('Email')
        ->and(is_file($path))->toBeTrue()
        ->and(include $path)->toMatchArray([
            'form' => [
                'components' => [
                    'email' => [
                        'label' => 'Email',
                    ],
                ],
            ],
        ]);
});

it('adds a missing nested key to an existing language file', function () {
    $path = lang_path('en/filament/user-resource.php');
    app(CatalogWriter::class)->persist($path, [
        'form' => [
            'components' => [
                'email' => [
                    'label' => 'Email',
                ],
            ],
        ],
    ]);

    $writes = app(MessageExtractor::class)->syncIdentities([
        new MessageIdentity(
            catalogId: 'filament.user-resource',
            scope: MessageSurface::Form,
            path: [],
            name: 'name',
            slot: MessageSlot::Label,
        ),
    ], 'en');

    $loaded = include $path;

    expect($writes)->toHaveCount(1)
        ->and($writes[0]->value)->toBe('Name')
        ->and($loaded['form']['components']['email']['label'])->toBe('Email')
        ->and($loaded['form']['components']['name']['label'])->toBe('Name');
});

it('does not overwrite an existing message value', function () {
    $path = lang_path('en/filament/user-resource.php');
    app(CatalogWriter::class)->persist($path, [
        'form' => [
            'components' => [
                'email' => [
                    'label' => 'Keep this',
                ],
            ],
        ],
    ]);

    $writes = app(MessageExtractor::class)->writeFindings([
        [
            'key' => 'filament/user-resource.form.components.email.label',
            'catalog' => 'filament.user-resource',
            'decision' => ResolutionOutcome::Missing->value,
            'locale' => 'en',
            'text' => null,
        ],
    ], 'en');

    expect($writes)->toBeEmpty()
        ->and(include $path)->toMatchArray([
            'form' => [
                'components' => [
                    'email' => [
                        'label' => 'Keep this',
                    ],
                ],
            ],
        ]);
});

it('copies fallback locale copy into the current locale file', function () {
    app('translator')->setFallback('en');
    app()->setLocale('ar');

    $englishPath = lang_path('en/filament/user-resource.php');
    app(CatalogWriter::class)->persist($englishPath, [
        'form' => [
            'components' => [
                'email' => [
                    'label' => 'Email address',
                ],
            ],
        ],
    ]);

    $writes = app(MessageExtractor::class)->syncIdentities([
        new MessageIdentity(
            catalogId: 'filament.user-resource',
            scope: MessageSurface::Form,
            path: [],
            name: 'email',
            slot: MessageSlot::Label,
        ),
    ], 'ar');

    expect($writes)->toHaveCount(1)
        ->and($writes[0]->value)->toBe('Email address')
        ->and(include lang_path('ar/filament/user-resource.php'))->toMatchArray([
            'form' => [
                'components' => [
                    'email' => [
                        'label' => 'Email address',
                    ],
                ],
            ],
        ]);
});

it('does not write files during a dry run', function () {
    $writes = app(MessageExtractor::class)->syncIdentities([
        new MessageIdentity(
            catalogId: 'filament.user-resource',
            scope: MessageSurface::Table,
            path: ['filters'],
            name: 'is_featured',
            slot: MessageSlot::Label,
        ),
    ], 'en', dryRun: true);

    expect($writes)->toHaveCount(1)
        ->and($writes[0]->action)->toBe('would_create')
        ->and($writes[0]->value)->toBe('Is featured')
        ->and(is_file(lang_path('en/filament/user-resource.php')))->toBeFalse();
});

it('humanizes a nested table filter key as the stub value', function () {
    $value = app(CatalogWriter::class)->stubValue(
        'filament/user-resource.table.filters.is_featured.label',
        'filament.user-resource',
    );

    expect($value)->toBe('Is featured');
});

it('humanizes a resource chrome key as the stub value', function () {
    $value = app(CatalogWriter::class)->stubValue(
        'filament/user-resource.model_label',
        'filament.user-resource',
    );

    expect($value)->toBe('Model label');
});

it('keeps nested layout keys when the catalog is bound on the walk owner', function () {
    $owner = new ExtractionHost;
    app(MessageBinder::class)->setCatalogId($owner, 'filament.user-resource');

    $schema = Schema::make($owner)->components([
        Fieldset::make()
            ->key('user')
            ->schema([
                TextInput::make('name'),
            ]),
    ]);

    $fieldset = $schema->getComponents()[0];
    $childSchema = $fieldset instanceof Fieldset ? $fieldset->getChildSchema() : null;
    $field = $childSchema?->getComponents()[0] ?? null;

    expect($field)->toBeInstanceOf(TextInput::class)
        ->and(ResolutionExplainer::explain($field, MessageSlot::Label)->key)
        ->toBe('filament/user-resource.form.components.user.schema.name.label');
});

it('reports that there is nothing to write when no catalogs are missing keys', function () {
    $this->artisan('translations:extract', ['--locale' => 'en', '--dry-run' => true])
        ->expectsOutput('No message catalog changes.')
        ->assertSuccessful();
});

it('removes language keys for components that were removed from the schema', function () {
    $path = lang_path('en/filament/user-resource.php');
    app(CatalogWriter::class)->persist($path, [
        'form' => [
            'components' => [
                'authorization' => [
                    'label' => 'Authorization',
                    'schema' => [
                        'or' => [
                            'body' => 'Or',
                        ],
                        'name' => [
                            'label' => 'Name',
                            'placeholder' => 'Full name',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $owner = new ExtractionHost;
    app(MessageBinder::class)->setCatalogId($owner, 'filament.user-resource');

    $schema = Schema::make($owner)->components([
        Fieldset::make()
            ->key('authorization', isInheritable: false)
            ->schema([
                TextInput::make('name'),
            ]),
    ]);

    app(MessageScanner::class)->auditComponents(
        $schema->getComponents(),
        'filament.user-resource',
    );

    $writes = app(MessageExtractor::class)->pruneOrphans('en');
    $loaded = include $path;

    expect(array_column($writes, 'key'))
        ->toContain('filament/user-resource.form.components.authorization.schema.or.body')
        ->and($writes[0]->action)->toBe('deleted')
        ->and($loaded['form']['components']['authorization']['schema']['name']['label'])->toBe('Name')
        ->and($loaded['form']['components']['authorization']['schema']['name']['placeholder'])->toBe('Full name')
        ->and($loaded['form']['components']['authorization']['label'])->toBe('Authorization')
        ->and($loaded['form']['components']['authorization']['schema'])->not->toHaveKey('or');
});

it('does not delete language keys during a prune dry run', function () {
    $path = lang_path('en/filament/user-resource.php');
    app(CatalogWriter::class)->persist($path, [
        'form' => [
            'components' => [
                'or' => [
                    'body' => 'Or',
                ],
                'name' => [
                    'label' => 'Name',
                ],
            ],
        ],
    ]);

    $owner = new ExtractionHost;
    app(MessageBinder::class)->setCatalogId($owner, 'filament.user-resource');

    $schema = Schema::make($owner)->components([
        TextInput::make('name'),
    ]);

    app(MessageScanner::class)->auditComponents(
        $schema->getComponents(),
        'filament.user-resource',
    );

    $writes = app(MessageExtractor::class)->pruneOrphans('en', dryRun: true);

    expect($writes)->toHaveCount(1)
        ->and($writes[0]->action)->toBe('would_delete')
        ->and($writes[0]->key)->toBe('filament/user-resource.form.components.or.body')
        ->and(include $path)->toMatchArray([
            'form' => [
                'components' => [
                    'or' => [
                        'body' => 'Or',
                    ],
                    'name' => [
                        'label' => 'Name',
                    ],
                ],
            ],
        ]);
});

it('does not remove existing keys when syncing specific identities', function () {
    $path = lang_path('en/filament/user-resource.php');
    app(CatalogWriter::class)->persist($path, [
        'form' => [
            'components' => [
                'or' => [
                    'body' => 'Or',
                ],
            ],
        ],
    ]);

    $writes = app(MessageExtractor::class)->syncIdentities([
        new MessageIdentity(
            catalogId: 'filament.user-resource',
            scope: MessageSurface::Form,
            path: [],
            name: 'email',
            slot: MessageSlot::Label,
        ),
    ], 'en');

    $loaded = include $path;

    expect($writes)->toHaveCount(1)
        ->and($writes[0]->action)->toBe('created')
        ->and($loaded['form']['components']['or']['body'])->toBe('Or')
        ->and($loaded['form']['components']['email']['label'])->toBe('Email');
});

it('removes empty parent arrays after forgetting a nested key', function () {
    $tree = app(CatalogWriter::class)->forget([
        'form' => [
            'components' => [
                'or' => [
                    'body' => 'Or',
                ],
                'name' => [
                    'label' => 'Name',
                ],
            ],
        ],
    ], ['form', 'components', 'or', 'body']);

    expect($tree)->toMatchArray([
        'form' => [
            'components' => [
                'name' => [
                    'label' => 'Name',
                ],
            ],
        ],
    ]);
});
