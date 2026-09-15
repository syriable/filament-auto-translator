<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Binding;

use Syriable\MessageCatalog\Enums\MessageSlot;
use Syriable\MessageCatalog\Resolution;

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
