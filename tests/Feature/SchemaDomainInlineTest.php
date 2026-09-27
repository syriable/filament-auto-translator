<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Syriable\FilamentAutoTranslator\Domains\SchemaDomainRegistry;
use Syriable\FilamentAutoTranslator\Extraction\LanguageFiles;
use Syriable\FilamentAutoTranslator\Inlining\MessageInliner;

beforeEach(function () {
    $this->langPath = sys_get_temp_dir().'/messages-apply-lang-'.uniqid('', true);
    File::ensureDirectoryExists($this->langPath);
    app()->useLangPath($this->langPath);

    // The applier only edits PHP under base_path() or the temp directory, so the
    // schema domain has to live somewhere it is allowed to write.
    $this->schemaPath = sys_get_temp_dir().'/messages-schemas-'.uniqid('', true);
    $this->namespace = 'TranslationTempSchemas'.str_replace('.', '', uniqid('', true));
    File::ensureDirectoryExists($this->schemaPath);

    $this->classFile = $this->schemaPath.'/SignUpForm.php';
    File::put($this->classFile, <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespace};

use Filament\\Forms\\Components\\TextInput;
use Filament\\Schemas\\Schema;
use Syriable\\FilamentAutoTranslator\\Attributes\\TranslationDomain;

#[TranslationDomain('identity.sign-up')]
class SignUpForm
{
    public static function configure(Schema \$schema): Schema
    {
        return \$schema->components([
            TextInput::make('email'),
        ]);
    }
}
PHP);

    require_once $this->classFile;

    app(SchemaDomainRegistry::class)->register($this->schemaPath, $this->namespace);

    app(LanguageFiles::class)->write(lang_path('en/identity/sign-up.php'), [
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

it('writes a setter onto a discovered schema domain', function () {
    $writes = app(MessageInliner::class)->inline('en');

    expect(array_column($writes, 'key'))
        ->toContain('identity/sign-up.form.components.email.label')
        ->and(File::get($this->classFile))
        ->toContain("->label(__('identity/sign-up.form.components.email.label'))");
});

it('changes no PHP on a dry run', function () {
    $before = File::get($this->classFile);

    $writes = app(MessageInliner::class)->inline('en', dryRun: true);

    expect($writes)->not->toBeEmpty()
        ->and($writes[0]->dryRun)->toBeTrue()
        ->and(File::get($this->classFile))->toBe($before);
});
