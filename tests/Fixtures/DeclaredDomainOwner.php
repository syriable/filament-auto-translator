<?php

declare(strict_types=1);

namespace Syriable\Translation\Tests\Fixtures;

use Syriable\Translation\Attributes\TranslationDomain;
use Syriable\Translation\Concerns\HasModelTranslations;

/**
 * A resource that names its own domain instead of taking one from the prefix map.
 */
#[TranslationDomain('identity::people')]
class DeclaredDomainOwner extends CatalogChromeParent
{
    use HasModelTranslations;
}
