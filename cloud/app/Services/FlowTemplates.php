<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Fluxuri gata făcute (ca bibliotecă de „Flows” din Klaviyo). Textele sunt exemple de pornire în română,
 * pe care firma le adaptează. Pași: [tip, config] sau ['condition', definiție, ['yes' => pași, 'no' => pași]].
 */
final class FlowTemplates
{
    /** @return array<string, array{name: string, description: string, trigger: array<string, mixed>, settings: array<string, mixed>, steps: list<array<int, mixed>>, shop?: bool}> */
    public static function all(): array
    {
        return [
            'welcome' => [
                'name' => 'Bun venit (3 emailuri)',
                'description' => 'Când cineva se abonează (formular sau listă): prezentarea firmei, de ce să vă aleagă, o ofertă.',
                'trigger' => ['type' => 'event', 'event' => 'subscribed'],
                'settings' => ['smart_sending_hours' => 0],
                'steps' => [
                    ['email', ['subject' => 'Bun venit, {{prenume}}!', 'body' => "Bună {{prenume}},\n\nÎți mulțumim că te-ai abonat la noutățile {{firma}}.\n\nÎți vom scrie doar când avem ceva util: oferte, sfaturi și noutăți.\n\nO zi frumoasă!"]],
                    ['wait', ['amount' => 2, 'unit' => 'days']],
                    ['email', ['subject' => 'De ce ne aleg clienții', 'body' => "Bună {{prenume}},\n\nCâteva lucruri despre noi:\n\n- **experiență**: …\n- **garanție**: …\n- **suport rapid**: …\n\nAi o întrebare? Răspunde la acest email."]],
                    ['wait', ['amount' => 3, 'unit' => 'days']],
                    ['email', ['subject' => '{{prenume}}, un mic cadou pentru tine', 'body' => "Bună {{prenume}},\n\nPentru prima comandă ai **10% reducere** cu codul BUNVENIT10.\n\nTe așteptăm!"]],
                ],
            ],
            'lead_followup' => [
                'name' => 'Follow-up după cerere',
                'description' => 'După o cerere de ofertă (din chat, formular): reamintire după o zi și un SMS după 3 zile dacă nu a comandat.',
                'trigger' => ['type' => 'event', 'event' => 'lead_created'],
                'settings' => ['exit_on' => ['placed_order'], 'smart_sending_hours' => 0],
                'steps' => [
                    ['wait', ['amount' => 1, 'unit' => 'days']],
                    ['email', ['subject' => 'Ai primit oferta, {{prenume}}?', 'body' => "Bună {{prenume}},\n\nVoiam să ne asigurăm că ai primit răspunsul nostru la cererea ta.\n\nDacă mai ai întrebări, răspunde la acest email sau sună-ne. Ne bucurăm să te ajutăm."]],
                    ['wait', ['amount' => 3, 'unit' => 'days']],
                    ['sms', ['body' => 'Buna {{prenume}}! Revenim la cererea ta catre {{firma}}. Te putem ajuta cu ceva? Raspunde-ne oricand.']],
                ],
            ],
            'abandoned_cart' => [
                'name' => 'Coș abandonat',
                'description' => 'Magazin: cine a început comanda și nu a terminat-o primește o reamintire după o oră, apoi a doua zi. Iese automat dacă a comandat.',
                'trigger' => ['type' => 'event', 'event' => 'started_checkout'],
                'settings' => ['exit_on' => ['placed_order'], 'reentry_days' => 7],
                'shop' => true,
                'steps' => [
                    ['wait', ['amount' => 1, 'unit' => 'hours']],
                    ['email', ['subject' => '{{prenume}}, ai uitat ceva în coș', 'body' => "Bună {{prenume}},\n\nProdusele tale te așteaptă: {{produse}} ({{total}}).\n\nFinalizează comanda aici: {{link_cos}}"]],
                    ['wait', ['amount' => 1, 'unit' => 'days']],
                    ['condition', ['match' => 'all', 'conditions' => [['type' => 'since_start', 'event' => 'email_opened', 'op' => 'did']]], [
                        'yes' => [['email', ['subject' => 'Încă te gândești?', 'body' => "Bună {{prenume}},\n\nComanda ta e aproape gata: {{produse}}.\n\nAi întrebări despre produse sau livrare? Răspunde la acest email.\n\nFinalizează aici: {{link_cos}}"]]],
                        'no' => [['sms', ['body' => 'Buna {{prenume}}! Produsele din cos te asteapta la {{firma}}: {{link_cos}}']]],
                    ]],
                ],
            ],
            'browse_abandonment' => [
                'name' => 'Produs văzut, necumpărat',
                'description' => 'Magazin: cine a văzut un produs și nu l-a pus în coș primește după 4 ore un email cu produsul.',
                'trigger' => ['type' => 'event', 'event' => 'viewed_product'],
                'settings' => ['exit_on' => ['added_to_cart', 'placed_order'], 'reentry_days' => 14],
                'shop' => true,
                'steps' => [
                    ['wait', ['amount' => 4, 'unit' => 'hours']],
                    ['email', ['subject' => 'Ți-a plăcut {{produs}}?', 'body' => "Bună {{prenume}},\n\nAm văzut că te-a interesat **{{produs}}**.\n\nÎl găsești aici: {{link_produs}}"]],
                ],
            ],
            'post_purchase' => [
                'name' => 'După comandă: mulțumire + recenzie',
                'description' => 'Magazin: mulțumire la 3 zile după comandă și cerere de recenzie după încă 7 zile.',
                'trigger' => ['type' => 'event', 'event' => 'placed_order'],
                'settings' => ['reentry_days' => 0, 'smart_sending_hours' => 0],
                'shop' => true,
                'steps' => [
                    ['wait', ['amount' => 3, 'unit' => 'days']],
                    ['email', ['subject' => 'Mulțumim pentru comandă, {{prenume}}!', 'body' => "Bună {{prenume}},\n\nÎți mulțumim pentru comanda de {{total}}. Sperăm că ești mulțumit.\n\nDacă ai nevoie de ceva, suntem aici."]],
                    ['wait', ['amount' => 7, 'unit' => 'days']],
                    ['email', ['subject' => 'Cum ți se par produsele?', 'body' => "Bună {{prenume}},\n\nNe-ar ajuta mult o recenzie de un minut despre {{produse}}.\n\nÎți mulțumim!"]],
                ],
            ],
            'winback' => [
                'name' => 'Recâștigare clienți inactivi',
                'description' => 'Cine nu a mai interacționat de 90 de zile primește un email „ne e dor de tine”, apoi o ofertă după o săptămână.',
                'trigger' => ['type' => 'segment', 'segment' => ['name' => 'Inactivi 90 de zile', 'definition' => ['match' => 'all', 'conditions' => [
                    ['type' => 'consent', 'channel' => 'email', 'op' => 'granted'],
                    ['type' => 'property', 'field' => 'last_activity_at', 'op' => 'older_than_days', 'value' => '90'],
                ]]]],
                'settings' => ['exit_on' => ['placed_order', 'lead_created'], 'reentry_days' => 180],
                'steps' => [
                    ['email', ['subject' => 'Ne e dor de tine, {{prenume}}', 'body' => "Bună {{prenume}},\n\nNu ne-am mai auzit de ceva vreme. Iată ce e nou la {{firma}}: …\n\nNe bucurăm să te revedem."]],
                    ['wait', ['amount' => 7, 'unit' => 'days']],
                    ['condition', ['match' => 'all', 'conditions' => [['type' => 'since_start', 'event' => 'email_clicked', 'op' => 'not']]], [
                        'yes' => [['email', ['subject' => '{{prenume}}, o ofertă doar pentru tine', 'body' => "Bună {{prenume}},\n\nCa să revii, ai **15% reducere** cu codul REVINO15, valabil 7 zile."]]],
                        'no' => [],
                    ]],
                ],
            ],
            'birthday' => [
                'name' => 'La mulți ani',
                'description' => 'În ziua de naștere (câmpul „Zi de naștere” din profil), la ora 9: urare și un cadou.',
                'trigger' => ['type' => 'date', 'field' => 'birthday'],
                'settings' => ['smart_sending_hours' => 0],
                'steps' => [
                    ['email', ['subject' => 'La mulți ani, {{prenume}}! 🎉', 'body' => "La mulți ani, {{prenume}}!\n\nEchipa {{firma}} îți urează o zi minunată. Cadoul nostru: **20% reducere** azi și mâine, cu codul LAMULTIANI."]],
                ],
            ],
        ];
    }
}
