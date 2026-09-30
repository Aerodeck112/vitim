<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    /** Agentul răspunde vizitatorilor doar în aceste stări (past_due = perioadă de grație). */
    public function isServiceable(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::PastDue], true);
    }
}
