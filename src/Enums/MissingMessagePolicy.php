<?php

declare(strict_types=1);

namespace Syriable\Translation\Enums;

/**
 * What a missing message does.
 *
 * KeepVendorLabel is deliberately not called "fallback": this package already
 * uses that word for the fallback *locale*, which is a different thing that
 * happens for a different reason.
 */
enum MissingMessagePolicy: string
{
    case Debug = 'debug';
    case Strict = 'strict';
    case KeepVendorLabel = 'keep_vendor_label';

    public static function fromConfig(string $value): self
    {
        return self::tryFrom($value) ?? self::KeepVendorLabel;
    }
}
