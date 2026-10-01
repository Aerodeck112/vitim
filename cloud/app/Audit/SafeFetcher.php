<?php

declare(strict_types=1);

namespace App\Audit;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Cereri HTTP către site-urile clienților pentru audit, cu protecție SSRF: doar http(s) pe domeniul site-ului
 * (cu sau fără www), doar adrese IP publice, redirecturi urmate manual și verificate, răspunsuri limitate ca mărime.
 */
final class SafeFetcher
{
    private const MAX_BYTES = 2_000_000;

    private int $requests = 0;

    public function __construct(private readonly string $domain, private readonly int $budget = 40) {}

    public function allowedHost(string $host): bool
    {
        $host = strtolower(rtrim($host, '.'));

        return $host === $this->domain || $host === 'www.'.$this->domain;
    }

    /** @return array{status: int, url: string, headers: array<string, string>, body: string, ms: int, redirects: list<string>}|null */
    public function get(string $url, int $maxRedirects = 5, bool $withBody = true): ?array
    {
        $redirects = [];
        for ($i = 0; $i <= $maxRedirects; $i++) {
            if (! $this->safe($url) || $this->requests >= $this->budget) {
                return null;
            }
            $this->requests++;
            $started = hrtime(true);
            try {
                $response = Http::withOptions(['allow_redirects' => false, 'stream' => false])
                    ->withHeaders(['User-Agent' => 'VITIM-Audit/1.0 (+https://vitim.ro)', 'Accept' => 'text/html,application/xhtml+xml,*/*'])
                    ->timeout(12)->connectTimeout(6)->get($url);
            } catch (ConnectionException) {
                return null;
            }
            $ms = (int) ((hrtime(true) - $started) / 1_000_000);
            $headers = array_change_key_case(array_map(fn ($v) => is_array($v) ? implode(', ', $v) : (string) $v, $response->headers()));
            if ($response->status() >= 300 && $response->status() < 400 && isset($headers['location'])) {
                $redirects[] = $url;
                $url = $this->absolute($url, $headers['location']);

                continue;
            }

            return [
                'status' => $response->status(),
                'url' => $url,
                'headers' => $headers,
                'body' => $withBody ? substr($response->body(), 0, self::MAX_BYTES) : '',
                'ms' => $ms,
                'redirects' => $redirects,
            ];
        }

        return null;
    }

    public function absolute(string $base, string $relative): string
    {
        if (preg_match('#^https?://#i', $relative)) {
            return $relative;
        }
        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');
        if (str_starts_with($relative, '//')) {
            return ($parts['scheme'] ?? 'https').':'.$relative;
        }
        if (str_starts_with($relative, '/')) {
            return $origin.$relative;
        }
        $path = $parts['path'] ?? '/';

        return $origin.rtrim(substr($path, 0, (int) strrpos($path, '/') + 1), '/').'/'.$relative;
    }

    private function safe(string $url): bool
    {
        $parts = parse_url($url);
        if (! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host']) || ! $this->allowedHost($parts['host'])) {
            return false;
        }
        // în producție: doar porturile web standard și doar IP-uri publice (în teste / local, site-urile de test sunt pe 127.0.0.1)
        if (! app()->isProduction()) {
            return true;
        }
        if (isset($parts['port']) && ! in_array((int) $parts['port'], [80, 443], true)) {
            return false;
        }
        $ips = gethostbynamel($parts['host']) ?: [];
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return $ips !== [];
    }
}
