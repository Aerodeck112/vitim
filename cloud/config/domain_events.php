<?php

/*
| Tip eveniment (sau „*” pentru toate) → listă de clase DomainEventListener.
| Phase 1: niciun consumator; evenimentele se marchează procesate și rămân ca istoric.
*/
return [
    'listeners' => [
        // 'lead.created' => [App\Automation\StartWorkflows::class],
    ],
    'max_attempts' => 5,
    'batch' => 200,
];
