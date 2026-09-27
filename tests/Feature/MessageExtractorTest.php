<?php

declare(strict_types=1);

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\File;
use Syriable\Filament\Plugins\AutoTranslator\AutoTranslator;
use Syriable\Filament\Plugins\AutoTranslator\Binding\MessageOptions;
use Syriable\Filament\Plugins\AutoTranslator\Enums\ChangeType;
use Syriable\Filament\Plugins\AutoTranslator\Enums\Chrome;
use Syriable\Filament\Plugins\AutoTranslator\Enums\MessageScope;
use Syriable\Filament\Plugins\AutoTranslator\Enums\MessageSlot;
use Syriable\Filament\Plugins\AutoTranslator\Extraction\LanguageFiles;
use Syriable\Filament\Plugins\AutoTranslator\Extraction\MessageExtractor;
use Syriable\Filament\Plugins\AutoTranslator\Messages\MessageIdentity;
use Syriable\Filament\Plugins\AutoTranslator\Scanning\MessageScanner;
use Syriable\Filament\Plugins\AutoTranslator\Scanning\ScanHost;

beforeEach(function () {
    $this->langPath = sys_get_temp_dir().'/translations-messages-'.uniqid('', true);
    File::ensureDirectoryExists($this->langPath);
    app()->useLangPath($this->langPath);
});

afterEach(function () {
    File::deleteDirectory($this->langPath);
});

it('creates the language file and nested key when both are missing', function () {
    $writes = app(MessageExtractor::class)->extractIdentities([
        new MessageIdentity(
            domain: 'filament.user-resource',
            scope: MessageScope::Form,
            path: [],
            name: 'email',
            slot: MessageSlot::Label,
        ),
    ], 'en');

    $path = lang_path('en/filament/user-resource.php');

    expect($writes)->toHaveCount(1)
        ->and($writes[0]->type)->toBe(ChangeType::Created)
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
    app(LanguageFiles::class)->write($path, [
        'form' => [
            'components' => [
                'email' => [
                    'label' => 'Email',
                ],
            ],
        ],
    ]);

    $writes = app(MessageExtractor::class)->extractIdentities([
        new MessageIdentity(
            domain: 'filament.user-resource',
            scope: MessageScope::Form,
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
    app(LanguageFiles::class)->write($path, [
        'form' => [
            'components' => [
                'email' => [
                    'label' => 'Keep this',
                ],
            ],
        ],
    ]);

    $writes = app(MessageExtractor::class)->extractIdentities([
        new MessageIdentity('filament.user-resource', MessageScope::Form, [], 'email', MessageSlot::Label),
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
    app(LanguageFiles::class)->write($englishPath, [
        'form' => [
            'components' => [
                'email' => [
                    'label' => 'Email address',
                ],
            ],
        ],
    ]);

    $writes = app(MessageExtractor::class)->extractIdentities([
        new MessageIdentity(
            domain: 'filament.user-resource',
            scope: MessageScope::Form,
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
    $writes = app(MessageExtractor::class)->extractIdentities([
        new MessageIdentity(
            domain: 'filament.user-resource',
            scope: MessageScope::Table,
            path: ['filters'],
            name: 'is_featured',
            slot: MessageSlot::Label,
        ),
    ], 'en', dryRun: true);

    expect($writes)->toHaveCount(1)
        ->and($writes[0]->type)->toBe(ChangeType::Created)
        ->and($writes[0]->dryRun)->toBeTrue()
        ->and($writes[0]->value)->toBe('Is featured')
        ->and(is_file(lang_path('en/filament/user-resource.php')))->toBeFalse();
});

it('humanizes a chrome key as the stub value', function () {
    $writes = app(MessageExtractor::class)->extractIdentities([Chrome::ModelLabel->identity('filament.user-resource')], 'en', dryRun: true);

    expect($writes[0]->key)->toBe('filament/user-resource.model_label')
        ->and($writes[0]->value)->toBe('Model label');
});

it('keeps nested layout keys when the domain is set on the scan host', function () {
    $owner = new ScanHost;
    app(MessageOptions::class)->setDomain($owner, 'filament.user-resource');

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
        ->and(AutoTranslator::explain($field, MessageSlot::Label)->key)
        ->toBe('filament/user-resource.form.components.user.schema.name.label');
});

it('reports that there is nothing to write when no domain is missing keys', function () {
    $this->artisan('auto-translator:extract', ['--locale' => 'en', '--dry-run' => true])
        ->expectsOutputToContain('Nothing to change.')
        ->assertSuccessful();
});

it('removes language keys for components that were removed from the schema', function () {
    $path = lang_path('en/filament/user-resource.php');
    app(LanguageFiles::class)->write($path, [
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

    $owner = new ScanHost;
    app(MessageOptions::class)->setDomain($owner, 'filament.user-resource');

    $schema = Schema::make($owner)->components([
        Fieldset::make()
            ->key('authorization', isInheritable: false)
            ->schema([
                TextInput::make('name'),
            ]),
    ]);

    $scan = app(MessageScanner::class)->scanComponents($schema->getComponents(), 'filament.user-resource', ['form']);

    $writes = app(MessageExtractor::class)->prune($scan, 'en');
    $loaded = include $path;

    expect(array_column($writes, 'key'))
        ->toContain('filament/user-resource.form.components.authorization.schema.or.body')
        ->and($writes[0]->type)->toBe(ChangeType::Deleted)
        ->and($loaded['form']['components']['authorization']['schema']['name']['label'])->toBe('Name')
        ->and($loaded['form']['components']['authorization']['schema']['name']['placeholder'])->toBe('Full name')
        ->and($loaded['form']['components']['authorization']['label'])->toBe('Authorization')
        ->and($loaded['form']['components']['authorization']['schema'])->not->toHaveKey('or');
});

it('does not delete language keys during a prune dry run', function () {
    $path = lang_path('en/filament/user-resource.php');
    app(LanguageFiles::class)->write($path, [
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

    $owner = new ScanHost;
    app(MessageOptions::class)->setDomain($owner, 'filament.user-resource');

    $schema = Schema::make($owner)->components([
        TextInput::make('name'),
    ]);

    $scan = app(MessageScanner::class)->scanComponents($schema->getComponents(), 'filament.user-resource', ['form']);

    $writes = app(MessageExtractor::class)->prune($scan, 'en', dryRun: true);

    expect($writes)->toHaveCount(1)
        ->and($writes[0]->type)->toBe(ChangeType::Deleted)
        ->and($writes[0]->dryRun)->toBeTrue()
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
    app(LanguageFiles::class)->write($path, [
        'form' => [
            'components' => [
                'or' => [
                    'body' => 'Or',
                ],
            ],
        ],
    ]);

    $writes = app(MessageExtractor::class)->extractIdentities([
        new MessageIdentity(
            domain: 'filament.user-resource',
            scope: MessageScope::Form,
            path: [],
            name: 'email',
            slot: MessageSlot::Label,
        ),
    ], 'en');

    $loaded = include $path;

    expect($writes)->toHaveCount(1)
        ->and($writes[0]->type)->toBe(ChangeType::Created)
        ->and($loaded['form']['components']['or']['body'])->toBe('Or')
        ->and($loaded['form']['components']['email']['label'])->toBe('Email');
});

it('removes empty parent arrays after forgetting a nested key', function () {
    $tree = app(LanguageFiles::class)->forget([
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
