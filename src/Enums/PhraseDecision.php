<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Enums;

enum PhraseDecision: string
{
    case Bound = 'bound';
    case CustomSlot = 'custom_slot';
    case Missing = 'missing';
    case Unbound = 'unbound';
    case UsedFallback = 'used_fallback';
    case VendorDefault = 'vendor_default';
    case NoCatalog = 'no_catalog';
}
