<?php

declare(strict_types=1);

namespace App\Enums;

/** Statusurile interne VITIM. Statusurile furnizorilor se mapează aici în adaptoare. */
enum MessageStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';
    case Bounced = 'bounced';
    case Cancelled = 'cancelled';
    /** Mesaj primit de la contact (inbound). */
    case Received = 'received';
}
