<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Csrf;
use App\Core\DB;
use App\Core\Mailer;
use App\Core\RateLimit;
use App\Core\Sanitizer;
use App\Core\Settings;

final class FormController extends SiteController
{
    public function token(): never
    {
        json_out(['t' => Csrf::formToken()]);
    }

    private function spamScore(string $text, array $fields): int
    {
        $score = 0;
        if (str_input('website') !== '') {
            $score += 10;
        }
        if (!Csrf::verifyFormToken(str_input('_t'))) {
            $score += 6;
        }
        if (preg_match_all('#https?://#i', $text) > 2) {
            $score += 4;
        }
        if (preg_match('/[\x{0400}-\x{04FF}\x{4E00}-\x{9FFF}]/u', $text)) {
            $score += 3;
        }
        foreach ($fields as $f) {
            if (preg_match('#https?://#i', $f)) {
                $score += 3;
            }
        }
        return $score;
    }

    private function turnstileOk(): bool
    {
        $secret = (string)Settings::get('turnstile_secret');
        if ($secret === '' || (string)Settings::get('turnstile_site_key') === '') {
            return true;
        }
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'timeout' => 6,
            'content' => http_build_query(['secret' => $secret, 'response' => str_input('cf-turnstile-response'), 'remoteip' => client_ip()])]]);
        $r = json_decode((string)@file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $ctx), true);
        return !empty($r['success']);
    }

    public function contact(): never
    {
        if (!Csrf::sameOrigin()) {
            json_out(['ok' => false, 'error' => 'Cerere invalidă.'], 403);
        }
        if (!RateLimit::hit('contact:' . client_ip(), 6, 3600)) {
            json_out(['ok' => false, 'error' => 'Ai trimis prea multe mesaje. Încearcă din nou mai târziu sau scrie-ne pe email.'], 429);
        }
        $name = Sanitizer::text(str_input('name'), 120);
        $email = mb_strtolower(Sanitizer::text(str_input('email'), 160));
        $phone = Sanitizer::text(str_input('phone'), 40);
        $subject = Sanitizer::text(str_input('subject'), 200);
        $msg = Sanitizer::text(str_input('message'), 5000);
        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Spune-ne cum te numești.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Adresa de email nu este validă.';
        }
        if (mb_strlen($msg) < 5) {
            $errors['message'] = 'Scrie-ne câteva cuvinte despre ce ai nevoie.';
        }
        if (empty($_POST['consent'])) {
            $errors['consent'] = 'Avem nevoie de acordul tău ca să îți putem răspunde.';
        }
        if ($errors) {
            json_out(['ok' => false, 'errors' => $errors, 'error' => 'Verifică câmpurile marcate.'], 422);
        }
        if (!$this->turnstileOk()) {
            json_out(['ok' => false, 'error' => 'Verificarea anti-spam a eșuat. Reîncarcă pagina.'], 422);
        }
        $score = $this->spamScore($msg, [$name, $subject]);
        $id = DB::insert('messages', [
            'name' => $name, 'email' => $email, 'phone' => $phone ?: null, 'subject' => $subject ?: null, 'message' => $msg,
            'page' => mb_substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 250), 'ip' => client_ip(),
            'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250), 'spam_score' => $score, 'created_at' => DB::now(),
        ]);
        if ($score < 6) {
            $body = '<h2>Mesaj nou de pe site</h2><p><strong>' . e($name) . '</strong> · <a href="mailto:' . e($email) . '">' . e($email) . '</a>' . ($phone ? ' · ' . e($phone) : '') . '</p>'
                . ($subject ? '<p><strong>Subiect:</strong> ' . e($subject) . '</p>' : '') . '<p style="background:#faf5ee;padding:14px;border-radius:12px">' . nl2br(e($msg)) . '</p>'
                . '<p><a href="' . e(abs_url('/admin/mesaje/' . $id)) . '">Deschide în panou</a> · poți răspunde direct la acest email.</p>';
            foreach (array_filter(array_map('trim', explode(',', (string)Settings::get('notify_email')))) as $to) {
                Mailer::send($to, '✉️ Mesaj nou: ' . ($subject ?: $name), Mailer::layout($body), ['kind' => 'contact', 'reply_to' => $email, 'reply_name' => $name]);
            }
        }
        json_out(['ok' => true, 'message' => 'Mulțumim, ' . explode(' ', $name)[0] . '! Am primit mesajul și îți răspundem în cel mai scurt timp.']);
    }

    /** Recenzie trimisă de un client – apare pe site după aprobare în panou. */
    public function review(): never
    {
        if (!Csrf::sameOrigin() || !RateLimit::hit('review:' . client_ip(), 4, 3600)) {
            json_out(['ok' => false, 'error' => 'Prea multe încercări. Încearcă mai târziu.'], 429);
        }
        $pid = (int)input('product_id', 0);
        $name = Sanitizer::text(str_input('name'), 80);
        $text = Sanitizer::text(str_input('text'), 2000);
        $rating = max(1, min(5, (int)input('rating', 5)));
        if (!DB::val('SELECT id FROM products WHERE id = ? AND published = 1', [$pid]) || $name === '' || mb_strlen($text) < 10) {
            json_out(['ok' => false, 'error' => 'Completează numele și o părere de cel puțin câteva cuvinte.'], 422);
        }
        if ($this->spamScore($text, [$name]) >= 6) {
            json_out(['ok' => true, 'message' => 'Mulțumim! Recenzia ta va apărea după verificare.']);
        }
        $email = mb_strtolower(Sanitizer::text(str_input('email'), 160));
        $verified = $email !== '' && DB::val("SELECT o.id FROM orders o JOIN order_items i ON i.order_id = o.id WHERE LOWER(o.email) = ? AND i.product_id = ? AND o.payment_status = 'platita'", [$email, $pid]) ? 1 : 0;
        DB::insert('reviews', ['product_id' => $pid, 'name' => $name, 'city' => Sanitizer::text(str_input('city'), 80) ?: null, 'rating' => $rating, 'text' => $text, 'verified' => $verified, 'published' => 0, 'created_at' => DB::now()]);
        json_out(['ok' => true, 'message' => 'Mulțumim pentru părere! Recenzia ta va apărea pe site după verificare.']);
    }
}
