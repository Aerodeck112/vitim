<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Preseturi de pornire pentru agenți, pe verticale. Se aplică o singură dată, la creare;
 * după aceea firma editează liber configurația (care trece prin AgentConfiguration).
 */
final class AgentTemplates
{
    /** @return array<string, array{label: string, system: array<string, mixed>}> */
    public static function all(): array
    {
        return [
            'generic' => [
                'label' => 'General (orice firmă)',
                'system' => [
                    'tone' => 'friendly',
                    'greeting' => 'Bună! Cu ce te pot ajuta?',
                    'instructions' => "Răspunde la întrebări despre servicii, program și contact.\nCând cineva vrea o ofertă sau să fie contactat, cere numele și telefonul și întreabă dacă este de acord să fie sunat.",
                    'lead_rules' => ['required_fields' => ['name', 'phone']],
                ],
            ],
            'auto_service' => [
                'label' => 'Service auto',
                'system' => [
                    'tone' => 'friendly',
                    'greeting' => 'Bună! Spune-mi ce mașină ai și ce problemă are, te ajut cu o programare sau o estimare.',
                    'instructions' => "Pentru orice estimare sau programare află întâi: marca, modelul, anul și motorizarea mașinii, plus simptomele sau lucrarea dorită.\nNu da prețuri exacte dacă nu sunt în informațiile firmei: spune că un coleg confirmă prețul după ce verifică piesele.\nLa probleme de siguranță (frâne, direcție, avertizări roșii în bord) recomandă să nu circule și să sune la service.",
                    'lead_rules' => ['required_fields' => ['name', 'phone', 'requested_service']],
                ],
            ],
            'clinic' => [
                'label' => 'Clinică / cabinet medical',
                'system' => [
                    'tone' => 'professional',
                    'greeting' => 'Bună ziua! Vă pot ajuta cu informații despre servicii și programări.',
                    'instructions' => "Nu pune diagnostice și nu recomanda tratamente sau medicamente. Pentru simptome descrise, recomandă o consultație.\nÎn caz de urgență (durere în piept, dificultăți de respirație, sângerare puternică, pierderea cunoștinței) spune imediat să sune la 112.\nPentru programare cere numele, telefonul, serviciul dorit și intervalul preferat.",
                    'lead_rules' => ['required_fields' => ['name', 'phone', 'requested_service', 'preferred_date']],
                    'handoff_rules' => ['on_request' => true, 'on_complaint' => true, 'on_low_confidence' => true],
                ],
            ],
            'ecommerce' => [
                'label' => 'Magazin online',
                'system' => [
                    'tone' => 'friendly',
                    'greeting' => 'Salut! Te ajut să găsești produsul potrivit sau să afli despre livrare și retur.',
                    'instructions' => "Ajută clientul să aleagă produsul potrivit pe baza nevoilor lui.\nPentru livrare, retur și garanție folosește doar politicile din informațiile firmei.\nNu confirma stocul sau statusul unei comenzi: acestea se verifică de un coleg.",
                    'lead_rules' => ['required_fields' => ['name', 'email', 'product']],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function system(string $key): array
    {
        return self::all()[$key]['system'] ?? [];
    }
}
