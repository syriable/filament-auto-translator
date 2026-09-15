<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Syriable\Filament\Plugins\AutoTranslator\Apply\PhrasePhpApplier;
use Syriable\Filament\Plugins\AutoTranslator\Discovery\SchemaCatalogRegistry;
use Syriable\Filament\Plugins\AutoTranslator\Sync\PhraseLangWriter;

beforeEach(function () {
    $this->langPath = sys_get_temp_dir().'/auto-translator-apply-lang-'.uniqid('', true);
    File::ensureDirectoryExists($this->langPath);
    app()->useLangPath($this->langPath);

    // The applier only edits PHP under base_path() or the temp directory, so the
    // catalog has to live somewhere it is allowed to write.
    $this->schemaPath = sys_get_temp_dir().'/auto-translator-schemas-'.uniqid('', true);
    $this->namespace = 'AutoTranslatorTempSchemas'.str_replace('.', '', uniqid('', true));
    File::ensureDirectoryExists($this->schemaPath);

    $this->classFile = $this->schemaPath.'/SignUpForm.php';
    File::put($this->classFile, <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespace};

use Filament\\Forms\\Components\\TextInput;
use Filament\\Schemas\\Schema;
use Syriable\\Filament\\Plugins\\AutoTranslator\\Contracts\\PhraseCatalog;

class SignUpForm implements PhraseCatalog
{
    public static function phraseCatalogId(): string
    {
        return 'identity.sign-up';
    }

    public static function configure(Schema \$schema): Schema
    {
        return \$schema->components([
            TextInput::make('email'),
        ]);
    }
}
PHP);

    require_once $this->classFile;

    app(SchemaCatalogRegistry::class)->discover(in: $this->schemaPath, for: $this->namespace);

    app(PhraseLangWriter::class)->persist(lang_path('en/identity/sign-up.php'), [
        'form' => [
            'components' => [
                'email' => ['label' => 'Email address'],
            ],
        ],
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->langPath);
    File::deleteDirectory($this->schemaPath);
});

it('writes a setter onto a discovered schema catalog', function () {
    $writes = app(PhrasePhpApplier::class)->apply('en');

    expect(array_column($writes, 'key'))
        ->toContain('identity/sign-up.form.components.email.label')
        ->and(File::get($this->classFile))
        ->toContain("->label(__('identity/sign-up.form.components.email.label'))");
});

it('changes no PHP on a dry run', function () {
    $before = File::get($this->classFile);

    $writes = app(PhrasePhpApplier::class)->apply('en', dryRun: true);

    expect(array_column($writes, 'action'))->toContain('would_create')
        ->and(File::get($this->classFile))->toBe($before);
});
