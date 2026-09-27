<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Tests\Fixtures;

use Syriable\FilamentAutoTranslator\Concerns\HasPageTranslations;

class TranslatedResourcePage extends ResourcePageParent
{
    use HasPageTranslations;
}
