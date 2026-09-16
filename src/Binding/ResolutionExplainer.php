<?php

declare(strict_types=1);

namespace Syriable\Translation\Binding;

use Syriable\Translation\Enums\MessageSlot;
use Syriable\Translation\Resolution;

class ResolutionExplainer
{
    public function __construct(
        private MessageBinder $binder,
    ) {}

    public static function explain(object $component, MessageSlot $slot = MessageSlot::Label): Resolution
    {
        return app(self::class)->inspect($component, $slot);
    }

    public function inspect(object $component, MessageSlot $slot = MessageSlot::Label): Resolution
    {
        return $this->binder->explain($component, $slot);
    }
}
