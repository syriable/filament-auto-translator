<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\AutoTranslator\Enums\Chrome;
use Syriable\Filament\Plugins\AutoTranslator\Inlining\ClassMethodEditor;

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

    $updated = app(ClassMethodEditor::class)->ensure($source, Chrome::ModelLabel, 'filament/user-resource.model_label');

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

    $updated = app(ClassMethodEditor::class)->ensure($source, Chrome::ModelLabel, 'filament/user-resource.model_label');

    expect($updated)->toBe($source);
});

it('rewrites a bare key return to __()', function () {
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

    $updated = app(ClassMethodEditor::class)->ensure($source, Chrome::ModelLabel, 'filament/user-resource.model_label');

    expect($updated)->toContain("return __('filament/user-resource.model_label');")
        ->and($updated)->not->toContain("return 'filament/user-resource.model_label';");
});

it('writes an instance method for page chrome', function () {
    $source = <<<'PHP'
<?php

class EditUser
{
}
PHP;

    expect(app(ClassMethodEditor::class)->ensure($source, Chrome::PageSubheading, 'filament/user-resource.pages.edit-user.subheading'))
        ->toContain('public function getSubheading(): ?string')
        ->not->toContain('static function getSubheading');
});

it('only looks at the class own methods, not closures inside them', function () {
    $source = <<<'PHP'
<?php

class UserResource
{
    public static function form(): void
    {
        $label = function () { return 'x'; };
    }
}
PHP;

    expect(app(ClassMethodEditor::class)->ensure($source, Chrome::ModelLabel, 'filament/user-resource.model_label'))
        ->toContain('public static function getModelLabel(): string');
});
