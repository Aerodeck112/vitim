<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\Channel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\ContactSource;
use App\Enums\IdentityType;
use App\Models\Suppression;
use App\Services\AuditLogger;
use App\Services\ConsentService;
use App\Services\ContactService;
use App\Services\DuplicateContactException;
use App\Services\IdentityNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Import de contacte din CSV (Excel → Salvează ca CSV). Acordul de marketing se înregistrează doar dacă firma
 * declară sursa lui (formular, contract, registru); declarația și sursa rămân în istoricul fiecărui contact.
 * Adresele dezabonate anterior NU primesc acord nou din import.
 */
final class ContactImportController extends PortalController
{
    private const MAX_ROWS = 5000;

    public function show(): View
    {
        return view('portal.contacts.import', ['organization' => $this->organization()]);
    }

    public function store(Request $request, ContactService $contacts, ConsentService $consents, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:4096', 'mimes:csv,txt'],
            'consent' => ['nullable', 'array'], 'consent.*' => ['in:email,sms,whatsapp'],
            'evidence' => ['required_with:consent', 'nullable', 'string', 'max:300'],
            'declare' => ['required_with:consent', 'nullable', 'accepted'],
        ], [
            'evidence.required_with' => 'Scrie de unde ai acordul contactelor (ex. „formular de abonare pe site, 2024–2026”).',
            'declare.required_with' => 'Confirmă că ai acordul documentat al acestor persoane.',
            'file.mimes' => 'Fișierul trebuie să fie CSV (în Excel: Fișier → Salvare ca → CSV).',
        ]);
        $channels = array_map(fn ($c) => Channel::from($c), (array) ($data['consent'] ?? []));
        $rows = $this->rows((string) file_get_contents($request->file('file')->getRealPath()));
        if ($rows === null) {
            return back()->withErrors(['file' => 'Nu am găsit coloanele. Prima linie trebuie să aibă cel puțin „email” sau „telefon” (opțional „prenume”, „nume”, „firma”).']);
        }
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'consents' => 0, 'suppressed' => 0];
        foreach (array_slice($rows, 0, self::MAX_ROWS) as $row) {
            $email = IdentityNormalizer::email($row['email'] ?? '');
            $phone = IdentityNormalizer::phone($row['phone'] ?? '');
            if (! $email && ! $phone) {
                $stats['skipped']++;

                continue;
            }
            $contact = ($email ? $contacts->findByIdentity(IdentityType::Email, $email) : null) ?? ($phone ? $contacts->findByIdentity(IdentityType::Phone, $phone) : null);
            $fields = array_filter(['first_name' => $row['first_name'] ?? null, 'last_name' => $row['last_name'] ?? null, 'company' => $row['company'] ?? null,
                'email' => $email, 'phone' => $phone]);
            try {
                if ($contact) {
                    // completează doar ce lipsește; nu suprascrie datele existente
                    $contacts->update($contact, array_filter($fields, fn ($v, $k) => blank($contact->{$k}), ARRAY_FILTER_USE_BOTH));
                    $stats['updated']++;
                } else {
                    $contact = $contacts->create($fields, ContactSource::Import);
                    $stats['created']++;
                }
            } catch (DuplicateContactException) {
                $stats['skipped']++;

                continue;
            }
            foreach ($channels as $channel) {
                $address = $channel === Channel::Email ? $email : $phone;
                if (! $address) {
                    continue;
                }
                if (Suppression::query()->where('channel', $channel->value)->where('value_hash', Suppression::hash($address))->exists()) {
                    $stats['suppressed']++; // s-a dezabonat sau a respins mesajele: importul nu reface acordul

                    continue;
                }
                $consents->record($contact, $channel, ConsentPurpose::Marketing, ConsentStatus::Granted, 'import',
                    ['evidence' => mb_substr((string) $data['evidence'], 0, 300)], $request->ip(), $request->userAgent(), $request->user());
                $stats['consents']++;
            }
        }
        $audit->record('contacts.imported', null, $stats + ['channels' => implode(',', $data['consent'] ?? [])]);

        return $this->to('portal.contacts.index', [], "Import terminat: {$stats['created']} contacte noi, {$stats['updated']} existente completate, {$stats['skipped']} rânduri fără email / telefon valid"
            .($channels ? ", {$stats['consents']} acorduri de marketing înregistrate".($stats['suppressed'] ? ", {$stats['suppressed']} adrese dezabonate anterior (fără acord nou)" : '') : '')
            .(count($rows) > self::MAX_ROWS ? '. Am importat primele '.self::MAX_ROWS.' rânduri; restul, într-un fișier separat.' : '.'));
    }

    /** @return list<array<string, string>>|null rândurile cu chei standard, sau null dacă antetul nu are email / telefon */
    private function rows(string $csv): ?array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv); // BOM din Excel
        $lines = preg_split('/\R/', trim((string) $csv)) ?: [];
        if (count($lines) < 2) {
            return null;
        }
        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $aliases = [
            'email' => ['email', 'e-mail', 'mail', 'adresa email', 'adresa de email'],
            'phone' => ['telefon', 'phone', 'mobil', 'tel', 'numar telefon', 'număr de telefon', 'nr telefon'],
            'first_name' => ['prenume', 'first name', 'first_name', 'nume complet', 'name', 'nume si prenume', 'nume și prenume'],
            'last_name' => ['nume', 'last name', 'last_name', 'nume de familie'],
            'company' => ['firma', 'firmă', 'companie', 'company'],
        ];
        $map = [];
        foreach (str_getcsv($lines[0], $delimiter) as $i => $header) {
            $h = mb_strtolower(trim($header));
            foreach ($aliases as $key => $names) {
                if (in_array($h, $names, true) && ! in_array($key, $map, true)) {
                    $map[$i] = $key;
                    break;
                }
            }
        }
        if (! in_array('email', $map, true) && ! in_array('phone', $map, true)) {
            return null;
        }
        $rows = [];
        foreach (array_slice($lines, 1) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line, $delimiter);
            $row = [];
            foreach ($map as $i => $key) {
                $row[$key] = mb_substr(trim((string) ($cells[$i] ?? '')), 0, 190);
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
