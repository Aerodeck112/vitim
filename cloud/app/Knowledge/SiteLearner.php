<?php

declare(strict_types=1);

namespace App\Knowledge;

use App\Ai\Local\Knowledge;
use App\Audit\SafeFetcher;
use App\Models\Site;
use App\Models\SitePage;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Cache;

/**
 * Citirea site-ului pentru agent, ca un vizitator: sitemap-ul și linkurile interne, doar pe domeniul site-ului,
 * cu respectarea robots.txt. Rulează din cron în tranșe (câteva zeci de pagini pe minut); fiecare citire nouă
 * („rundă”) reîmprospătează paginile, iar cele care nu mai există pe site se șterg la final.
 */
final class SiteLearner
{
    public const MAX_PAGES = 300;

    public const PER_STEP = 25;

    private const SKIP = '#/(wp-admin|wp-login|wp-json|xmlrpc|feed|cart|cos|cosul|checkout|finalizare|my-account|contul-meu|cont|login|autentificare|register|inregistrare|wishlist|compare|tag|author|search|cautare)(/|$|\?)|/page/\d+|\.(jpe?g|png|gif|webp|avif|svg|ico|pdf|zip|rar|docx?|xlsx?|pptx?|mp4|mp3|css|js|xml|json|txt)$#i';

    private const KEEP_QUERY = ['p', 'page_id', 'product', 'id'];

    public function __construct(private readonly PageExtractor $extractor, private readonly AuditLogger $audit) {}

    /** Pune site-ul la rând pentru o citire nouă (pornește în cel mult un minut, din cron). */
    public function queue(Site $site): void
    {
        $site->forceFill(['learn_status' => 'queued', 'learn_error' => null])->save();
    }

    /** O tranșă: la început pornește runda (sitemap + prima pagină), apoi citește paginile din coadă. Întoarce câte pagini a citit. */
    public function step(Site $site, int $budget = self::PER_STEP, int $seconds = 40): int
    {
        $started = time();
        $http = new SafeFetcher($site->domain, $budget * 2 + 10);
        if ($site->learn_status === 'queued') {
            if (! $this->begin($site, $http)) {
                return 0;
            }
        }
        if ($site->learn_status !== 'running') {
            return 0;
        }

        $done = 0;
        $pages = SitePage::query()->where('site_id', $site->id)->where('run', $site->learn_run)->where('status', 'pending')->orderBy('id')->limit($budget)->get();
        foreach ($pages as $page) {
            if (time() - $started > $seconds) {
                break;
            }
            $this->read($site, $page, $http);
            $done++;
        }
        if (! SitePage::query()->where('site_id', $site->id)->where('run', $site->learn_run)->where('status', 'pending')->exists()) {
            $this->finish($site);
        }

        return $done;
    }

    private function begin(Site $site, SafeFetcher $http): bool
    {
        $home = 'https://'.$site->domain.'/';
        $first = $http->get($home);
        if ($first === null || $first['status'] >= 400) {
            $site->forceFill(['learn_status' => 'failed', 'learn_error' => 'Site-ul nu răspunde pe https://'.$site->domain.($first ? ' (HTTP '.$first['status'].')' : '').'.'])->save();

            return false;
        }
        $origin = $this->origin($first['url']);
        $disallow = $this->robots($http, $origin);
        Cache::put($this->robotsKey($site), $disallow, now()->addDay());

        $site->forceFill(['learn_status' => 'running', 'learn_run' => $site->learn_run + 1, 'learn_error' => null])->save();
        $urls = [$first['url']];
        foreach ($this->sitemaps($http, $origin) as $url) {
            $urls[] = $url;
        }
        foreach ($urls as $url) {
            $this->enqueue($site, $url, $http);
        }

        return true;
    }

    private function read(Site $site, SitePage $page, SafeFetcher $http): void
    {
        $response = $http->get($page->url, 3);
        if ($response === null || $response['status'] >= 400) {
            $page->forceFill(['status' => 'error', 'fetched_at' => now()])->save();

            return;
        }
        if (stripos($response['headers']['content-type'] ?? 'text/html', 'html') === false || preg_match('/noindex/i', $response['headers']['x-robots-tag'] ?? '')) {
            $page->forceFill(['status' => 'skipped', 'fetched_at' => now()])->save();

            return;
        }
        // redirect către o pagină deja în listă: o păstrăm o singură dată
        $final = $this->normalize($response['url']);
        if ($final !== null && $final !== $page->url) {
            $twin = SitePage::query()->where('site_id', $site->id)->where('url_hash', SitePage::hash($final))->first();
            if ($twin) {
                $page->forceFill(['status' => 'skipped', 'fetched_at' => now()])->save();
                if ($twin->run !== $site->learn_run) {
                    $twin->forceFill(['run' => $site->learn_run, 'status' => 'pending'])->save();
                }

                return;
            }
            $page->forceFill(['url' => $final, 'url_hash' => SitePage::hash($final)]);
        }
        if (preg_match('/<meta[^>]+name=["\']robots["\'][^>]+content=["\'][^"\']*noindex/i', $response['body'])) {
            $page->forceFill(['status' => 'skipped', 'fetched_at' => now()])->save();

            return;
        }

        $data = $this->extractor->extract($response['body']);
        $text = trim($data['title']."\n".$data['description']."\n".$data['content']);
        $usable = mb_strlen($data['content']) >= 80 || $data['kind'] === 'product';
        $page->forceFill([
            'title' => $data['title'] ?: null, 'description' => $data['description'] ?: null, 'content' => $usable ? $data['content'] : null,
            'terms' => $usable ? ' '.implode(' ', array_unique(Knowledge::terms($text))).' ' : null,
            'kind' => $data['kind'], 'price' => $data['price'], 'status' => $usable ? 'ok' : 'skipped', 'fetched_at' => now(),
        ])->save();

        foreach ($data['links'] as $href) {
            $href = trim($href);
            // mailto:, tel:, javascript:, ancore: nu sunt pagini
            if ($href === '' || str_starts_with($href, '#') || (preg_match('/^[a-z][a-z0-9+.\-]*:/i', $href) && ! preg_match('#^https?://#i', $href))) {
                continue;
            }
            $this->enqueue($site, $http->absolute($response['url'], $href), $http);
        }
    }

    private function finish(Site $site): void
    {
        // paginile care nu mai apar pe site (nici în sitemap, nici în linkuri) nu mai sunt folosite
        SitePage::query()->where('site_id', $site->id)->where('run', '<', $site->learn_run)->delete();
        $ok = SitePage::query()->where('site_id', $site->id)->where('status', 'ok')->count();
        $site->forceFill($ok > 0
            ? ['learn_status' => 'done', 'learned_at' => now(), 'learn_error' => null]
            : ['learn_status' => 'failed', 'learned_at' => now(), 'learn_error' => 'Nu am găsit text pe paginile site-ului (poate fi blocat pentru roboți sau construit doar din imagini).'])->save();
        Cache::forget($this->robotsKey($site));
        $this->audit->record('site.learned', $site, ['pages' => $ok, 'run' => $site->learn_run]);
    }

    private function enqueue(Site $site, string $url, SafeFetcher $http): void
    {
        $url = $this->normalize($url);
        $host = $url !== null ? (string) parse_url($url, PHP_URL_HOST) : '';
        if ($url === null || ! $http->allowedHost($host) || preg_match(self::SKIP, (string) parse_url($url, PHP_URL_PATH).(parse_url($url, PHP_URL_QUERY) ? '?' : ''))) {
            return;
        }
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
        foreach ((array) Cache::get($this->robotsKey($site), []) as $rule) {
            if ($rule !== '' && str_starts_with($path, $rule)) {
                return;
            }
        }
        $hash = SitePage::hash($url);
        $existing = SitePage::query()->where('site_id', $site->id)->where('url_hash', $hash)->first(['id', 'run']);
        if ($existing) {
            if ($existing->run !== $site->learn_run) {
                SitePage::query()->whereKey($existing->id)->update(['run' => $site->learn_run, 'status' => 'pending']);
            }

            return;
        }
        if (SitePage::query()->where('site_id', $site->id)->where('run', $site->learn_run)->count() >= self::MAX_PAGES) {
            return;
        }
        SitePage::create(['site_id' => $site->id, 'url' => mb_substr($url, 0, 700), 'url_hash' => $hash, 'status' => 'pending', 'run' => $site->learn_run]);
    }

    /** https://Site.ro/pagina/?utm_source=x#sus → https://site.ro/pagina/ (păstrează doar parametrii care identifică pagina). */
    private function normalize(string $url): ?string
    {
        $p = parse_url(trim($url));
        if (! is_array($p) || ! in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) || empty($p['host'])) {
            return null;
        }
        $query = '';
        if (! empty($p['query'])) {
            parse_str($p['query'], $params);
            $params = array_intersect_key($params, array_flip(self::KEEP_QUERY));
            $query = $params ? '?'.http_build_query($params) : '';
        }

        return 'https://'.strtolower($p['host']).(isset($p['port']) && ! in_array((int) $p['port'], [80, 443], true) ? ':'.$p['port'] : '').($p['path'] ?? '/').$query;
    }

    /** Regulile „Disallow” pentru toți roboții (User-agent: *). @return list<string> */
    private function robots(SafeFetcher $http, string $origin): array
    {
        $r = $http->get($origin.'/robots.txt', 2);
        if (! $r || $r['status'] !== 200 || stripos($r['headers']['content-type'] ?? 'text/plain', 'html') !== false) {
            return [];
        }
        $rules = [];
        $applies = false;
        foreach (preg_split('/\R/', $r['body']) ?: [] as $line) {
            $line = trim((string) preg_replace('/#.*$/', '', $line));
            if (preg_match('/^User-agent:\s*(.+)$/i', $line, $m)) {
                $applies = trim($m[1]) === '*';
            } elseif ($applies && preg_match('/^Disallow:\s*(\S*)/i', $line, $m) && $m[1] !== '') {
                $rules[] = rtrim(explode('*', $m[1])[0], '$');
            }
        }

        return array_values(array_unique(array_filter($rules)));
    }

    /** Adresele din sitemap (inclusiv indexul de sitemap-uri: pagini, produse, articole). @return list<string> */
    private function sitemaps(SafeFetcher $http, string $origin): array
    {
        foreach ([$origin.'/sitemap_index.xml', $origin.'/sitemap.xml', $origin.'/wp-sitemap.xml'] as $candidate) {
            $urls = $this->sitemapUrls($http, $candidate, 2);
            if ($urls) {
                return array_slice(array_values(array_unique($urls)), 0, self::MAX_PAGES);
            }
        }

        return [];
    }

    /** @return list<string> */
    private function sitemapUrls(SafeFetcher $http, string $url, int $depth): array
    {
        $r = $http->get($url, 3);
        if (! $r || $r['status'] !== 200 || ! preg_match('/<(urlset|sitemapindex)/i', $r['body'])) {
            return [];
        }
        preg_match_all('/<loc>\s*(?:<!\[CDATA\[)?\s*([^<\s\]]+)/i', $r['body'], $m);
        $locs = array_map(fn ($l) => html_entity_decode($l), $m[1]);
        if (! preg_match('/<sitemapindex/i', $r['body'])) {
            return $locs;
        }
        $all = [];
        // sitemap-urile de taxonomii / autori aduc doar liste; paginile, produsele și articolele au conținutul
        usort($locs, fn ($a, $b) => (int) (bool) preg_match('/tax|tag|author|categor|user/i', $a) <=> (int) (bool) preg_match('/tax|tag|author|categor|user/i', $b));
        foreach (array_slice($locs, 0, 8) as $child) {
            if ($depth > 0 && count($all) < self::MAX_PAGES) {
                $all = array_merge($all, $this->sitemapUrls($http, $child, $depth - 1));
            }
        }

        return $all;
    }

    private function origin(string $url): string
    {
        $p = parse_url($url);

        return ($p['scheme'] ?? 'https').'://'.($p['host'] ?? '').(isset($p['port']) ? ':'.$p['port'] : '');
    }

    private function robotsKey(Site $site): string
    {
        return 'learn-robots-'.$site->id;
    }
}
