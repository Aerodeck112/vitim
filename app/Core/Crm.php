<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Logica CRM: contacte, oportunități, activități, abonări.
 */
final class Crm
{
    public const STATUSES = [
        'lead' => 'Lead',
        'prospect' => 'Prospect',
        'client' => 'Client',
        'fost_client' => 'Fost client',
        'partener' => 'Partener',
        'furnizor' => 'Furnizor',
    ];

    public const NEWSLETTER = [
        'none' => 'Neabonat',
        'pending' => 'În așteptarea confirmării',
        'subscribed' => 'Abonat',
        'unsubscribed' => 'Dezabonat',
    ];

    public const ACTIVITY_TYPES = [
        'note' => ['Notă', 'edit'],
        'call' => ['Apel', 'phone'],
        'email' => ['Email', 'mail'],
        'meeting' => ['Întâlnire', 'calendar'],
        'task' => ['Sarcină', 'check-circle'],
        'form' => ['Formular', 'inbox'],
        'stage' => ['Etapă', 'kanban'],
        'newsletter' => ['Newsletter', 'send'],
        'campaign' => ['Campanie', 'send'],
    ];

    public static function stages(): array
    {
        $out = [];
        foreach (Settings::json('crm_stages') as $s) {
            $out[$s['key']] = $s;
        }
        return $out;
    }

    public static function findContact(string $email, string $phone = ''): ?array
    {
        if ($email !== '') {
            $c = DB::row('SELECT * FROM contacts WHERE LOWER(email) = ? ORDER BY id LIMIT 1', [mb_strtolower($email)]);
            if ($c) {
                return $c;
            }
        }
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) >= 9) {
            $tail = substr($digits, -9);
            foreach (DB::all("SELECT * FROM contacts WHERE phone IS NOT NULL AND phone <> '' ORDER BY id") as $c) {
                if (str_ends_with(preg_replace('/\D/', '', (string)$c['phone']), $tail)) {
                    return $c;
                }
            }
        }
        return null;
    }

    public static function upsertContact(array $d, string $source): int
    {
        $existing = self::findContact((string)($d['email'] ?? ''), (string)($d['phone'] ?? ''));
        $now = DB::now();
        if ($existing) {
            $upd = ['updated_at' => $now, 'last_contact_at' => $now];
            foreach (['name', 'phone', 'company', 'county', 'email'] as $k) {
                if (!empty($d[$k]) && empty($existing[$k])) {
                    $upd[$k] = $d[$k];
                }
            }
            DB::update('contacts', $upd, 'id = :id', ['id' => $existing['id']]);
            return (int)$existing['id'];
        }
        return DB::insert('contacts', [
            'name' => $d['name'] ?? ($d['email'] ?? 'Contact'),
            'email' => $d['email'] ?? null,
            'phone' => $d['phone'] ?? null,
            'company' => $d['company'] ?? null,
            'county' => $d['county'] ?? null,
            'status' => 'lead',
            'source' => $source,
            'newsletter' => 'none',
            'unsub_token' => random_token(18),
            'notes' => '',
            'last_contact_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** Salvează o cerere din formular: contact + oportunitate + activitate + înregistrare. */
    public static function captureLead(array $d, array $utm, int $spam, bool $newsletter): array
    {
        return DB::transaction(function () use ($d, $utm, $spam, $newsletter) {
            $now = DB::now();
            $contactId = self::upsertContact($d, 'formular');
            $title = $d['service_title'] !== '' ? 'Cerere: ' . $d['service_title'] : 'Cerere de pe site';
            if ($d['company']) {
                $title .= ' – ' . $d['company'];
            }
            $msg = $d['message'];
            if ($d['budget']) {
                $msg .= "\n\nBuget estimat: " . $d['budget'];
            }
            if ($d['county']) {
                $msg .= "\nJudeț: " . $d['county'];
            }
            $dealId = DB::insert('deals', [
                'contact_id' => $contactId,
                'title' => mb_substr($title, 0, 250),
                'service' => $d['service_title'] ?: null,
                'value' => 0,
                'stage' => $spam >= 5 ? 'pierdut' : 'nou',
                'source' => self::sourceFromUtm($utm),
                'message' => $msg,
                'utm' => json_encode($utm, JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            self::logActivity($contactId, $dealId, 'form', "Formular de contact trimis de pe " . ($utm['page'] ?? 'site') . ":\n\n" . $msg);
            DB::insert('submissions', [
                'form' => 'contact',
                'contact_id' => $contactId,
                'deal_id' => $dealId,
                'data' => json_encode($d + ['utm' => $utm], JSON_UNESCAPED_UNICODE),
                'page' => mb_substr((string)($utm['page'] ?? ''), 0, 250),
                'ip' => client_ip(),
                'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
                'spam_score' => $spam,
                'created_at' => $now,
            ]);
            $token = null;
            if ($newsletter && $spam < 5) {
                $r = self::subscribe($d['email'], $d['name'], 'formular', (string)($utm['page'] ?? ''), $contactId);
                $token = $r['token'];
            }
            return ['contact_id' => $contactId, 'deal_id' => $dealId, 'confirm_token' => $token];
        });
    }

    public static function sourceFromUtm(array $utm): string
    {
        if (!empty($utm['gclid']) || (($utm['utm_source'] ?? '') === 'google' && in_array($utm['utm_medium'] ?? '', ['cpc', 'ppc', 'paid'], true))) {
            return 'google_ads';
        }
        if (!empty($utm['fbclid']) || in_array(strtolower($utm['utm_source'] ?? ''), ['facebook', 'instagram', 'meta', 'fb', 'ig'], true)) {
            return 'meta';
        }
        if (!empty($utm['utm_source'])) {
            return mb_substr((string)$utm['utm_source'], 0, 60);
        }
        $ref = (string)($utm['referrer'] ?? '');
        if ($ref !== '' && $ref !== 'direct') {
            $h = (string)parse_url($ref, PHP_URL_HOST);
            if (str_contains($h, 'google.')) {
                return 'google_organic';
            }
            if (str_contains($h, 'bing.') || str_contains($h, 'duckduckgo')) {
                return 'search_organic';
            }
            if (str_contains($h, 'facebook') || str_contains($h, 'instagram')) {
                return 'social';
            }
            if (str_contains($h, 'chatgpt') || str_contains($h, 'openai') || str_contains($h, 'perplexity') || str_contains($h, 'claude.ai') || str_contains($h, 'gemini')) {
                return 'ai_search';
            }
            $self = (string)parse_url(abs_url('/'), PHP_URL_HOST);
            if ($h !== '' && $h !== $self) {
                return 'referral';
            }
        }
        return 'direct';
    }

    /** @return array{status: string, token: ?string, already: bool, contact_id: int} */
    public static function subscribe(string $email, string $name, string $source, string $page = '', ?int $contactId = null): array
    {
        $c = $contactId ? DB::row('SELECT * FROM contacts WHERE id = ?', [$contactId]) : self::findContact($email);
        $now = DB::now();
        if (!$c) {
            $id = DB::insert('contacts', [
                'name' => $name !== '' ? $name : $email,
                'email' => $email,
                'status' => 'lead',
                'source' => $source,
                'newsletter' => 'none',
                'unsub_token' => random_token(18),
                'notes' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $c = DB::row('SELECT * FROM contacts WHERE id = ?', [$id]);
        }
        if ($c['newsletter'] === 'subscribed') {
            return ['status' => 'subscribed', 'token' => null, 'already' => true, 'contact_id' => (int)$c['id']];
        }
        $double = Settings::get('newsletter_double_optin') === '1';
        $token = $double ? random_token(24) : null;
        DB::update('contacts', [
            'newsletter' => $double ? 'pending' : 'subscribed',
            'confirm_token' => $token,
            'newsletter_consent_at' => $double ? null : $now,
            'newsletter_consent_ip' => client_ip(),
            'unsub_token' => $c['unsub_token'] ?: random_token(18),
            'updated_at' => $now,
        ], 'id = :id', ['id' => $c['id']]);
        self::logActivity((int)$c['id'], null, 'newsletter', 'Abonare newsletter' . ($page ? " de pe $page" : '') . ($double ? ' (așteaptă confirmarea)' : ''));
        return ['status' => $double ? 'pending' : 'subscribed', 'token' => $token, 'already' => false, 'contact_id' => (int)$c['id']];
    }

    public static function sendOptinEmail(string $email, string $name, string $token): void
    {
        $link = abs_url('/newsletter/confirmare/' . $token);
        $body = '<p>Salut' . ($name ? ' ' . e(explode(' ', $name)[0]) : '') . ',</p>'
            . '<p>Mai ai un singur pas: confirmă abonarea la newsletterul ' . e((string)Settings::get('brand_name')) . ' apăsând pe butonul de mai jos.</p>'
            . '<p style="margin:28px 0"><a href="' . e($link) . '" style="background:#2f6bff;color:#ffffff;padding:14px 26px;border-radius:999px;text-decoration:none;font-weight:600;display:inline-block">Confirm abonarea</a></p>'
            . '<p style="color:#6b7489;font-size:14px">Dacă nu tu ai cerut abonarea, ignoră acest email – nu te vom adăuga pe listă.</p>';
        Mailer::send($email, 'Confirmă abonarea la newsletter', Mailer::layout($body, ['preheader' => 'Un click și ești abonat.']), ['kind' => 'optin']);
    }

    public static function notifyNewLead(int $contactId, int $dealId, array $d, string $serviceTitle, array $utm): void
    {
        $to = (string)Settings::get('notify_email', (string)Settings::get('email'));
        if ($to === '') {
            return;
        }
        $rows = [
            'Nume' => $d['name'], 'Firmă' => $d['company'], 'Telefon' => $d['phone'], 'Email' => $d['email'],
            'Serviciu' => $serviceTitle ?: '—', 'Județ' => $d['county'], 'Buget' => $d['budget'],
            'Sursă' => self::sourceFromUtm($utm), 'Pagina' => $utm['page'] ?? '', 'Campanie' => $utm['utm_campaign'] ?? '',
        ];
        $html = '<h2 style="margin:0 0 16px">Cerere nouă de pe site</h2><table cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:15px">';
        foreach ($rows as $k => $v) {
            if ((string)$v !== '') {
                $html .= '<tr><td style="border-bottom:1px solid #eee;color:#6b7489;width:120px">' . e($k) . '</td><td style="border-bottom:1px solid #eee"><strong>' . e($v) . '</strong></td></tr>';
            }
        }
        $html .= '</table><p style="margin:20px 0 8px;color:#6b7489">Mesaj:</p><div style="background:#f5f7fb;padding:16px;border-radius:12px;white-space:pre-wrap">' . nl2br(e($d['message'])) . '</div>';
        $html .= '<p style="margin:28px 0 0"><a href="' . e(abs_url('/admin/crm/contacte/' . $contactId)) . '" style="background:#2f6bff;color:#fff;padding:12px 22px;border-radius:999px;text-decoration:none;font-weight:600">Deschide în CRM</a> &nbsp; <a href="tel:' . e(preg_replace('/[^0-9+]/', '', $d['phone'])) . '" style="color:#2f6bff">Sună acum</a></p>';
        foreach (array_filter(array_map('trim', explode(',', $to))) as $addr) {
            Mailer::send($addr, '🔔 Lead nou: ' . $d['name'] . ($serviceTitle ? ' – ' . $serviceTitle : ''), Mailer::layout($html), ['kind' => 'notify', 'reply_to' => $d['email'], 'reply_name' => $d['name']]);
        }
    }

    public static function logActivity(?int $contactId, ?int $dealId, string $type, string $body, ?string $dueAt = null): int
    {
        $uid = null;
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['uid'])) {
            $uid = (int)$_SESSION['uid'];
        }
        return DB::insert('activities', [
            'contact_id' => $contactId,
            'deal_id' => $dealId,
            'user_id' => $uid,
            'type' => $type,
            'body' => $body,
            'due_at' => $dueAt,
            'done' => $type === 'task' ? 0 : 1,
            'created_at' => DB::now(),
        ]);
    }

    public static function sourceLabel(?string $s): string
    {
        return match ($s) {
            'google_ads' => 'Google Ads',
            'meta' => 'Meta Ads',
            'google_organic' => 'Google (organic)',
            'search_organic' => 'Căutare organică',
            'ai_search' => 'Căutare AI',
            'social' => 'Social media',
            'referral' => 'Recomandare (link)',
            'direct' => 'Direct',
            'formular' => 'Formular site',
            'asistent_ai' => 'Asistent AI (chat site)',
            'newsletter' => 'Newsletter',
            'import' => 'Import',
            'manual' => 'Adăugat manual',
            null, '' => '—',
            default => $s,
        };
    }
}
