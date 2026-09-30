<?php

use App\Messaging\Providers\LogProvider;

/*
| Furnizorul activ pentru fiecare canal. Gol = canal inactiv (mesajele se marchează „failed”, nu se pierd tăcut).
| Furnizorii reali (Brevo, Twilio, Meta WhatsApp...) se adaugă ca adaptoare în app/Messaging/Providers.
*/
return [
    'channels' => [
        'email' => env('MESSAGING_EMAIL_PROVIDER'),
        'sms' => env('MESSAGING_SMS_PROVIDER'),
        'whatsapp' => env('MESSAGING_WHATSAPP_PROVIDER'),
    ],
    'providers' => [
        'log' => LogProvider::class,
    ],
];
