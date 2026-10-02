<?php

declare(strict_types=1);

namespace App\Http\Validation;

/** Mesajele de validare în română, pentru formularele din panou (aplicația nu are fișiere de limbă publicate). */
final class Ro
{
    public const MESSAGES = [
        'required' => 'Completează câmpul „:attribute”.',
        'string' => 'Câmpul „:attribute” nu e valid.',
        'max.string' => '„:attribute” poate avea cel mult :max caractere (acum are mai multe).',
        'max.numeric' => '„:attribute” poate fi cel mult :max.',
        'max.array' => '„:attribute” poate avea cel mult :max elemente.',
        'min.numeric' => '„:attribute” trebuie să fie cel puțin :min.',
        'integer' => '„:attribute” trebuie să fie un număr întreg.',
        'url' => '„:attribute” trebuie să fie un link complet (de forma https://…).',
        'in' => 'Valoarea aleasă la „:attribute” nu e permisă.',
        'enum' => 'Valoarea aleasă la „:attribute” nu e permisă.',
        'date_format' => '„:attribute” trebuie să fie o oră, de forma 09:00.',
        'size' => '„:attribute” trebuie să aibă exact :size caractere.',
        'size.string' => '„:attribute” trebuie să aibă exact :size caractere.',
        'alpha' => '„:attribute” poate conține doar litere.',
        'array' => 'Câmpul „:attribute” nu e valid.',
        'boolean' => 'Câmpul „:attribute” nu e valid.',
        'timezone' => 'Fusul orar nu e valid.',
        'regex' => 'Câmpul „:attribute” nu are formatul cerut.',
        'prohibited' => 'Câmpul „:attribute” nu se poate schimba aici.',
    ];

    public const ATTRIBUTES = [
        'name' => 'Nume', 'status' => 'Status', 'site_id' => 'Site', 'default_language' => 'Limba implicită', 'engine' => 'Cum răspunde agentul',
        'business_facts' => 'Informații despre firmă', 'contact_line' => 'Date de contact', 'tone' => 'Ton', 'languages' => 'Limbi',
        'languages.*' => 'Limbi (coduri de 2 litere, ex. ro, en)', 'greeting' => 'Mesaj de întâmpinare', 'instructions' => 'Instrucțiuni suplimentare',
        'fallback_behavior' => 'Când nu știe răspunsul', 'allowed_actions.*' => 'Acțiuni permise', 'required_fields.*' => 'Date cerute pentru lead',
        'lead_rules.required_fields.*' => 'Date cerute pentru lead',
        'color' => 'Culoare', 'position' => 'Poziție', 'title' => 'Titlul ferestrei', 'launcher' => 'Textul butonului', 'privacy_url' => 'Link confidențialitate',
        'avatar_url' => 'Poză pentru chat', 'welcome_title' => 'Salutul', 'welcome_text' => 'Textul de sub salut', 'quick_replies' => 'Întrebări rapide',
        'proactive_delay' => 'Mesaj automat după (secunde)', 'proactive_text' => 'Mesajul automat', 'hours_start' => 'Online de la', 'hours_end' => 'Online până la',
    ];
}
