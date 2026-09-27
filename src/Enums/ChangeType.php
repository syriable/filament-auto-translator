<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Enums;

enum ChangeType: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';

    public function describe(bool $dryRun): string
    {
        if (! $dryRun) {
            return $this->value;
        }

        return match ($this) {
            self::Created => 'would create',
            self::Updated => 'would update',
            self::Deleted => 'would delete',
        };
    }
}
