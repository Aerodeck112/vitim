<?php

use App\Events\NotifyTeam;

/*
| Tip eveniment (sau „*” pentru toate) → listă de clase DomainEventListener.
| Emailurile către echipa firmei (cereri de la agentul AI, preluare de către un om) pleacă din cron (vitim:events).
*/
return [
    'listeners' => [
        'lead.created' => [NotifyTeam::class],
        'conversation.human_requested' => [NotifyTeam::class],
    ],
    'max_attempts' => 5,
    'batch' => 200,
];
