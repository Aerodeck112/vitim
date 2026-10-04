<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Crm;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\Mailer;
use App\Core\RateLimit;
use App\Core\Sanitizer;
use App\Core\Seo;
use App\Core\Settings;
use App\Core\View;

final class FormController extends SiteController
{
    public function token(): never
    {
        header('X-Robots-Tag: noindex');
        json_out(['token' => Csrf::formToken()]);
    }

    public function contact(): never
    {
        $spam = $this->guard('contact', 6);
        $d = [
            'name' => Sanitizer::text(str_input('name'), 120),
            'email' => mb_strtolower(Sanitizer::text(str_input('email'), 160)),
            'phone' => Sanitizer::text(str_input('phone'), 30),
            'company' => Sanitizer::text(str_input('company'), 160),
            'service' => preg_replace('/[^a-z0-9-]/', '', str_input('service')),
            'county' => Sanitizer::text(str_input('county'), 60),
            'budget' => Sanitizer::text(str_input('budget'), 60),
            'message' => Sanitizer::text(str_input('message'), 5000),
        ];
        // formularul scurt (v1.7) are un singur câmp „Telefon sau email”
        $contact = Sanitizer::text(str_input('contact'), 160);
        if ($contact !== '') {
            if (str_contains($contact, '@')) {
                $d['email'] = $d['email'] ?: mb_strtolower($contact);
            } else {
                $d['phone'] = $d['phone'] ?: mb_substr($contact, 0, 30);
            }
        }
        $errors = [];
        if (mb_strlen($d['name']) < 2) {
            $errors[] = 'numele';
        }
        $emailOk = filter_var($d['email'], FILTER_VALIDATE_EMAIL) !== false;
        $phoneOk = strlen(preg_replace('/\D/', '', $d['phone'])) >= 9;
        if ($d['email'] !== '' && !$emailOk) {
            $errors[] = 'un email valid';
        } elseif ($d['phone'] !== '' && !$phoneOk) {
            $errors[] = 'un număr de telefon valid';
        } elseif (!$emailOk && !$phoneOk) {
            $errors[] = 'un telefon sau un email la care te putem contacta';
        }
        if (mb_strlen($d['message']) < 5) {
            $errors[] = 'mesajul';
        }
        if (empty($_POST['consent'])) {
            $errors[] = 'acordul pentru prelucrarea datelor';
        }
        if ($errors) {
            json_out(['ok' => false, 'message' => 'Te rugăm să completezi ' . implode(', ', $errors) . '.'], 422);
        }
        // tipul colaborării: bugetul lunar (abonament) nu se amestecă cu bugetul unui proiect
        $type = ['abonament' => 'Abonament lunar', 'proiect' => 'Proiect (o singură dată)', 'nu-stiu' => 'Nu știu încă'][str_input('budget_type')] ?? '';
        if ($d['budget'] !== '' && $type !== '' && $type !== 'Nu știu încă') {
            $d['budget'] = $type . ': ' . $d['budget'];
        } elseif ($type !== '' && $d['budget'] === '') {
            $d['budget'] = $type;
        }
        // detaliile opționale (pasul 2) se adaugă la mesaj, ca să ajungă în CRM și în notificare
        $extra = [];
        foreach (['employees' => 'Angajați', 'computers' => 'Calculatoare'] as $k => $label) {
            $v = Sanitizer::text(str_input($k), 30);
            if ($v !== '') {
                $extra[] = $label . ': ' . $v;
            }
        }
        $needs = array_filter(array_map(fn($v) => Sanitizer::text(is_string($v) ? $v : '', 40), array_slice((array)($_POST['needs'] ?? []), 0, 8)));
        if ($needs) {
            $extra[] = 'Servicii de interes: ' . implode(', ', $needs);
        }
        if ($extra) {
            $d['message'] .= "\n\n" . implode("\n", $extra);
        }
        if (preg_match_all('#https?://#i', $d['message']) > 3) {
            $spam += 3;
        }
        $utm = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid', 'page', 'referrer'] as $k) {
            $v = Sanitizer::text(str_input($k), 300);
            if ($v !== '') {
                $utm[$k] = $v;
            }
        }

        $serviceTitle = $d['service'] ? (string)DB::val('SELECT title FROM services WHERE slug = ?', [$d['service']]) : '';
        $result = Crm::captureLead($d + ['service_title' => $serviceTitle], $utm, $spam, !empty($_POST['newsletter']) && $emailOk);

        $this->respondThenContinue(['ok' => true, 'message' => 'Mulțumim! Am primit mesajul și revenim în curând.', 'redirect' => url('/multumim')]);

        if ($spam < 5) {
            Crm::notifyNewLead($result['contact_id'], $result['deal_id'], $d, $serviceTitle, $utm);
            if ($emailOk && Settings::get('autoreply_enabled') === '1') {
                $body = Mailer::merge((string)Settings::get('autoreply_body'), ['name' => $d['name'], 'email' => $d['email'], 'company' => $d['company']]);
                Mailer::send($d['email'], Mailer::merge((string)Settings::get('autoreply_subject'), ['name' => $d['name']]), Mailer::layout($body, ['reason' => 'Primești acest email pentru că ai trimis o solicitare pe ' . parse_url(abs_url('/'), PHP_URL_HOST) . '.']), ['kind' => 'autoreply', 'to_name' => $d['name']]);
            }
            if (!empty($result['confirm_token'])) {
                Crm::sendOptinEmail($d['email'], $d['name'], $result['confirm_token']);
            }
        }
        exit;
    }

    /** „Testează un agent AI pentru firma ta”: omul își lasă site-ul și emailul; cererea intră în CRM ca lead. */
    public function demo(): never
    {
        $spam = $this->guard('demo', 4);
        $site = self::normalizeSite(str_input('site_url'));
        $d = [
            'name' => Sanitizer::text(str_input('name'), 120),
            'email' => mb_strtolower(Sanitizer::text(str_input('email'), 160)),
            'phone' => Sanitizer::text(str_input('phone'), 30),
        ];
        $errors = [];
        if ($site === null) {
            $errors[] = 'adresa site-ului (ex: firma.ro)';
        }
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'un email valid';
        }
        if (empty($_POST['consent'])) {
            $errors[] = 'acordul pentru prelucrarea datelor';
        }
        if ($errors) {
            json_out(['ok' => false, 'message' => 'Te rugăm să completezi ' . implode(', ', $errors) . '.'], 422);
        }
        $utm = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid', 'page', 'referrer'] as $k) {
            $v = Sanitizer::text(str_input($k), 300);
            if ($v !== '') {
                $utm[$k] = $v;
            }
        }
        $host = (string)parse_url($site, PHP_URL_HOST);
        $d += [
            'company' => $host,
            'service' => '',
            'service_title' => 'Demo agent AI',
            'county' => '',
            'budget' => '',
            'message' => "Cerere demo agent AI pentru site-ul: {$site}",
            'site_url' => $site,
        ];
        if ($d['name'] === '') {
            $d['name'] = $d['email'];
        }
        $result = Crm::captureLead($d, $utm, $spam, false);

        $this->respondThenContinue(['ok' => true, 'message' => "Am primit cererea pentru {$host}. Pregătim demo-ul și îți trimitem linkul pe email, de regulă în aceeași zi lucrătoare."]);

        if ($spam < 5) {
            Crm::notifyNewLead($result['contact_id'], $result['deal_id'], $d, 'Demo agent AI', $utm);
        }
        exit;
    }

    /** Acceptă „firma.ro”, „www.firma.ro” sau un URL complet; întoarce „https://domeniu” sau null. Fără IP-uri și nume locale. */
    public static function normalizeSite(string $input): ?string
    {
        $input = trim(mb_substr($input, 0, 255));
        if ($input === '') {
            return null;
        }
        if (!preg_match('#^https?://#i', $input)) {
            $input = 'https://' . $input;
        }
        $host = parse_url($input, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return null;
        }
        $host = rtrim(mb_strtolower($host), '.');
        if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7e]/', $host)) {
            $host = (string)idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        }
        if (strlen($host) > 253 || filter_var($host, FILTER_VALIDATE_IP) || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+(?:[a-z]{2,63}|xn--[a-z0-9-]{1,59})$/', $host)) {
            return null;
        }
        if (preg_match('/(^|\.)(localhost|local|internal|lan|home|test|example|invalid)$/', $host)) {
            return null;
        }
        return 'https://' . $host;
    }

    public function newsletter(): never
    {
        $spam = $this->guard('newsletter', 5);
        $email = mb_strtolower(Sanitizer::text(str_input('email'), 160));
        $name = Sanitizer::text(str_input('name'), 120);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_out(['ok' => false, 'message' => 'Adresa de email nu pare validă.'], 422);
        }
        if (empty($_POST['consent'])) {
            json_out(['ok' => false, 'message' => 'Bifează acordul pentru a te abona.'], 422);
        }
        if ($spam >= 5) {
            json_out(['ok' => true, 'message' => 'Mulțumim! Verifică-ți emailul pentru confirmare.']);
        }
        $r = Crm::subscribe($email, $name, 'newsletter', Sanitizer::text(str_input('page'), 250));
        $double = Settings::get('newsletter_double_optin') === '1';
        $msg = $r['already']
            ? 'Ești deja abonat. Mulțumim!'
            : ($double ? 'Aproape gata! Ți-am trimis un email – apasă pe link pentru a confirma abonarea.' : 'Te-ai abonat cu succes. Mulțumim!');
        $this->respondThenContinue(['ok' => true, 'message' => $msg]);
        if ($r['token'] && $double) {
            Crm::sendOptinEmail($email, $name, $r['token']);
        }
        exit;
    }

    public function thanks(): string
    {
        $seo = $this->seo('Mulțumim pentru mesaj');
        $seo->noindex = true;
        return $this->view('thanks', ['title' => 'Mulțumim! Am primit solicitarea ta.', 'text' => 'Un specialist VITIM te contactează în cel mai scurt timp, de regulă în aceeași zi lucrătoare. Pentru urgențe ne poți suna la ' . Settings::get('phone') . '.'], $seo);
    }

    public function confirm(string $token): string
    {
        $c = DB::row("SELECT * FROM contacts WHERE confirm_token = ?", [$token]);
        $seo = $this->seo('Confirmare abonare');
        $seo->noindex = true;
        if (!$c) {
            return $this->view('thanks', ['title' => 'Link invalid sau deja folosit', 'text' => 'Dacă te-ai abonat deja, nu mai trebuie să faci nimic. Altfel, abonează-te din nou din subsolul site-ului.', 'icon' => 'info'], $seo);
        }
        DB::update('contacts', [
            'newsletter' => 'subscribed',
            'confirm_token' => null,
            'newsletter_consent_at' => DB::now(),
            'newsletter_consent_ip' => client_ip(),
            'unsubscribed_at' => null,
            'updated_at' => DB::now(),
        ], 'id = :id', ['id' => $c['id']]);
        Crm::logActivity((int)$c['id'], null, 'newsletter', 'A confirmat abonarea la newsletter (double opt-in).');
        return $this->view('thanks', ['title' => 'Abonare confirmată!', 'text' => 'Mulțumim! De acum vei primi ghidurile și noutățile noastre. Te poți dezabona oricând dintr-un singur click.'], $seo);
    }

    public function unsubscribe(string $token): string
    {
        $c = DB::row('SELECT * FROM contacts WHERE unsub_token = ?', [$token]);
        $seo = $this->seo('Dezabonare');
        $seo->noindex = true;
        if ($c && ($c['newsletter'] !== 'unsubscribed')) {
            DB::update('contacts', ['newsletter' => 'unsubscribed', 'unsubscribed_at' => DB::now(), 'updated_at' => DB::now()], 'id = :id', ['id' => $c['id']]);
            Crm::logActivity((int)$c['id'], null, 'newsletter', 'S-a dezabonat de la emailuri.');
            $cid = (int)($_GET['c'] ?? 0);
            if ($cid) {
                DB::q('UPDATE campaigns SET unsubs = unsubs + 1 WHERE id = ?', [$cid]);
            }
        }
        if (request_method() === 'POST') {
            // dezabonare one-click (RFC 8058) din clientul de email
            http_response_code(200);
            header('Content-Type: text/plain; charset=utf-8');
            return 'OK';
        }
        return $this->view('thanks', ['title' => 'Te-ai dezabonat', 'text' => 'Nu vei mai primi emailuri de marketing de la noi. Ne pare rău să te vedem plecând – dacă ai o sugestie, scrie-ne oricând.', 'icon' => 'mail'], $seo);
    }

    /** Protecții anti-spam comune. Întoarce un scor de spam (0 = curat). */
    private function guard(string $form, int $max): int
    {
        header('X-Robots-Tag: noindex');
        if (!Csrf::sameOrigin()) {
            json_out(['ok' => false, 'message' => 'Cerere invalidă.'], 403);
        }
        if (!RateLimit::hit('form:' . $form . ':' . client_ip(), $max, 600)) {
            json_out(['ok' => false, 'message' => 'Ai trimis prea multe cereri. Încearcă din nou peste câteva minute sau sună-ne.'], 429);
        }
        $spam = 0;
        if (trim((string)($_POST['website'] ?? '')) !== '') {
            // honeypot completat → bot; răspundem „ok” fără să salvăm
            json_out(['ok' => true, 'message' => 'Mulțumim!', 'redirect' => url('/multumim')]);
        }
        if (!Csrf::verifyFormToken($_POST['_t'] ?? null)) {
            $spam += 2;
        }
        $secret = (string)Settings::get('turnstile_secret');
        if ($secret !== '') {
            $ok = false;
            $resp = (string)($_POST['cf-turnstile-response'] ?? '');
            if ($resp !== '') {
                $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => http_build_query(['secret' => $secret, 'response' => $resp, 'remoteip' => client_ip()]), 'timeout' => 5]]);
                $r = json_decode((string)@file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $ctx), true);
                $ok = !empty($r['success']);
            }
            if (!$ok) {
                json_out(['ok' => false, 'message' => 'Verificarea anti-robot a eșuat. Reîncarcă pagina și încearcă din nou.'], 422);
            }
        }
        return $spam;
    }

    /** Trimite răspunsul JSON imediat, apoi continuă (ex. trimiterea emailurilor) fără ca vizitatorul să aștepte. */
    private function respondThenContinue(array $data): void
    {
        ignore_user_abort(true);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('Content-Length: ' . strlen((string)$json));
        header('Connection: close');
        echo $json;
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        } else {
            @ob_flush();
            flush();
        }
    }
}
