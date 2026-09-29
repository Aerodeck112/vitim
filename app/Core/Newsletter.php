<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Motorul de campanii email: audiență, coadă, trimitere în loturi, urmărire deschideri/click-uri.
 */
final class Newsletter
{
    public const KINDS = [
        'newsletter' => 'Newsletter / marketing',
        'notificare' => 'Notificare de serviciu către clienți',
    ];

    public const STATUSES = [
        'draft' => 'Ciornă',
        'scheduled' => 'Programată',
        'sending' => 'Se trimite',
        'paused' => 'Pe pauză',
        'sent' => 'Trimisă',
    ];

    /**
     * Construiește interogarea audienței.
     * audience: {segment: subscribers|clients|all_contacts, tags: [], statuses: [], counties: []}
     */
    public static function audienceQuery(array $a, string $kind): array
    {
        $where = ["email IS NOT NULL", "email <> ''"];
        $params = [];
        $segment = $a['segment'] ?? 'subscribers';
        if ($kind === 'newsletter' || $segment === 'subscribers') {
            // marketing: doar abonați confirmați (GDPR)
            $where[] = "newsletter = 'subscribed'";
        } else {
            // notificări de serviciu: excludem doar pe cei care s-au dezabonat explicit
            $where[] = "newsletter <> 'unsubscribed'";
        }
        $statuses = array_values(array_filter((array)($a['statuses'] ?? [])));
        if ($segment === 'clients' && !$statuses) {
            $statuses = ['client'];
        }
        if ($statuses) {
            $in = [];
            foreach ($statuses as $i => $s) {
                $in[] = ":st$i";
                $params["st$i"] = $s;
            }
            $where[] = 'status IN (' . implode(',', $in) . ')';
        }
        $counties = array_values(array_filter((array)($a['counties'] ?? [])));
        if ($counties) {
            $in = [];
            foreach ($counties as $i => $c) {
                $in[] = ":co$i";
                $params["co$i"] = $c;
            }
            $where[] = 'county IN (' . implode(',', $in) . ')';
        }
        $tags = array_values(array_filter(array_map('trim', (array)($a['tags'] ?? []))));
        if ($tags) {
            $or = [];
            foreach ($tags as $i => $t) {
                $or[] = "(',' || REPLACE(COALESCE(tags,''), ' ', '') || ',') LIKE :tg$i";
                $params["tg$i"] = '%,' . str_replace(' ', '', $t) . ',%';
            }
            $where[] = '(' . implode(' OR ', $or) . ')';
        }
        $sql = implode(' AND ', $where);
        if (DB::driver() === 'mysql') {
            $sql = str_replace("(',' || REPLACE(COALESCE(tags,''), ' ', '') || ',')", "CONCAT(',', REPLACE(COALESCE(tags,''), ' ', ''), ',')", $sql);
        }
        return [$sql, $params];
    }

    public static function audienceCount(array $a, string $kind): int
    {
        [$w, $p] = self::audienceQuery($a, $kind);
        return (int)DB::val("SELECT COUNT(DISTINCT LOWER(email)) FROM contacts WHERE $w", $p);
    }

    /** Creează lista de destinatari (o singură dată per campanie). */
    public static function prepare(array $c): int
    {
        if ((int)DB::val('SELECT COUNT(*) FROM campaign_recipients WHERE campaign_id = ?', [$c['id']]) > 0) {
            return (int)$c['total'];
        }
        [$w, $p] = self::audienceQuery(json_list($c['audience']), (string)$c['kind']);
        $rows = DB::all("SELECT id, name, email, unsub_token FROM contacts WHERE $w ORDER BY id", $p);
        $seen = [];
        $n = 0;
        DB::transaction(function () use ($rows, $c, &$seen, &$n) {
            foreach ($rows as $r) {
                $em = mb_strtolower(trim((string)$r['email']));
                if (isset($seen[$em]) || !filter_var($em, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                $seen[$em] = true;
                if (!$r['unsub_token']) {
                    DB::update('contacts', ['unsub_token' => random_token(18)], 'id = :id', ['id' => $r['id']]);
                }
                DB::insert('campaign_recipients', [
                    'campaign_id' => $c['id'],
                    'contact_id' => $r['id'],
                    'email' => $em,
                    'name' => $r['name'],
                    'token' => random_token(18),
                    'status' => 'queued',
                ]);
                $n++;
            }
        });
        DB::update('campaigns', ['total' => $n], 'id = :id', ['id' => $c['id']]);
        self::prepareLinks((int)$c['id'], (string)$c['body']);
        return $n;
    }

    private static function prepareLinks(int $campaignId, string $html): void
    {
        preg_match_all('#<a\s[^>]*href="(https?://[^"]+)"#i', $html, $m);
        foreach (array_unique($m[1]) as $u) {
            $u = html_entity_decode($u, ENT_QUOTES, 'UTF-8');
            if (!DB::val('SELECT id FROM campaign_links WHERE campaign_id = ? AND url = ?', [$campaignId, $u])) {
                DB::insert('campaign_links', ['campaign_id' => $campaignId, 'url' => $u, 'clicks' => 0]);
            }
        }
    }

    /** Randează emailul final pentru un destinatar (variabile + tracking + dezabonare). */
    public static function render(array $c, ?array $rcpt, bool $track = true): array
    {
        $contact = $rcpt && $rcpt['contact_id'] ? DB::row('SELECT * FROM contacts WHERE id = ?', [$rcpt['contact_id']]) : null;
        $vars = ['name' => $contact['name'] ?? ($rcpt['name'] ?? ''), 'email' => $rcpt['email'] ?? '', 'company' => $contact['company'] ?? ''];
        $body = Mailer::merge((string)$c['body'], $vars);
        $subject = Mailer::merge((string)$c['subject'], $vars);
        $unsub = $contact && $contact['unsub_token'] ? abs_url('/newsletter/dezabonare/' . $contact['unsub_token']) . '?c=' . $c['id'] : abs_url('/newsletter/dezabonare/preview');
        if ($track && $rcpt) {
            $links = DB::all('SELECT id, url FROM campaign_links WHERE campaign_id = ?', [$c['id']]);
            $map = [];
            foreach ($links as $l) {
                $map[$l['url']] = abs_url('/e/c/' . $rcpt['token'] . '/' . $l['id']);
            }
            $body = (string)preg_replace_callback('#(<a\s[^>]*href=")(https?://[^"]+)(")#i', function ($m) use ($map) {
                $u = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
                return $m[1] . e($map[$u] ?? $u) . $m[3];
            }, $body);
        }
        $reason = $c['kind'] === 'newsletter'
            ? 'Primești acest email pentru că te-ai abonat la noutățile ' . Settings::get('brand_name') . '.'
            : 'Primești această notificare pentru că ești client ' . Settings::get('brand_name') . '.';
        $html = Mailer::layout($body, [
            'preheader' => (string)$c['preheader'],
            'unsubscribe' => $unsub,
            'pixel' => $track && $rcpt ? abs_url('/e/o/' . $rcpt['token'] . '.gif') : '',
            'reason' => $reason,
        ]);
        return [$subject, $html, $unsub];
    }

    /** Trimite următorul lot. Respectă limita pe oră (hosting partajat). */
    public static function sendBatch(int $campaignId, ?int $size = null): array
    {
        $c = DB::row('SELECT * FROM campaigns WHERE id = ?', [$campaignId]);
        if (!$c || !in_array($c['status'], ['sending', 'scheduled'], true)) {
            return ['done' => true, 'sent' => 0, 'message' => 'Campania nu este activă.'];
        }
        if ($c['status'] === 'scheduled') {
            DB::update('campaigns', ['status' => 'sending', 'started_at' => DB::now()], 'id = :id', ['id' => $c['id']]);
        }
        $limit = max(10, (int)Settings::get('newsletter_hourly_limit', '200'));
        $sentLastHour = (int)DB::val("SELECT COUNT(*) FROM email_log WHERE kind = 'campaign' AND created_at >= ?", [gmdate('Y-m-d H:i:s', time() - 3600)]);
        $room = $limit - $sentLastHour;
        if ($room <= 0) {
            return ['done' => false, 'sent' => 0, 'wait' => true, 'message' => "Limita de $limit emailuri/oră a fost atinsă. Trimiterea continuă automat (cron) sau reia peste câteva minute."];
        }
        $size = min($size ?? (int)Settings::get('newsletter_batch', '20'), $room, 100);
        $rows = DB::all("SELECT * FROM campaign_recipients WHERE campaign_id = ? AND status = 'queued' ORDER BY id LIMIT $size", [$c['id']]);
        $sent = 0;
        $failed = 0;
        foreach ($rows as $r) {
            [$subject, $html, $unsub] = self::render($c, $r, true);
            [$ok, $err] = Mailer::send($r['email'], $subject, $html, [
                'kind' => 'campaign',
                'unsubscribe' => $unsub,
                'to_name' => (string)$r['name'],
                'keepalive' => true,
                'headers' => ['X-Campaign-ID' => 'vitim-' . $c['id'], 'Precedence' => 'bulk'],
            ]);
            DB::update('campaign_recipients', ['status' => $ok ? 'sent' : 'failed', 'error' => $ok ? null : mb_substr($err, 0, 250), 'sent_at' => DB::now()], 'id = :id', ['id' => $r['id']]);
            $ok ? $sent++ : $failed++;
            if ($ok && $r['contact_id']) {
                Crm::logActivity((int)$r['contact_id'], null, 'campaign', 'A primit campania „' . $c['name'] . '”.');
            }
        }
        $left = (int)DB::val("SELECT COUNT(*) FROM campaign_recipients WHERE campaign_id = ? AND status = 'queued'", [$c['id']]);
        $upd = [
            'sent' => (int)DB::val("SELECT COUNT(*) FROM campaign_recipients WHERE campaign_id = ? AND status = 'sent'", [$c['id']]),
            'failed' => (int)DB::val("SELECT COUNT(*) FROM campaign_recipients WHERE campaign_id = ? AND status = 'failed'", [$c['id']]),
            'updated_at' => DB::now(),
        ];
        if ($left === 0) {
            $upd['status'] = 'sent';
            $upd['finished_at'] = DB::now();
        }
        DB::update('campaigns', $upd, 'id = :id', ['id' => $c['id']]);
        return ['done' => $left === 0, 'sent' => $sent, 'failed' => $failed, 'left' => $left, 'total' => (int)$c['total'], 'message' => ''];
    }

    /** Rulat de cron: pornește campaniile programate și continuă trimiterile în curs. */
    public static function cron(): array
    {
        $log = [];
        foreach (DB::all("SELECT id FROM campaigns WHERE status = 'scheduled' AND scheduled_at <= ?", [DB::now()]) as $c) {
            $camp = DB::row('SELECT * FROM campaigns WHERE id = ?', [$c['id']]);
            self::prepare($camp);
            DB::update('campaigns', ['status' => 'sending', 'started_at' => DB::now()], 'id = :id', ['id' => $c['id']]);
            $log[] = "Campania #{$c['id']} a pornit.";
        }
        $deadline = time() + 45;
        foreach (DB::all("SELECT id FROM campaigns WHERE status = 'sending' ORDER BY id") as $c) {
            while (time() < $deadline) {
                $r = self::sendBatch((int)$c['id']);
                $log[] = "Campania #{$c['id']}: trimise {$r['sent']}" . (isset($r['left']) ? ", rămase {$r['left']}" : '') . ($r['message'] ? ' – ' . $r['message'] : '');
                if ($r['done'] || !empty($r['wait']) || $r['sent'] === 0) {
                    break;
                }
            }
        }
        return $log;
    }
}
