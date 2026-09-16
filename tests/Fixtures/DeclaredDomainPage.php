<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Tests\Fixtures;

use Syriable\MessageCatalog\Attributes\TranslationDomain;
use Syriable\MessageCatalog\Concerns\HasPageMessages;

/**
 * A page that keeps its own domain instead of sharing its resource's.
 */
#[TranslationDomain('identity::people-edit')]
class DeclaredDomainPage extends CatalogPageParent
{
    use HasPageMessages;
}
