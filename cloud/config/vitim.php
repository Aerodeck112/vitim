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
];
