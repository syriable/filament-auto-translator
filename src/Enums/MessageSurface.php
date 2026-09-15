<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Enums;

enum MessageSurface: string
{
    case Form = 'form';
    case Infolist = 'infolist';
    case Table = 'table';
    case Actions = 'actions';
    case Pages = 'pages';
    case Navigation = 'navigation';
    case Model = 'model';
}
