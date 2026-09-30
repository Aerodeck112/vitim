<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Site;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SiteService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SiteKeyService $keys,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Site nou pentru organizația curentă, cu prima cheie. Respectă limita planului.
     *
     * @param  list<string>  $extraHosts
     * @return array{0: Site, 1: IssuedSiteKey}
     */
    public function create(string $domain, string $platform = 'generic', array $extraHosts = []): array
    {
        $domain = self::normalizeHost($domain);
        if ($domain === null) {
            throw ValidationException::withMessages(['domain' => 'Domeniu invalid.']);
        }
        if (Site::withoutTenancy()->where('domain', $domain)->exists()) {
            throw ValidationException::withMessages(['domain' => 'Domeniul este deja înregistrat.']);
        }
        $limit = $this->context->organization()->subscription?->limit('sites');
        if ($limit !== null && Site::query()->count() >= $limit) {
            throw ValidationException::withMessages(['domain' => 'Planul curent nu permite mai multe site-uri.']);
        }
        $hosts = array_values(array_filter(array_map(self::normalizeHost(...), $extraHosts)));

        return DB::transaction(function () use ($domain, $platform, $hosts): array {
            $site = Site::create([
                'domain' => $domain,
                'allowed_origins' => $hosts,
                'platform' => in_array($platform, ['wordpress', 'generic'], true) ? $platform : 'generic',
            ]);
            $this->audit->record('site.created', $site, ['domain' => $domain]);

            return [$site, $this->keys->issue($site)];
        });
    }

    /** „https://www.Firma.ro/pagina” → „firma.ro”; null pentru orice nu arată ca un domeniu public. */
    public static function normalizeHost(string $input): ?string
    {
        $input = trim(strtolower($input));
        if ($input === '') {
            return null;
        }
        $host = parse_url(str_contains($input, '://') ? $input : 'https://'.$input, PHP_URL_HOST);
        if (! is_string($host)) {
            return null;
        }
        $host = preg_replace('/^www\./', '', rtrim($host, '.'));
        if (! is_string($host) || filter_var($host, FILTER_VALIDATE_IP) || ! preg_match('/^(?=.{4,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host)) {
            return null;
        }

        return $host;
    }
}
