<?php

declare(strict_types=1);

namespace App\Enums;

/** Identificatorii prin care recunoaștem același om pe canale diferite. */
enum IdentityType: string
{
    case Email = 'email';
    case Phone = 'phone';
    case WhatsApp = 'whatsapp';
    case ExternalId = 'external_id';
}
