<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Cache;
use App\Core\DB;
use App\Core\Newsletter;
use App\Core\RateLimit;
use App\Core\Settings;

final class TrackController
{
    public function open(string $token): never
    {
        $r = DB::row('SELECT id, campaign_id, opened_at FROM campaign_recipients WHERE token = ?', [$token]);
        if ($r) {
            if (!$r['opened_at']) {
                DB::update('campaign_recipients', ['opened_at' => DB::now(), 'open_count' => 1], 'id = :id', ['id' => $r['id']]);
                DB::q('UPDATE campaigns SET opens = opens + 1 WHERE id = ?', [$r['campaign_id']]);
            } else {
                DB::q('UPDATE campaign_recipients SET open_count = open_count + 1 WHERE id = ?', [$r['id']]);
            }
        }
        header('Content-Type: image/gif');
        header('Cache-Control: no-store, private');
        header('X-Robots-Tag: noindex');
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        exit;
    }

    public function click(string $token, string $link): never
    {
        $l = DB::row('SELECT * FROM campaign_links WHERE id = ?', [(int)$link]);
        $r = DB::row('SELECT id, campaign_id, clicked_at, opened_at FROM campaign_recipients WHERE token = ?', [$token]);
        if (!$l || !$r || (int)$l['campaign_id'] !== (int)$r['campaign_id']) {
            redirect('/');
        }
        DB::q('UPDATE campaign_links SET clicks = clicks + 1 WHERE id = ?', [$l['id']]);
        if (!$r['clicked_at']) {
            DB::update('campaign_recipients', ['clicked_at' => DB::now(), 'click_count' => 1], 'id = :id', ['id' => $r['id']]);
            DB::q('UPDATE campaigns SET clicks = clicks + 1 WHERE id = ?', [$r['campaign_id']]);
        } else {
            DB::q('UPDATE campaign_recipients SET click_count = click_count + 1 WHERE id = ?', [$r['id']]);
        }
        if (!$r['opened_at']) {
            // click fără pixel încărcat (imagini blocate) = tot deschidere
            DB::update('campaign_recipients', ['opened_at' => DB::now(), 'open_count' => 1], 'id = :id', ['id' => $r['id']]);
            DB::q('UPDATE campaigns SET opens = opens + 1 WHERE id = ?', [$r['campaign_id']]);
        }
        $url = (string)$l['url'];
        $camp = DB::row('SELECT name FROM campaigns WHERE id = ?', [$r['campaign_id']]);
        $self = (string)parse_url(abs_url('/'), PHP_URL_HOST);
        if (parse_url($url, PHP_URL_HOST) === $self && !str_contains($url, 'utm_')) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query(['utm_source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => slugify((string)($camp['name'] ?? 'campanie'))]);
        }
        header('Location: ' . $url, true, 302);
        exit;
    }

    /** Cron: https://site.ro/cron?key=... (din cPanel → Cron Jobs, la 5 minute) */
    public function cron(): never
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex');
        $key = (string)Settings::get('cron_key');
        if ($key === '' || !hash_equals($key, (string)($_GET['key'] ?? ''))) {
            http_response_code(403);
            echo 'Cheie invalidă.';
            exit;
        }
        @set_time_limit(90);
        $log = Newsletter::cron();
        RateLimit::gc();
        // cache vechi
        foreach (glob(STORAGE_PATH . '/cache/pages/*') ?: [] as $f) {
            if (filemtime($f) < time() - 86400) {
                @unlink($f);
            }
        }
        // articole programate care tocmai au apărut → golim cache-ul
        $recent = (int)DB::val("SELECT COUNT(*) FROM posts WHERE status = 'published' AND published_at BETWEEN ? AND ?", [gmdate('Y-m-d H:i:s', time() - 600), DB::now()]);
        if ($recent) {
            Cache::clear();
            $log[] = 'Cache golit (articol programat publicat).';
        }
        Settings::set('cron_last_run', DB::now());
        echo "OK " . date('Y-m-d H:i:s') . "\n" . implode("\n", $log);
        exit;
    }
}
