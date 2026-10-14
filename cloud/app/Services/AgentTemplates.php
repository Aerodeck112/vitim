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

    /**
     * Exemplu de „Informații despre firmă” pe domeniul agentului (doar ca model de scriere, nu se salvează nicăieri).
     * Generic, fără date ale vreunui client.
     */
    public static function example(?string $key): string
    {
        return match ($key) {
            'auto_service' => "Servicii: revizie, schimb ulei și filtre, diagnoză computerizată, frâne, distribuție.\nPrețuri: schimb ulei de la 150 lei manopera, diagnoză 100 lei.\nProgram: luni–vineri 8–18, sâmbătă 9–13.\n\nTrebuie programare?\nDa, pentru revizii; diagnoza se poate face și pe loc, în limita locurilor.",
            'clinic' => "Servicii: consultații, analize, ecografie.\nPrețuri: consultație de specialitate 250 lei, ecografie 200 lei.\nProgram: luni–vineri 8–20, sâmbătă 9–14.\n\nLucrați cu casa de asigurări?\nDa, pentru consultațiile cu bilet de trimitere, în limita fondurilor lunare.",
            'ecommerce' => "Livrare: prin curier în 1–3 zile lucrătoare, gratuit peste 300 lei.\nRetur: în 14 zile de la primire, produsul nefolosit, în ambalajul original.\nPlată: card online, ramburs sau transfer bancar.\n\nPot schimba mărimea?\nDa, schimbul de mărime este gratuit o singură dată.",
            default => "Servicii: ce oferiți, pe scurt, câte unul pe rând.\nPrețuri: de la 200 lei / lucrare; oferta exactă după evaluare.\nProgram: luni–vineri 9–17.\nZonă: lucrăm în orașul X și în împrejurimi.\n\nCât durează o lucrare?\nDe obicei 2–5 zile lucrătoare de la confirmarea comenzii.",
        };
    }

    /** @return array<string, mixed> */
    public static function system(string $key): array
    {
        return self::all()[$key]['system'] ?? [];
    }
}
