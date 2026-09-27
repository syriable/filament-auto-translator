<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Enums;

enum ResolutionOutcome: string
{
    /** The current locale has the message. */
    case Bound = 'bound';

    /** Only the fallback locale has the message; its copy is shown. */
    case UsedFallbackLocale = 'used_fallback_locale';

    /** Neither locale has the message. */
    case Missing = 'missing';

    /** The component's owner declares no translation domain. */
    case NoDomain = 'no_domain';

    /** The component has no machine name, so it has no key. */
    case Unbound = 'unbound';

    public function isPresent(): bool
    {
        return $this === self::Bound || $this === self::UsedFallbackLocale;
    }

    public function hasKey(): bool
    {
        return $this !== self::NoDomain && $this !== self::Unbound;
    }
}
