<?php
declare(strict_types=1);

/**
 * Categoriile de servicii (ordinea contează: meniu, pagina Servicii, filtre).
 * `secondary` = afișată discret, după serviciile B2B (service tehnic: reparații, telefoane, plăci de bază).
 */
return [
    'it' => [
        'name' => 'IT & Infrastructură',
        'short' => 'IT & Infrastructură',
        'icon' => 'server',
        'intro' => 'Calculatoare, servere, rețele și conturi administrate pentru companii – remote sau la sediul tău.',
    ],
    'securitate' => [
        'name' => 'Securitate & Date',
        'short' => 'Securitate & Date',
        'icon' => 'shield',
        'intro' => 'Protecție împotriva atacurilor, backup verificat și recuperarea datelor pierdute.',
    ],
    'ai' => [
        'name' => 'VITIM AI & Automatizări',
        'short' => 'AI & Automatizări',
        'icon' => 'sparkles',
        'intro' => 'Platforma VITIM AI, agenți AI, integrări și automatizări care preiau munca repetitivă din companie.',
    ],
    'marketing' => [
        'name' => 'Web & Creștere digitală',
        'short' => 'Web & Creștere',
        'icon' => 'trending',
        'intro' => 'Website-uri, magazine online, SEO și campanii măsurate după lead-uri și vânzări, nu după trafic.',
    ],
    'service' => [
        'name' => 'Service & intervenții',
        'short' => 'Service & intervenții',
        'icon' => 'wrench',
        'intro' => 'Reparații de calculatoare, laptopuri, telefoane și tablete, inclusiv la nivel de componentă pe placa de bază.',
        'secondary' => true,
    ],
];
