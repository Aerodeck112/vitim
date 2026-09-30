<?php

declare(strict_types=1);

namespace App\Enums;

enum SenderType: string
{
    case Contact = 'contact';
    case Ai = 'ai';
    case Human = 'human';
    case System = 'system';
}
