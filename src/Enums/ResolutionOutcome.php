<?php

declare(strict_types=1);

namespace Syriable\Translation\Enums;

enum ResolutionOutcome: string
{
    case Bound = 'bound';
    case CustomSlot = 'custom_slot';
    case Missing = 'missing';
    case Unbound = 'unbound';
    case UsedFallbackLocale = 'used_fallback_locale';
    case VendorDefault = 'vendor_default';
    case NoCatalog = 'no_catalog';
}
