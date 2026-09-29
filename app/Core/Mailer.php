<?php
declare(strict_types=1);

namespace App\Core;

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Trimitere email prin SMTP (recomandat: contul de email din cPanel) sau mail() ca rezervă.
 */
final class Mailer
{
    /**
     * @param array{text?:string, reply_to?:string, reply_name?:string, unsubscribe?:string, kind?:string, headers?:array} $opt
     * @return array{0: bool, 1: string}
     */
    public static function send(string $to, string $subject, string $html, array $opt = []): array
    {
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return [false, 'Adresă de email invalidă'];
        }
        $mail = new PHPMailer(true);
        try {
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $driver = (string)Settings::get('mail_driver', 'smtp');
            $host = (string)Settings::get('smtp_host', '');
            if ($driver === 'smtp' && $host !== '') {
                $mail->isSMTP();
                $mail->Host = $host;
                $mail->Port = (int)Settings::get('smtp_port', '465');
                $secure = (string)Settings::get('smtp_secure', 'ssl');
                $mail->SMTPSecure = $secure === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : ($secure === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : '');
                $mail->SMTPAutoTLS = $secure !== 'none';
                $user = (string)Settings::get('smtp_user', '');
                if ($user !== '') {
                    $mail->SMTPAuth = true;
                    $mail->Username = $user;
                    $mail->Password = (string)Settings::get('smtp_pass', '');
                }
                $mail->Timeout = 20;
                $mail->SMTPKeepAlive = !empty($opt['keepalive']);
            } else {
                $mail->isMail();
            }
            $from = (string)Settings::get('mail_from', (string)Settings::get('email', ''));
            $mail->setFrom($from, (string)Settings::get('mail_from_name', 'VITIM'));
            $mail->Sender = $from;
            $mail->addAddress($to, (string)($opt['to_name'] ?? ''));
            $replyTo = $opt['reply_to'] ?? (string)Settings::get('mail_reply_to', '');
            if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($replyTo, (string)($opt['reply_name'] ?? ''));
            }
            if (!empty($opt['unsubscribe'])) {
                // cerință Gmail/Yahoo pentru trimiteri în masă: dezabonare cu un click (RFC 8058)
                $mail->addCustomHeader('List-Unsubscribe', '<' . $opt['unsubscribe'] . '>, <mailto:' . $from . '?subject=unsubscribe>');
                $mail->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            }
            foreach ($opt['headers'] ?? [] as $k => $v) {
                $mail->addCustomHeader($k, $v);
            }
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $opt['text'] ?? self::toText($html);
            $mail->send();
            self::log($to, $subject, $opt['kind'] ?? 'system', 'sent', '');
            return [true, ''];
        } catch (\Throwable $e) {
            $err = $mail->ErrorInfo ?: $e->getMessage();
            self::log($to, $subject, $opt['kind'] ?? 'system', 'failed', $err);
            return [false, $err];
        }
    }

    public static function toText(string $html): string
    {
        $html = (string)preg_replace('#<a[^>]+href="([^"]+)"[^>]*>(.*?)</a>#si', '$2 ($1)', $html);
        $html = (string)preg_replace('#<(br|/p|/h[1-6]|/li|/tr)[^>]*>#i', "\n", $html);
        $t = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
        return trim((string)preg_replace("/\n{3,}/", "\n\n", (string)preg_replace('/[ \t]+/', ' ', $t)));
    }

    private static function log(string $to, string $subject, string $kind, string $status, string $error): void
    {
        try {
            DB::insert('email_log', [
                'to_email' => $to,
                'subject' => mb_substr($subject, 0, 250),
                'kind' => $kind,
                'status' => $status,
                'error' => mb_substr($error, 0, 2000),
                'created_at' => DB::now(),
            ]);
        } catch (\Throwable) {
        }
    }

    /** Înlocuiește variabilele {{nume}}, {{prenume}}, {{email}}, {{firma}}, {{telefon}}. */
    public static function merge(string $text, array $vars): string
    {
        $name = trim((string)($vars['name'] ?? ''));
        if (str_contains($name, '@')) {
            $name = ''; // abonații fără nume au emailul salvat ca nume
        }
        $first = $name !== '' ? explode(' ', $name)[0] : '';
        if ($first === '') {
            // „Salut {{prenume}},” → „Salut,” ; „Noutăți {{prenume}}” → „Noutăți”
            $text = (string)preg_replace('/\s*\{\{prenume\}\}/u', '', $text);
        }
        $map = [
            '{{nume}}' => $name !== '' ? $name : 'client',
            '{{prenume}}' => $first,
            '{{email}}' => (string)($vars['email'] ?? ''),
            '{{firma}}' => (string)($vars['company'] ?? ''),
            '{{telefon}}' => (string)Settings::get('phone', ''),
            '{{site}}' => abs_url('/'),
            '{{an}}' => date('Y'),
        ];
        foreach ($vars['extra'] ?? [] as $k => $v) {
            $map['{{' . $k . '}}'] = (string)$v;
        }
        return strtr($text, $map);
    }

    /** Învelește conținutul în șablonul de email al brandului. */
    public static function layout(string $bodyHtml, array $opt = []): string
    {
        return View::partial('email/layout', [
            'body' => $bodyHtml,
            'preheader' => $opt['preheader'] ?? '',
            'unsubscribe' => $opt['unsubscribe'] ?? '',
            'pixel' => $opt['pixel'] ?? '',
            'reason' => $opt['reason'] ?? '',
        ]);
    }
}
