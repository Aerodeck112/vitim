<?php

/*
| Planuri și limite. Valorile sunt provizorii: prețurile și limitele finale se stabilesc după pilot,
| pe baza costului măsurat per conversație (docs/VITIM-AI-ARCHITECTURE.md §15, §21).
| ai_cost_cap_usd = plafon lunar de cost AI; la depășire widgetul afișează doar datele de contact.
*/

return [
    'trial_days' => 14,

    'plans' => [
        'start' => [
            'conversations_per_month' => 300,
            'ai_cost_cap_usd' => 40,
            'knowledge_pages' => 100,
            'sites' => 1,
            'agents' => 1,
            'users' => 2,
            'analytics_retention_days' => 90,
        ],
        'pro' => [
            'conversations_per_month' => 1000,
            'ai_cost_cap_usd' => 120,
            'knowledge_pages' => 500,
            'sites' => 2,
            'agents' => 2,
            'users' => 5,
            'analytics_retention_days' => 365,
        ],
        'business' => [
            'conversations_per_month' => 3000,
            'ai_cost_cap_usd' => 350,
            'knowledge_pages' => 2000,
            'sites' => 5,
            'agents' => 5,
            'users' => 15,
            'analytics_retention_days' => 730,
        ],
        'enterprise' => [
            'conversations_per_month' => null, // negociat
            'ai_cost_cap_usd' => null,
            'knowledge_pages' => null,
            'sites' => null,
            'agents' => null,
            'users' => null,
            'analytics_retention_days' => 730,
        ],
    ],
];
