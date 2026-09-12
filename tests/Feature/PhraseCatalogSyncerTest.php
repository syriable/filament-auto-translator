<?php

declare(strict_types=1);

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\File;
use Syriable\Filament\Plugins\AutoTranslator\Audit\PhraseAuditor;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseDecision;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseScope;
use Syriable\Filament\Plugins\AutoTranslator\Enums\PhraseSlot;
use Syriable\Filament\Plugins\AutoTranslator\Inspection\PhraseInspector;
use Syriable\Filament\Plugins\AutoTranslator\PhraseBinder;
use Syriable\Filament\Plugins\AutoTranslator\PhraseIdentity;
use Syriable\Filament\Plugins\AutoTranslator\Sync\CatalogWalkLivewire;
use Syriable\Filament\Plugins\AutoTranslator\Sync\PhraseCatalogSyncer;
use Syriable\Filament\Plugins\AutoTranslator\Sync\PhraseLangWriter;

beforeEach(function () {
    $this->langPath = sys_get_temp_dir().'/auto-translator-phrases-'.uniqid('', true);
    File::ensureDirectoryExists($this->langPath);
    app()->useLangPath($this->langPath);
});

afterEach(function () {
    File::deleteDirectory($this->langPath);
});

it('creates the language file and nested key when both are missing', function () {
    $writes = app(PhraseCatalogSyncer::class)->syncIdentities([
        new PhraseIdentity(
            catalogId: 'filament.user-resource',
            scope: PhraseScope::Form,
            path: [],
            name: 'email',
            slot: PhraseSlot::Label,
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
    app(PhraseLangWriter::class)->persist($path, [
        'form' => [
            'components' => [
                'email' => [
                    'label' => 'Email',
                ],
            ],
        ],
    ]);

    $writes = app(PhraseCatalogSyncer::class)->syncIdentities([
        new PhraseIdentity(
            catalogId: 'filament.user-resource',
            scope: PhraseScope::Form,
            path: [],
            name: 'name',
            slot: PhraseSlot::Label,
        ),
    ], 'en');

    $loaded = include $path;

    expect($writes)->toHaveCount(1)
        ->and($writes[0]->value)->toBe('Name')
        ->and($loaded['form']['components']['email']['label'])->toBe('Email')
        ->and($loaded['form']['components']['name']['label'])->toBe('Name');
});

it('does not overwrite an existing phrase value', function () {
    $path = lang_path('en/filament/user-resource.php');
    app(PhraseLangWriter::class)->persist($path, [
        'form' => [
            'components' => [
                'email' => [
                    'label' => 'Keep this',
                ],
            ],
        ],
    ]);

    $writes = app(PhraseCatalogSyncer::class)->writeFindings([
        [
            'key' => 'filament/user-resource.form.components.email.label',
            'catalog' => 'filament.user-resource',
            'decision' => PhraseDecision::Missing->value,
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
    app(PhraseLangWriter::class)->persist($englishPath, [
        'form' => [
            'components' => [
                'email' => [
                    'label' => 'Email address',
                ],
            ],
        ],
    ]);

    $writes = app(PhraseCatalogSyncer::class)->syncIdentities([
        new PhraseIdentity(
            catalogId: 'filament.user-resource',
            scope: PhraseScope::Form,
            path: [],
            name: 'email',
            slot: PhraseSlot::Label,
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
    $writes = app(PhraseCatalogSyncer::class)->syncIdentities([
        new PhraseIdentity(
            catalogId: 'filament.user-resource',
            scope: PhraseScope::Table,
            path: ['filters'],
            name: 'is_featured',
            slot: PhraseSlot::Label,
        ),
    ], 'en', dryRun: true);

    expect($writes)->toHaveCount(1)
        ->and($writes[0]->action)->toBe('would_create')
        ->and($writes[0]->value)->toBe('Is featured')
        ->and(is_file(lang_path('en/filament/user-resource.php')))->toBeFalse();
});

it('humanizes a nested table filter key as the stub value', function () {
    $value = app(PhraseLangWriter::class)->stubValue(
        'filament/user-resource.table.filters.is_featured.label',
        'filament.user-resource',
    );

    expect($value)->toBe('Is featured');
});

it('humanizes a resource chrome key as the stub value', function () {
    $value = app(PhraseLangWriter::class)->stubValue(
        'filament/user-resource.model_label',
        'filament.user-resource',
    );

    expect($value)->toBe('Model label');
});

it('keeps nested layout keys when the catalog is bound on the walk owner', function () {
    $owner = new CatalogWalkLivewire;
    app(PhraseBinder::class)->setCatalogId($owner, 'filament.user-resource');

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
        ->and(PhraseInspector::explain($field, PhraseSlot::Label)->key)
        ->toBe('filament/user-resource.form.components.user.schema.name.label');
});

it('reports that there is nothing to write when no catalogs are missing keys', function () {
    $this->artisan('phrases:sync', ['--locale' => 'en', '--dry-run' => true])
        ->expectsOutput('No phrase catalog changes.')
        ->assertSuccessful();
});

it('removes language keys for components that were removed from the schema', function () {
    $path = lang_path('en/filament/user-resource.php');
    app(PhraseLangWriter::class)->persist($path, [
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

    $owner = new CatalogWalkLivewire;
    app(PhraseBinder::class)->setCatalogId($owner, 'filament.user-resource');

    $schema = Schema::make($owner)->components([
        Fieldset::make()
            ->key('authorization', isInheritable: false)
            ->schema([
                TextInput::make('name'),
            ]),
    ]);

    app(PhraseAuditor::class)->auditComponents(
        $schema->getComponents(),
        'filament.user-resource',
    );

    $writes = app(PhraseCatalogSyncer::class)->pruneOrphans('en');
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
    app(PhraseLangWriter::class)->persist($path, [
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

    $owner = new CatalogWalkLivewire;
    app(PhraseBinder::class)->setCatalogId($owner, 'filament.user-resource');

    $schema = Schema::make($owner)->components([
        TextInput::make('name'),
    ]);

    app(PhraseAuditor::class)->auditComponents(
        $schema->getComponents(),
        'filament.user-resource',
    );

    $writes = app(PhraseCatalogSyncer::class)->pruneOrphans('en', dryRun: true);

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
    app(PhraseLangWriter::class)->persist($path, [
        'form' => [
            'components' => [
                'or' => [
                    'body' => 'Or',
                ],
            ],
        ],
    ]);

    $writes = app(PhraseCatalogSyncer::class)->syncIdentities([
        new PhraseIdentity(
            catalogId: 'filament.user-resource',
            scope: PhraseScope::Form,
            path: [],
            name: 'email',
            slot: PhraseSlot::Label,
        ),
    ], 'en');

    $loaded = include $path;

    expect($writes)->toHaveCount(1)
        ->and($writes[0]->action)->toBe('created')
        ->and($loaded['form']['components']['or']['body'])->toBe('Or')
        ->and($loaded['form']['components']['email']['label'])->toBe('Email');
});

it('removes empty parent arrays after forgetting a nested key', function () {
    $tree = app(PhraseLangWriter::class)->forget([
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
