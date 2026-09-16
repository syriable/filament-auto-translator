<?php

declare(strict_types=1);

namespace Syriable\Translation\Tests\Fixtures;

use Syriable\Translation\Attributes\TranslationDomain;
use Syriable\Translation\Concerns\HasPageTranslations;

/**
 * A page that keeps its own domain instead of sharing its resource's.
 */
#[TranslationDomain('identity::people-edit')]
class DeclaredDomainPage extends CatalogPageParent
{
    use HasPageTranslations;
}
