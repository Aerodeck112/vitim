<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\DomainEvent;

/**
 * Consumatorii evenimentelor de domeniu (viitorul Automation Engine, webhook-uri, statistici).
 * Rulează în contextul organizației evenimentului. Se înregistrează în config/domain_events.php.
 * Trebuie să fie idempotenți: un eveniment poate fi reîncercat după o eroare.
 */
interface DomainEventListener
{
    public function handle(DomainEvent $event): void;
}
