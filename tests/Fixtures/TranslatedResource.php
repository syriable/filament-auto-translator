<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Tests\Fixtures;

use Syriable\FilamentAutoTranslator\Concerns\HasResourceTranslations;

class TranslatedResource extends ResourceChromeParent
{
    use HasResourceTranslations;
}
