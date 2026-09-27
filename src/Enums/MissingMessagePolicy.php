<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Enums;

/**
 * What a missing required message does.
 *
 * KeepVendorLabel is deliberately not called "fallback": that word already
 * means the fallback *locale*, which is a different thing reported as
 * {@see ResolutionOutcome::UsedFallbackLocale}.
 */
enum MissingMessagePolicy: string
{
    case KeepVendorLabel = 'keep_vendor_label';
    case Debug = 'debug';
    case Strict = 'strict';

    public static function fromConfig(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::KeepVendorLabel) : self::KeepVendorLabel;
    }
}
