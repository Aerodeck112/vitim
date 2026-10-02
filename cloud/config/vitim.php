<?php

return [
    /*
    | Crearea primului administrator VITIM (/setup). Funcționează doar cât timp nu există niciun utilizator
    | și doar cu acest token setat în .env. După instalare, ștergeți SETUP_TOKEN din .env.
    */
    'setup_token' => env('SETUP_TOKEN'),

    /* Unde pleacă backup-ul zilnic al bazei de date (adresă din afara hostingului). */
    'backup_email' => env('BACKUP_EMAIL'),

    /* Modelele AI permise în configurația agenților (primul = implicit). */
    'ai_models' => array_values(array_filter(explode(',', (string) env('VITIM_AI_MODELS', 'claude-opus-5-5')))),
    'backup_keep_local' => (int) env('BACKUP_KEEP_LOCAL', 7),

    // versiunea Graph API pentru WhatsApp Cloud API (Meta retrage versiunile vechi după ~2 ani)
    'whatsapp_graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v22.0'),

    /*
    | Agentul AI. Cheia stă doar în .env (niciodată în baza de date sau pe agent).
    | ANTHROPIC_BASE_URL e doar pentru teste locale (server care imită API-ul).
    */
    'ai' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL'),
        'timeout' => (float) env('ANTHROPIC_TIMEOUT', 45),
        'max_tool_rounds' => 4,
        'max_message_chars' => 2000,
        // USD per milion de tokeni. Folosite pentru plafonul de cost; estimare conservatoare (rotunjită în sus).
        'prices' => [
            'claude-opus-5-5' => ['input' => 4.0, 'output' => 20.0, 'cache_write' => 5.0, 'cache_read' => 0.4],
        ],
        'default_price' => ['input' => 5.0, 'output' => 25.0, 'cache_write' => 6.25, 'cache_read' => 0.5],
    ],
];
