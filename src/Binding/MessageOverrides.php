<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Binding;

use Syriable\MessageCatalog\Enums\MissingMessagePolicy;

class MessageOverrides
{
    /**
     * @var array<string, string>
     */
    public array $prefixes = [];

    public ?MissingMessagePolicy $mode = null;

    public bool $hooksRegistered = false;
}
