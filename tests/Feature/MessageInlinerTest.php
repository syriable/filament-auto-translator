<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\File;
use Syriable\MessageCatalog\Apply\PhrasePhpApplier;
use Syriable\MessageCatalog\Binding\MessageBinder;
use Syriable\MessageCatalog\Catalog\CatalogWriter;
use Syriable\MessageCatalog\Extraction\ExtractionHost;

beforeEach(function () {
    $this->langPath = sys_get_temp_dir().'/auto-translator-apply-lang-'.uniqid('', true);
    File::ensureDirectoryExists($this->langPath);
    app()->useLangPath($this->langPath);
});

afterEach(function () {
    File::deleteDirectory($this->langPath);
});

it('writes label and placeholder methods for keys present in the language file', function () {
    app(CatalogWriter::class)->persist(lang_path('en/filament/user-resource.php'), [
        'form' => [
            'components' => [
                'name' => [
                    'label' => 'Name',
                    'placeholder' => 'Enter your name',
                ],
            ],
        ],
    ]);

    $phpPath = sys_get_temp_dir().'/auto-translator-apply-'.uniqid('', true).'.php';
    File::put($phpPath, <<<'PHP'
<?php

TextInput::make('name')
    ->required(),
PHP);

    $owner = new ExtractionHost;
    app(MessageBinder::class)->setCatalogId($owner, 'filament.user-resource');

    $schema = Schema::make($owner)->components([
        TextInput::make('name')->required(),
    ]);

    $writes = app(PhrasePhpApplier::class)->applyComponents(
        $schema->getComponents(),
        [$phpPath],
        'filament.user-resource',
        'en',
    );

    expect(array_column($writes, 'method'))->toContain('label', 'placeholder')
        ->and(File::get($phpPath))->toContain("->label(__('filament/user-resource.form.components.name.label'))")
        ->and(File::get($phpPath))->toContain("->placeholder(__('filament/user-resource.form.components.name.placeholder'))");

    File::delete($phpPath);
});

it('rewrites a raw catalog key on an existing setter and adds missing methods', function () {
    app(CatalogWriter::class)->persist(lang_path('en/filament/user-resource.php'), [
        'form' => [
            'components' => [
                'name' => [
                    'label' => 'Name',
                    'placeholder' => 'Enter your name',
                ],
            ],
        ],
    ]);

    $phpPath = sys_get_temp_dir().'/auto-translator-apply-'.uniqid('', true).'.php';
    File::put($phpPath, <<<'PHP'
<?php

TextInput::make('name')
    ->label('filament/user-resource.form.components.name.label')
    ->required(),
PHP);

    $owner = new ExtractionHost;
    app(MessageBinder::class)->setCatalogId($owner, 'filament.user-resource');

    $schema = Schema::make($owner)->components([
        TextInput::make('name')->required(),
    ]);

    $writes = app(PhrasePhpApplier::class)->applyComponents(
        $schema->getComponents(),
        [$phpPath],
        'filament.user-resource',
        'en',
    );

    expect(array_column($writes, 'action'))->toContain('updated', 'created')
        ->and(File::get($phpPath))->toContain("->label(__('filament/user-resource.form.components.name.label'))")
        ->and(File::get($phpPath))->toContain("->placeholder(__('filament/user-resource.form.components.name.placeholder'))")
        ->and(File::get($phpPath))->not->toContain("->label('filament/user-resource.form.components.name.label')");

    File::delete($phpPath);
});

it('does not write methods for keys missing from the language file', function () {
    app(CatalogWriter::class)->persist(lang_path('en/filament/user-resource.php'), [
        'form' => [
            'components' => [
                'name' => [
                    'label' => 'Name',
                ],
            ],
        ],
    ]);

    $phpPath = sys_get_temp_dir().'/auto-translator-apply-'.uniqid('', true).'.php';
    File::put($phpPath, <<<'PHP'
<?php

TextInput::make('name')
    ->required(),
PHP);

    $owner = new ExtractionHost;
    app(MessageBinder::class)->setCatalogId($owner, 'filament.user-resource');

    $schema = Schema::make($owner)->components([
        TextInput::make('name')->required(),
    ]);

    app(PhrasePhpApplier::class)->applyComponents(
        $schema->getComponents(),
        [$phpPath],
        'filament.user-resource',
        'en',
    );

    expect(File::get($phpPath))
        ->toContain("->label(__('filament/user-resource.form.components.name.label'))")
        ->not->toContain('placeholder');

    File::delete($phpPath);
});

it('writes notification title from the language file onto Notification::make', function () {
    app(CatalogWriter::class)->persist(lang_path('en/filament/user-resource.php'), [
        'form' => [
            'components' => [
                'actions' => [
                    'action' => [
                        'label' => 'Action',
                        'notifications' => [
                            'success' => [
                                'title' => 'Success',
                                'body' => 'Saved.',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $phpPath = sys_get_temp_dir().'/auto-translator-apply-'.uniqid('', true).'.php';
    File::put($phpPath, <<<'PHP'
<?php

Action::make('action')
    ->action(function (): void {
        Notification::make()
            ->success()
            ->send();
    }),
PHP);

    $owner = new ExtractionHost;
    app(MessageBinder::class)->setCatalogId($owner, 'filament.user-resource');

    $schema = Schema::make($owner)->components([
        Action::make('action')
            ->action(function (): void {
                Notification::make()->success()->send();
            }),
    ]);

    $writes = app(PhrasePhpApplier::class)->applyComponents(
        $schema->getComponents(),
        [$phpPath],
        'filament.user-resource',
        'en',
    );

    expect(array_column($writes, 'method'))->toContain('title', 'body')
        ->and(File::get($phpPath))->toContain("->title(__('filament/user-resource.form.components.actions.action.notifications.success.title'))")
        ->and(File::get($phpPath))->toContain("->body(__('filament/user-resource.form.components.actions.action.notifications.success.body'))")
        ->and(File::get($phpPath))->not->toContain("Action::make('action')\n    ->title(");

    File::delete($phpPath);
});

it('writes getModelLabel when the language file has model_label', function () {
    $phpPath = sys_get_temp_dir().'/auto-translator-apply-'.uniqid('', true).'.php';
    File::put($phpPath, <<<'PHP'
<?php

class UserResource
{
    public static function form(): void
    {
    }
}
PHP);

    $writes = app(PhrasePhpApplier::class)->applyClassMethods($phpPath, [
        [
            'method' => 'getModelLabel',
            'key' => 'filament/user-resource.model_label',
            'static' => true,
            'return' => 'string',
        ],
    ]);

    expect(array_column($writes, 'method'))->toContain('getModelLabel')
        ->and(File::get($phpPath))->toContain('public static function getModelLabel(): string')
        ->and(File::get($phpPath))->toContain("return __('filament/user-resource.model_label');");

    File::delete($phpPath);
});
