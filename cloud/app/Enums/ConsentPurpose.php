<?php

declare(strict_types=1);

namespace App\Enums;

enum ConsentPurpose: string
{
    case Marketing = 'marketing';
    case Transactional = 'transactional';
    case Service = 'service';
}
