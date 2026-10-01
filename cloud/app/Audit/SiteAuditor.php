<?php

declare(strict_types=1);

namespace App\Audit;

use App\Models\Site;
use Illuminate\Support\Str;

/**
 * Auditul extern al unui site (ca un vizitator / ca Google): SEO, viteză, securitate, legal (România).
 * Funcționează pentru orice site, cu sau fără plugin. Citește prima pagină, robots.txt, sitemap-ul și până la 12 pagini din el.
 */
final class SiteAuditor
{
    private const MAX_PAGES = 12;

    /** Urmăritori care, conform Legii 506/2004 / GDPR, cer consimțământ înainte de încărcare. */
    private const TRACKERS = ['googletagmanager.com/gtag', 'google-analytics.com', 'googletagmanager.com/gtm.js', 'connect.facebook.net', 'fbq(', 'static.hotjar.com', 'clarity.ms', 'snap.licdn.com', 'analytics.tiktok.com'];

    /** Semnături ale bannerelor / platformelor de consimțământ uzuale. */
    private const CONSENT = ['cookiebot', 'cookieyes', 'cky-consent', 'complianz', 'cmplz', 'cookie-law-info', 'borlabs', 'iubenda', 'onetrust', 'termly', 'cookie-notice', 'moove_gdpr', 'gdpr-cookie', 'cookieconsent', 'consent-banner', 'cookie-consent', 'cookie_consent', 'data-cookieconsent', "gtag('consent'", 'gtag("consent"', 'usercentrics', 'axeptio', 'didomi', 'quantcast', 'klaro', 'tarteaucitron', 'data-consent', 'cookie-banner', 'cookiebanner'];

    /** @var list<array{code: string, category: string, severity: string, title: string, details: ?string, fix: ?string}> */
    private array $findings = [];

    private SafeFetcher $http;

    /** @return list<array{code: string, category: string, severity: string, title: string, details: ?string, fix: ?string}> */
    public function audit(Site $site): array
    {
        $this->findings = [];
        $this->http = new SafeFetcher($site->domain);
        $base = $this->baseUrl($site);
        $home = $this->http->get($base);
        if ($home === null || $home['status'] >= 500) {
            $this->add('sec.ssl_invalid', 'critical', 'Site-ul nu răspunde corect pe https://'.$site->domain, $home ? 'HTTP '.$home['status'] : 'Conexiune eșuată sau certificat invalid.');

            return $this->findings;
        }

        $this->transport($site, $home);
        $this->security($home);
        $robots = $this->robots($home['url']);
        $pages = $this->pages($home, $robots);
        $this->seo($home, $pages);
        $this->legal($home, $pages);

        return $this->findings;
    }

    private function baseUrl(Site $site): string
    {
        $url = (string) ($site->health['site_url'] ?? '');
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $url !== '' && in_array($host, [$site->domain, 'www.'.$site->domain], true) ? $url : 'https://'.$site->domain.'/';
    }

    // ---------- transport: https, redirecturi, viteză ----------

    /** @param array{status: int, url: string, headers: array<string, string>, body: string, ms: int, redirects: list<string>} $home */
    private function transport(Site $site, array $home): void
    {
        $scheme = parse_url($home['url'], PHP_URL_SCHEME);
        if ($scheme !== 'https') {
            $this->add('sec.ssl_invalid', 'critical', 'Site-ul nu se deschide pe HTTPS');
        } else {
            $http = $this->http->get('http://'.parse_url($home['url'], PHP_URL_HOST).'/', 5, false);
            if ($http && parse_url($http['url'], PHP_URL_SCHEME) !== 'https') {
                $this->add('seo.https_redirect', 'warning', 'Versiunea http:// nu redirecționează spre https://');
            }
            $this->certificate((string) parse_url($home['url'], PHP_URL_HOST));
        }
        $host = (string) parse_url($home['url'], PHP_URL_HOST);
        $other = str_starts_with($host, 'www.') ? substr($host, 4) : 'www.'.$host;
        $alt = $this->http->get($scheme.'://'.$other.'/', 0, false);
        if ($alt && $alt['status'] === 200) {
            $this->add('seo.www_duplicate', 'warning', 'Site-ul răspunde pe '.$host.' și pe '.$other.' fără redirect');
        }

        if ($home['ms'] > 3000) {
            $this->add('perf.slow_response', 'critical', 'Prima pagină se încarcă foarte greu ('.round($home['ms'] / 1000, 1).' s)');
        } elseif ($home['ms'] > 1500) {
            $this->add('perf.slow_response', 'warning', 'Prima pagină răspunde lent ('.round($home['ms'] / 1000, 1).' s)');
        }
        if (strlen($home['body']) > 600_000) {
            $this->add('perf.page_size', 'info', 'Codul primei pagini are '.round(strlen($home['body']) / 1024).' KB');
        }
        $encoding = $home['headers']['content-encoding'] ?? $home['headers']['x-encoded-content-encoding'] ?? '';
        if ($encoding === '' && strlen($home['body']) > 20_000) {
            $this->add('perf.no_compression', 'info', 'Paginile nu sunt comprimate (gzip / brotli)');
        }
    }

    private function certificate(string $host): void
    {
        if (! app()->isProduction()) {
            return;
        }
        $context = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => false, 'verify_peer_name' => false, 'SNI_enabled' => true, 'peer_name' => $host]]);
        $client = @stream_socket_client('ssl://'.$host.':443', $errno, $error, 8, STREAM_CLIENT_CONNECT, $context);
        if (! $client) {
            return;
        }
        $cert = openssl_x509_parse(stream_context_get_params($client)['options']['ssl']['peer_certificate'] ?? '');
        fclose($client);
        $days = isset($cert['validTo_time_t']) ? (int) floor(($cert['validTo_time_t'] - time()) / 86400) : null;
        if ($days !== null && $days < 0) {
            $this->add('sec.ssl_invalid', 'critical', 'Certificatul HTTPS a expirat');
        } elseif ($days !== null && $days < 14) {
            $this->add('sec.ssl_expiring', 'warning', "Certificatul HTTPS expiră în {$days} zile");
        }
    }

    // ---------- securitate (văzută din afară) ----------

    /** @param array{status: int, url: string, headers: array<string, string>, body: string, ms: int, redirects: list<string>} $home */
    private function security(array $home): void
    {
        $h = $home['headers'];
        $missing = array_filter([
            'X-Content-Type-Options' => ! isset($h['x-content-type-options']),
            'X-Frame-Options' => ! isset($h['x-frame-options']) && ! str_contains($h['content-security-policy'] ?? '', 'frame-ancestors'),
            'Referrer-Policy' => ! isset($h['referrer-policy']),
        ]);
        if ($missing) {
            $this->add('sec.headers_missing', 'info', 'Lipsesc antete de securitate: '.implode(', ', array_keys($missing)));
        }
        if (parse_url($home['url'], PHP_URL_SCHEME) === 'https' && ! isset($h['strict-transport-security'])) {
            $this->add('sec.hsts_missing', 'info', 'HSTS nu este activ');
        }
        if (preg_match('/PHP\/[\d.]+/i', $h['x-powered-by'] ?? '', $m)) {
            $this->add('sec.server_leak', 'info', 'Serverul își afișează versiunea: '.$m[0]);
        }

        $origin = $this->origin($home['url']);
        $exposed = [];
        foreach ([
            '/.env' => '/^\s*(APP_KEY|DB_PASSWORD|DB_HOST|APP_ENV)\s*=/m',
            '/.git/HEAD' => '/^ref: refs\//',
            '/wp-config.php.bak' => '/DB_PASSWORD/',
            '/wp-config.php.old' => '/DB_PASSWORD/',
            '/wp-config.php~' => '/DB_PASSWORD/',
            '/wp-config.bak' => '/DB_PASSWORD/',
            '/debug.log' => '/PHP (Fatal|Warning|Notice|Deprecated)/',
            '/wp-content/debug.log' => '/PHP (Fatal|Warning|Notice|Deprecated)/',
            '/phpinfo.php' => '/phpinfo\(\)|PHP Version/',
            '/info.php' => '/phpinfo\(\)|PHP Version/',
        ] as $path => $signature) {
            $r = $this->http->get($origin.$path, 0);
            if ($r && $r['status'] === 200 && preg_match($signature, substr($r['body'], 0, 50_000))) {
                $exposed[] = $origin.$path;
            }
        }
        if ($exposed) {
            $this->add('sec.exposed_files', 'critical', count($exposed).' fișiere sensibile se pot descărca public', implode("\n", $exposed));
        }
        $uploads = $this->http->get($origin.'/wp-content/uploads/', 0);
        if ($uploads && $uploads['status'] === 200 && str_contains($uploads['body'], 'Index of /')) {
            $this->add('sec.dir_listing', 'warning', 'Conținutul folderelor se poate lista în browser', $origin.'/wp-content/uploads/');
        }
    }

    // ---------- robots.txt și pagini ----------

    /** @return array{body: ?string, sitemaps: list<string>} */
    private function robots(string $homeUrl): array
    {
        $r = $this->http->get($this->origin($homeUrl).'/robots.txt', 2);
        if (! $r || $r['status'] !== 200 || stripos($r['headers']['content-type'] ?? 'text/plain', 'html') !== false) {
            $this->add('seo.robots_missing', 'info', 'Lipsește fișierul robots.txt');

            return ['body' => null, 'sitemaps' => []];
        }
        $body = $r['body'];
        if (preg_match('/User-agent:\s*\*\s*(?:\R(?!User-agent)[^\n]*)*?\R\s*Disallow:\s*\/\s*(\R|$)/i', $body)) {
            $this->add('seo.robots_block_all', 'critical', 'robots.txt blochează tot site-ul pentru Google');
        }
        preg_match_all('/^\s*Sitemap:\s*(\S+)/im', $body, $m);

        return ['body' => $body, 'sitemaps' => $m[1]];
    }

    /**
     * @param  array{status: int, url: string, headers: array<string, string>, body: string, ms: int, redirects: list<string>}  $home
     * @param  array{body: ?string, sitemaps: list<string>}  $robots
     * @return array<string, array{status: int, url: string, headers: array<string, string>, body: string, ms: int, redirects: list<string>}>
     */
    private function pages(array $home, array $robots): array
    {
        $origin = $this->origin($home['url']);
        $urls = [];
        $sitemapFound = false;
        foreach (array_unique([...$robots['sitemaps'], $origin.'/sitemap.xml', $origin.'/sitemap_index.xml', $origin.'/wp-sitemap.xml']) as $sitemap) {
            if (count($urls) >= self::MAX_PAGES || ! $this->sameSite($sitemap)) {
                continue;
            }
            $locs = $this->sitemapUrls($sitemap, 2);
            if ($locs !== null) {
                $sitemapFound = true;
                $urls = array_merge($urls, $locs);
            }
            if ($sitemapFound) {
                break;
            }
        }
        if (! $sitemapFound) {
            $this->add('seo.sitemap_missing', 'warning', 'Nu am găsit un sitemap XML');
            // fără sitemap: linkurile interne din prima pagină
            preg_match_all('/<a\s[^>]*href=["\']([^"\'#]+)["\']/i', $home['body'], $m);
            foreach ($m[1] as $href) {
                $abs = $this->http->absolute($home['url'], html_entity_decode($href));
                if ($this->sameSite($abs) && ! preg_match('/\.(jpe?g|png|gif|webp|pdf|zip|svg)$/i', $abs)) {
                    $urls[] = strtok($abs, '?');
                }
            }
        }

        $pages = [$home['url'] => $home];
        $broken = [];
        foreach (array_slice(array_values(array_unique(array_filter($urls, fn ($u) => rtrim($u, '/') !== rtrim($home['url'], '/')))), 0, self::MAX_PAGES - 1) as $url) {
            $page = $this->http->get($url, 3);
            if ($page === null) {
                continue;
            }
            if ($page['status'] >= 400) {
                $broken[] = $url.' → '.$page['status'];

                continue;
            }
            if (stripos($page['headers']['content-type'] ?? 'text/html', 'html') !== false) {
                $pages[$page['url']] = $page;
            }
        }
        if ($broken && $sitemapFound) {
            $this->add('seo.broken_pages', 'warning', count($broken).' pagini din sitemap dau eroare', implode("\n", $broken));
        }

        return $pages;
    }

    /** @return list<string>|null */
    private function sitemapUrls(string $url, int $depth): ?array
    {
        $r = $this->http->get($url, 3);
        if (! $r || $r['status'] !== 200 || ! str_contains($r['body'], '<')) {
            return null;
        }
        preg_match_all('/<loc>\s*([^<\s]+)\s*<\/loc>/i', $r['body'], $m);
        $locs = array_map(fn ($l) => html_entity_decode($l), $m[1]);
        if ($locs === [] && ! preg_match('/<(urlset|sitemapindex)/i', $r['body'])) {
            return null;
        }
        if (preg_match('/<sitemapindex/i', $r['body'])) {
            $all = [];
            foreach (array_slice($locs, 0, 3) as $child) {
                if ($depth > 0 && $this->sameSite($child)) {
                    $all = array_merge($all, $this->sitemapUrls($child, $depth - 1) ?? []);
                }
            }

            return $all;
        }

        return array_values(array_filter($locs, fn ($l) => $this->sameSite($l)));
    }

    // ---------- SEO pe pagini ----------

    /** @param array<string, array{status: int, url: string, headers: array<string, string>, body: string}> $pages */
    private function seo(array $home, array $pages): void
    {
        $html = $home['body'];
        if (preg_match('/<meta[^>]+name=["\']robots["\'][^>]*content=["\'][^"\']*noindex/i', $html) || str_contains(strtolower($home['headers']['x-robots-tag'] ?? ''), 'noindex')) {
            $this->add('seo.noindex_home', 'critical', 'Prima pagină cere motoarelor de căutare să nu fie indexată');
        }
        if (! preg_match('/<meta[^>]+name=["\']viewport["\']/i', $html)) {
            $this->add('seo.viewport_missing', 'critical', 'Site-ul nu e adaptat pentru telefon (lipsește meta viewport)');
        }
        if (! preg_match('/<html[^>]+lang=["\'][a-z]{2}/i', $html)) {
            $this->add('seo.lang_missing', 'info', 'Limba paginii nu e declarată');
        }
        if (! preg_match('/<meta[^>]+property=["\']og:(title|image)["\']/i', $html)) {
            $this->add('seo.og_missing', 'info', 'Lipsesc etichetele pentru distribuire (Open Graph)');
        }
        if (! preg_match('/"@type"\s*:\s*"?\[?\s*"?(LocalBusiness|Organization|AutoRepair|Store|Dentist|MedicalClinic|ProfessionalService|HomeAndConstructionBusiness|Restaurant|[A-Za-z]+Business)/i', $html)) {
            $this->add('seo.schema_missing', 'info', 'Lipsesc datele structurate pentru firmă (LocalBusiness / Organization)');
        }

        $noTitle = $badTitle = $noDescription = $noH1 = $multiH1 = $noCanonical = $titles = [];
        $images = $imagesNoAlt = 0;
        foreach ($pages as $url => $page) {
            $body = $page['body'];
            $title = preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $m) ? trim(html_entity_decode(strip_tags($m[1]))) : '';
            if ($title === '') {
                $noTitle[] = $url;
            } else {
                $titles[$title][] = $url;
                $length = mb_strlen($title);
                if ($length > 65 || $length < 15) {
                    $badTitle[] = "{$url} ({$length} caractere)";
                }
            }
            if (! preg_match('/<meta[^>]+name=["\']description["\'][^>]*content=["\']\s*[^"\'\s]/i', $body) && ! preg_match('/<meta[^>]+content=["\']\s*[^"\'\s][^>]*name=["\']description["\']/i', $body)) {
                $noDescription[] = $url;
            }
            $h1 = preg_match_all('/<h1[\s>]/i', $body);
            if ($h1 === 0) {
                $noH1[] = $url;
            } elseif ($h1 > 1) {
                $multiH1[] = $url;
            }
            if (! preg_match('/<link[^>]+rel=["\']canonical["\']/i', $body)) {
                $noCanonical[] = $url;
            }
            preg_match_all('/<img\s[^>]*>/i', $body, $imgs);
            foreach ($imgs[0] as $img) {
                $images++;
                // alt="" e corect pentru imaginile decorative; problema e doar lipsa atributului
                if (! preg_match('/\salt\s*=/i', $img)) {
                    $imagesNoAlt++;
                }
            }
        }
        $list = fn (array $urls) => implode("\n", array_slice($urls, 0, 15));
        if ($noTitle) {
            $this->add('seo.title_missing', 'critical', count($noTitle).' pagini fără titlu', $list($noTitle));
        }
        if ($badTitle) {
            $this->add('seo.title_length', 'info', count($badTitle).' pagini cu titlu prea lung sau prea scurt', $list($badTitle));
        }
        $duplicates = array_filter($titles, fn ($urls) => count($urls) > 1);
        if ($duplicates) {
            $this->add('seo.title_duplicate', 'warning', count($duplicates).' titluri folosite pe mai multe pagini', $list(array_map(fn ($t, $u) => "„{$t}”: ".implode(', ', $u), array_keys($duplicates), $duplicates)));
        }
        if ($noDescription) {
            $this->add('seo.description_missing', 'warning', count($noDescription).' pagini fără descriere (meta description)', $list($noDescription));
        }
        if ($noH1) {
            $this->add('seo.h1_missing', 'warning', count($noH1).' pagini fără titlu principal (H1)', $list($noH1));
        }
        if ($multiH1) {
            $this->add('seo.h1_multiple', 'info', count($multiH1).' pagini cu mai multe H1', $list($multiH1));
        }
        if ($noCanonical) {
            $this->add('seo.canonical_missing', 'info', count($noCanonical).' pagini fără canonical', $list($noCanonical));
        }
        if ($imagesNoAlt > 0) {
            $this->add('seo.images_alt', $imagesNoAlt > $images / 2 ? 'warning' : 'info', "{$imagesNoAlt} din {$images} imagini fără text alternativ");
        }
    }

    // ---------- legal (România) ----------

    /** @param array<string, array{body: string}> $pages */
    private function legal(array $home, array $pages): void
    {
        $html = $home['body'];
        $plain = Str::lower(Str::ascii(html_entity_decode(strip_tags($html))));
        $raw = Str::lower($html);
        $links = $this->links($html);
        $hasLink = fn (string $pattern) => (bool) array_filter($links, fn ($l) => preg_match($pattern, Str::lower(Str::ascii($l['text'].' '.$l['href']))));

        if (! $hasLink('/confidential|privacy|gdpr|protectia[- ]datelor|date[- ]personale/')) {
            $this->add('legal.privacy_policy', 'critical', 'Lipsește linkul spre Politica de confidențialitate');
        }
        if (! $hasLink('/cookie/')) {
            $this->add('legal.cookie_policy', 'warning', 'Lipsește linkul spre Politica de cookie-uri');
        }
        $trackers = array_values(array_filter(self::TRACKERS, fn ($t) => str_contains($raw, strtolower($t))));
        $consent = (bool) array_filter(self::CONSENT, fn ($c) => str_contains($raw, strtolower($c)));
        if ($trackers && ! $consent) {
            $this->add('legal.cookie_consent', 'critical', 'Scripturi de urmărire fără banner de consimțământ', 'Găsite: '.implode(', ', $trackers));
        } elseif (! $consent && ! str_contains($plain, 'cookie')) {
            $this->add('legal.cookie_banner', 'info', 'Nu am găsit un banner de cookie-uri');
        }
        if (! $hasLink('/termen|conditii|terms/')) {
            $this->add('legal.terms', 'warning', 'Lipsește linkul spre Termeni și condiții');
        }
        $shop = (bool) preg_match('/woocommerce|add[_-]to[_-]cart|adauga in cos|cos de cumparaturi|checkout/', $raw.' '.$plain);
        if (! str_contains($raw, 'anpc.ro')) {
            $this->add('legal.anpc', $shop ? 'critical' : 'warning', 'Lipsește linkul ANPC – SAL'.($shop ? ' (magazin online)' : ''));
        }
        if (! str_contains($raw, 'ec.europa.eu/consumers/odr')) {
            $this->add('legal.sol', $shop ? 'critical' : 'warning', 'Lipsește linkul spre platforma europeană SOL'.($shop ? ' (magazin online)' : ''));
        }
        $all = $plain.' '.implode(' ', array_map(fn ($p) => Str::lower(Str::ascii(strip_tags($p['body']))), array_slice($pages, 1, 3, true)));
        $cui = (bool) preg_match('/\b(cui|cif|cod fiscal|c\.u\.i\.|c\.i\.f\.)\s*[:.]?\s*(ro)?\s*\d{2,10}\b|\bro\s?\d{6,10}\b/', $all);
        $regCom = (bool) preg_match('/\bj\s?\d{1,2}\s*\/\s*\d+\s*\/\s*\d{4}\b|\bj\d{8,}\b|registrul comertului/', $all);
        if (! $cui || ! $regCom) {
            $this->add('legal.company_id', 'warning', 'Nu am găsit datele de identificare ale firmei'.(! $cui && ! $regCom ? ' (CUI și Nr. Reg. Com.)' : (! $cui ? ' (CUI)' : ' (Nr. Reg. Com.)')));
        }
        if (! preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/', $all) && ! str_contains($raw, 'mailto:') && ! str_contains($raw, 'tel:') && ! preg_match('/\b0[237]\d{2}[\s.]?\d{3}[\s.]?\d{3}\b/', $all)) {
            $this->add('legal.contact', 'info', 'Nu am găsit email sau telefon pe prima pagină');
        }
        foreach ($pages as $url => $page) {
            if (preg_match('/<form[\s\S]*?type=["\']email["\'][\s\S]*?<\/form>/i', $page['body'], $form)
                && ! preg_match('/type=["\']checkbox["\']|confidential|gdpr|privacy|date(lor)? personale/i', Str::ascii($form[0]))) {
                $this->add('legal.form_consent', 'info', 'Formular fără informare / acord GDPR', $url);
                break;
            }
        }
    }

    /** @return list<array{href: string, text: string}> */
    private function links(string $html): array
    {
        preg_match_all('/<a\s[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is', $html, $m, PREG_SET_ORDER);

        return array_map(fn ($x) => ['href' => html_entity_decode($x[1]), 'text' => trim(html_entity_decode(strip_tags($x[2])))], $m);
    }

    private function origin(string $url): string
    {
        $p = parse_url($url);

        return ($p['scheme'] ?? 'https').'://'.($p['host'] ?? '').(isset($p['port']) ? ':'.$p['port'] : '');
    }

    private function sameSite(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $this->http->allowedHost($host);
    }

    private function add(string $code, string $severity, string $title, ?string $details = null, ?string $fix = null): void
    {
        foreach ($this->findings as $f) {
            if ($f['code'] === $code) {
                return; // prima constatare (cea mai gravă, în ordinea verificărilor) rămâne
            }
        }
        $this->findings[] = ['code' => $code, 'category' => Guidance::category($code), 'severity' => $severity, 'title' => $title, 'details' => $details, 'fix' => $fix];
    }
}
