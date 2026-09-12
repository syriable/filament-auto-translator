<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\AutoTranslator\Apply\ClassMethodEditor;

it('inserts getModelLabel when the class does not declare it', function () {
    $source = <<<'PHP'
<?php

class UserResource
{
    public static function form(): void
    {
    }
}
PHP;

    $updated = app(ClassMethodEditor::class)->ensureTranslationMethod($source, [
        'method' => 'getModelLabel',
        'key' => 'filament/user-resource.model_label',
        'static' => true,
        'return' => 'string',
    ]);

    expect($updated)->toContain(<<<'PHP'
    public static function getModelLabel(): string
    {
        return __('filament/user-resource.model_label');
    }
PHP);
});

it('does not replace a custom getModelLabel body', function () {
    $source = <<<'PHP'
<?php

class UserResource
{
    public static function getModelLabel(): string
    {
        return 'User';
    }
}
PHP;

    $updated = app(ClassMethodEditor::class)->ensureTranslationMethod($source, [
        'method' => 'getModelLabel',
        'key' => 'filament/user-resource.model_label',
        'static' => true,
        'return' => 'string',
    ]);

    expect($updated)->toBe($source);
});

it('rewrites a raw catalog key return to __()', function () {
    $source = <<<'PHP'
<?php

class UserResource
{
    public static function getModelLabel(): string
    {
        return 'filament/user-resource.model_label';
    }
}
PHP;

    $updated = app(ClassMethodEditor::class)->ensureTranslationMethod($source, [
        'method' => 'getModelLabel',
        'key' => 'filament/user-resource.model_label',
        'static' => true,
        'return' => 'string',
    ]);

    expect($updated)->toContain("return __('filament/user-resource.model_label');")
        ->and($updated)->not->toContain("return 'filament/user-resource.model_label';");
});
