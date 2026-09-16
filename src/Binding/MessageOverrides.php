<?php

declare(strict_types=1);

namespace Syriable\Translation\Binding;

use Syriable\Translation\Enums\MissingMessagePolicy;

class MessageOverrides
{
    /**
     * @var array<string, string>
     */
    public array $prefixes = [];

    public ?MissingMessagePolicy $mode = null;

    public bool $hooksRegistered = false;
}
