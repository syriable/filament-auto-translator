<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\File;
use Syriable\Filament\Plugins\AutoTranslator\Binding\MessageOptions;
use Syriable\Filament\Plugins\AutoTranslator\Enums\ChangeType;
use Syriable\Filament\Plugins\AutoTranslator\Enums\Chrome;
use Syriable\Filament\Plugins\AutoTranslator\Extraction\LanguageFiles;
use Syriable\Filament\Plugins\AutoTranslator\Inlining\MessageInliner;
use Syriable\Filament\Plugins\AutoTranslator\Inlining\SourceChange;
use Syriable\Filament\Plugins\AutoTranslator\Scanning\ChromeMessage;
use Syriable\Filament\Plugins\AutoTranslator\Scanning\ScanHost;
use Syriable\Filament\Plugins\AutoTranslator\Scanning\Surface;
use Syriable\Filament\Plugins\AutoTranslator\Scanning\SurfaceCollector;

beforeEach(function () {
    $this->langPath = sys_get_temp_dir().'/messages-apply-lang-'.uniqid('', true);
    File::ensureDirectoryExists($this->langPath);
    app()->useLangPath($this->langPath);
});

afterEach(function () {
    File::deleteDirectory($this->langPath);
});

it('writes label and placeholder methods for keys present in the language file', function () {
    app(LanguageFiles::class)->write(lang_path('en/filament/user-resource.php'), [
        'form' => [
            'components' => [
                'name' => [
                    'label' => 'Name',
                    'placeholder' => 'Enter your name',
                ],
            ],
        ],
    ]);

    $phpPath = sys_get_temp_dir().'/messages-apply-'.uniqid('', true).'.php';
    File::put($phpPath, <<<'PHP'
<?php

TextInput::make('name')
    ->required(),
PHP);

    $owner = new ScanHost;
    app(MessageOptions::class)->setDomain($owner, 'filament.user-resource');

    $schema = Schema::make($owner)->components([
        TextInput::make('name')->required(),
    ]);

    $writes = inlineInto($schema->getComponents(), $phpPath, 'filament.user-resource');

    expect(array_column($writes, 'method'))->toContain('label', 'placeholder')
        ->and(File::get($phpPath))->toContain("->label(__('filament/user-resource.form.components.name.label'))")
        ->and(File::get($phpPath))->toContain("->placeholder(__('filament/user-resource.form.components.name.placeholder'))");

    File::delete($phpPath);
});

it('rewrites a bare key on an existing setter and adds missing methods', function () {
    app(LanguageFiles::class)->write(lang_path('en/filament/user-resource.php'), [
        'form' => [
            'components' => [
                'name' => [
                    'label' => 'Name',
                    'placeholder' => 'Enter your name',
                ],
            ],
        ],
    ]);

    $phpPath = sys_get_temp_dir().'/messages-apply-'.uniqid('', true).'.php';
    File::put($phpPath, <<<'PHP'
<?php

TextInput::make('name')
    ->label('filament/user-resource.form.components.name.label')
    ->required(),
PHP);

    $owner = new ScanHost;
    app(MessageOptions::class)->setDomain($owner, 'filament.user-resource');

    $schema = Schema::make($owner)->components([
        TextInput::make('name')->required(),
    ]);

    $writes = inlineInto($schema->getComponents(), $phpPath, 'filament.user-resource');

    expect(array_map(fn (SourceChange $change) => $change->type, $writes))->toContain(ChangeType::Updated, ChangeType::Created)
        ->and(File::get($phpPath))->toContain("->label(__('filament/user-resource.form.components.name.label'))")
        ->and(File::get($phpPath))->toContain("->placeholder(__('filament/user-resource.form.components.name.placeholder'))")
        ->and(File::get($phpPath))->not->toContain("->label('filament/user-resource.form.components.name.label')");

    File::delete($phpPath);
});

it('does not write methods for keys missing from the language file', function () {
    app(LanguageFiles::class)->write(lang_path('en/filament/user-resource.php'), [
        'form' => [
            'components' => [
                'name' => [
                    'label' => 'Name',
                ],
            ],
        ],
    ]);

    $phpPath = sys_get_temp_dir().'/messages-apply-'.uniqid('', true).'.php';
    File::put($phpPath, <<<'PHP'
<?php

TextInput::make('name')
    ->required(),
PHP);

    $owner = new ScanHost;
    app(MessageOptions::class)->setDomain($owner, 'filament.user-resource');

    $schema = Schema::make($owner)->components([
        TextInput::make('name')->required(),
    ]);

    inlineInto($schema->getComponents(), $phpPath, 'filament.user-resource');

    expect(File::get($phpPath))
        ->toContain("->label(__('filament/user-resource.form.components.name.label'))")
        ->not->toContain('placeholder');

    File::delete($phpPath);
});

it('writes notification title from the language file onto Notification::make', function () {
    app(LanguageFiles::class)->write(lang_path('en/filament/user-resource.php'), [
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

    $phpPath = sys_get_temp_dir().'/messages-apply-'.uniqid('', true).'.php';
    File::put($phpPath, <<<'PHP'
<?php

Action::make('action')
    ->action(function (): void {
        Notification::make()
            ->success()
            ->send();
    }),
PHP);

    $owner = new ScanHost;
    app(MessageOptions::class)->setDomain($owner, 'filament.user-resource');

    $schema = Schema::make($owner)->components([
        Action::make('action')
            ->action(function (): void {
                Notification::make()->success()->send();
            }),
    ]);

    $writes = inlineInto($schema->getComponents(), $phpPath, 'filament.user-resource');

    expect(array_column($writes, 'method'))->toContain('title', 'body')
        ->and(File::get($phpPath))->toContain("->title(__('filament/user-resource.form.components.actions.action.notifications.success.title'))")
        ->and(File::get($phpPath))->toContain("->body(__('filament/user-resource.form.components.actions.action.notifications.success.body'))")
        ->and(File::get($phpPath))->not->toContain("Action::make('action')\n    ->title(");

    File::delete($phpPath);
});

it('writes getModelLabel when the language file has model_label', function () {
    app(LanguageFiles::class)->write(lang_path('en/filament/user-resource.php'), ['model_label' => 'User']);

    $class = 'InlinedResource'.bin2hex(random_bytes(4));
    $phpPath = sys_get_temp_dir()."/{$class}.php";
    File::put($phpPath, <<<PHP
<?php

class {$class}
{
    public static function form(): void
    {
    }
}
PHP);
    require $phpPath;

    $writes = app(MessageInliner::class)->inlineSurface(new Surface(
        domain: 'filament.user-resource',
        chrome: [new ChromeMessage(Chrome::ModelLabel, $class, 'filament.user-resource')],
    ), 'en');

    expect(array_column($writes, 'method'))->toBe(['getModelLabel'])
        ->and($writes[0]->type)->toBe(ChangeType::Created)
        ->and(File::get($phpPath))->toContain('public static function getModelLabel(): string')
        ->and(File::get($phpPath))->toContain("return __('filament/user-resource.model_label');");

    File::delete($phpPath);
});

it('writes no chrome method the language file has no copy for', function () {
    app(LanguageFiles::class)->write(lang_path('en/filament/user-resource.php'), ['navigation_label' => 'Users']);

    expect(app(MessageInliner::class)->inlineSurface(new Surface(
        domain: 'filament.user-resource',
        chrome: [new ChromeMessage(Chrome::ModelLabel, ScanHost::class, 'filament.user-resource')],
    ), 'en'))->toBe([]);
});

/**
 * @param  array<array-key, mixed>  $components
 * @return list<SourceChange>
 */
function inlineInto(array $components, string $file, string $domain): array
{
    return app(MessageInliner::class)->inlineSurface(new Surface(
        domain: $domain,
        components: app(SurfaceCollector::class)->flatten($components),
        files: [$file],
    ), 'en');
}
