<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Enums;

/**
 * The top-level section of a language file a message lives in.
 */
enum MessageScope: string
{
    case Form = 'form';
    case Infolist = 'infolist';
    case Table = 'table';
    case Actions = 'actions';
    case Pages = 'pages';
    case Navigation = 'navigation';
    case Model = 'model';
    case Cluster = 'cluster';

    /**
     * Schema scopes nest their components under a `components` segment.
     */
    public function nestsComponents(): bool
    {
        return $this === self::Form || $this === self::Infolist;
    }
}
