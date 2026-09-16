<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Tests\Fixtures;

use Syriable\MessageCatalog\Attributes\TranslationDomain;
use Syriable\MessageCatalog\Concerns\HasModelMessages;

/**
 * A resource that names its own domain instead of taking one from the prefix map.
 */
#[TranslationDomain('identity::people')]
class DeclaredDomainOwner extends CatalogChromeParent
{
    use HasModelMessages;
}
