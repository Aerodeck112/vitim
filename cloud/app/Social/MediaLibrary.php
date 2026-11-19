<?php

declare(strict_types=1);

namespace App\Social;

use App\Audit\SafeFetcher;
use App\Models\Product;
use App\Models\Site;
use App\Models\SitePage;
use App\Models\SocialAsset;
use App\Tenancy\TenantContext;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Biblioteca de materiale a firmei pentru postări. Pozele vin doar din surse ale firmei: site-ul ei, pagina ei de
 * Facebook / Instagram conectată sau fișiere încărcate de echipă. Se descarcă o singură dată și se țin în platformă.
 */
final class MediaLibrary
{
    public const MAX_ASSETS = 150;

    public const MAX_BYTES = 8_000_000;

    private const MIN_SIDE = 500;

    private const TYPES = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];

    /** Gazdele de pe care Meta servește pozele paginilor (doar https). */
    private const META_HOSTS = ['fbcdn.net', 'cdninstagram.com', 'fbsbx.com'];

    public function __construct(private readonly TenantContext $context) {}

    /** Strânge pozele din paginile și produsele site-ului (doar de pe domeniul site-ului). Întoarce câte a adăugat. */
    public function collectFromSite(Site $site, int $limit = 40): int
    {
        $fetcher = new SafeFetcher($site->domain, 160);
        $candidates = [];
        foreach (Product::query()->where('site_id', $site->id)->whereNotNull('image')->orderByDesc('id')->limit(30)->get(['name', 'image']) as $product) {
            $candidates[(string) $product->image] = (string) $product->name;
        }
        foreach (SitePage::query()->where('site_id', $site->id)->where('status', 'ok')->where('excluded', false)->orderBy('id')->limit(25)->get(['url', 'title']) as $page) {
            $html = $fetcher->get($page->url);
            if ($html === null || $html['status'] !== 200) {
                continue;
            }
            foreach (self::imagesIn($html['body'], $fetcher, $html['url']) as $src => $alt) {
                $candidates[$src] ??= $alt !== '' ? $alt : (string) $page->title;
            }
        }
        $added = 0;
        foreach ($candidates as $url => $title) {
            if ($added >= $limit || $this->full()) {
                break;
            }
            if ($this->known($url) || ! $fetcher->allowedHost((string) parse_url($url, PHP_URL_HOST))) {
                continue;
            }
            $image = $fetcher->get($url);
            if ($image !== null && $image['status'] === 200 && $this->keep('site', $url, $image['body'], $title) !== null) {
                $added++;
            }
        }

        return $added;
    }

    /** O poză de pe gazdele Meta (adresa vine din răspunsul interfeței oficiale, pentru pagina conectată). */
    public function fromMeta(string $url, ?string $title): ?SocialAsset
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowed = parse_url($url, PHP_URL_SCHEME) === 'https' && array_filter(self::META_HOSTS, fn (string $h) => $host === $h || str_ends_with($host, '.'.$h)) !== [];
        // adresa fără parametrii de semnătură, care se schimbă la fiecare citire
        $stable = strtok($url, '?') ?: $url;
        if (! $allowed || $this->full() || $this->known($stable)) {
            return null;
        }
        try {
            $response = Http::timeout(15)->connectTimeout(6)->withOptions(['allow_redirects' => false])->get($url);
        } catch (ConnectionException) {
            return null;
        }

        return $response->successful() ? $this->keep('meta', $stable, $response->body(), $title) : null;
    }

    public function upload(string $bytes, string $name): ?SocialAsset
    {
        return $this->full() ? null : $this->keep('upload', 'upload:'.sha1($bytes), $bytes, pathinfo($name, PATHINFO_FILENAME));
    }

    public function delete(SocialAsset $asset): void
    {
        Storage::disk('local')->delete($asset->path);
        $asset->delete();
    }

    public function full(): bool
    {
        return SocialAsset::query()->count() >= self::MAX_ASSETS;
    }

    private function known(string $origin): bool
    {
        return SocialAsset::query()->where('origin_hash', sha1($origin))->exists();
    }

    private function keep(string $source, string $origin, string $bytes, ?string $title): ?SocialAsset
    {
        $size = strlen($bytes);
        // 2 MB e limita de citire a site-urilor: un fișier exact atât e trunchiat
        if ($size < 5_000 || $size > self::MAX_BYTES || ($source === 'site' && $size >= 2_000_000)) {
            return null;
        }
        $info = @getimagesizefromstring($bytes);
        if ($info === false || ! isset(self::TYPES[$info[2]]) || min($info[0], $info[1]) < self::MIN_SIDE) {
            return null;
        }
        $path = 'social/'.$this->context->id().'/'.sha1($origin).'.'.self::TYPES[$info[2]];
        Storage::disk('local')->put($path, $bytes);

        return SocialAsset::create(['source' => $source, 'origin' => Str::limit($origin, 990, ''), 'origin_hash' => sha1($origin), 'path' => $path,
            'title' => $title !== null && trim($title) !== '' ? Str::limit(trim($title), 290, '') : null, 'width' => $info[0], 'height' => $info[1], 'bytes' => $size]);
    }

    /**
     * Fotografiile din conținutul unei pagini, cu descrierea lor (textul alternativ). Fără logo-uri, iconițe și
     * cartonașele generate pentru distribuire (og:image), care sunt text pe fundal, nu fotografii.
     *
     * @return array<string, string> adresă => descriere
     */
    private static function imagesIn(string $html, SafeFetcher $fetcher, string $base): array
    {
        $found = [];
        preg_match_all('/<img\b[^>]*>/i', $html, $all);
        foreach ($all[0] as $tag) {
            if (preg_match('/logo|icon|sprite|avatar|placeholder|\.svg|\.gif|data:/i', $tag) || ! preg_match('/\bsrc=["\']([^"\']+)["\']/i', $tag, $src)) {
                continue;
            }
            $alt = preg_match('/\balt=["\']([^"\']*)["\']/i', $tag, $m) ? trim(html_entity_decode($m[1])) : '';
            $found[$fetcher->absolute($base, html_entity_decode($src[1]))] ??= $alt;
        }

        return array_slice($found, 0, 8, true);
    }
}
