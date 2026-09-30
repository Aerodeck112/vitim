<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Site;
use App\Models\SiteKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Emiterea și verificarea cheilor de site.
 *
 * - Widget (browser): public_key + Origin permis. Cheia publică nu e secret; abuzul se limitează prin rate limit și plafoane.
 * - Server-to-server (plugin): semnătură HMAC-SHA256 peste "timestamp.nonce.body", fereastră de 5 minute, nonce unic.
 *
 * Metodele resolveForWidget / verifySignedRequest rulează înainte să existe organizație curentă, deci caută explicit fără scope;
 * apelantul setează apoi TenantContext pe organizația site-ului întors.
 */
final class SiteKeyService
{
    public const SIGNATURE_WINDOW_SECONDS = 300;

    public function __construct(private readonly AuditLogger $audit) {}

    /** Se apelează în contextul organizației care deține site-ul. */
    public function issue(Site $site): IssuedSiteKey
    {
        $public = 'pk_'.Str::random(32);
        $secret = 'sk_'.Str::random(48);
        $key = SiteKey::create([
            'site_id' => $site->getKey(),
            'public_key' => $public,
            'secret' => $secret,
        ]);
        $this->audit->record('site_key.issued', $site, ['key_id' => $key->getKey()]);

        return new IssuedSiteKey($key, $public, $secret);
    }

    /** Revocă toate cheile active ale site-ului și emite una nouă. */
    public function rotate(Site $site): IssuedSiteKey
    {
        return DB::transaction(function () use ($site): IssuedSiteKey {
            $site->keys()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $this->audit->record('site_key.rotated', $site);

            return $this->issue($site);
        });
    }

    public function revoke(SiteKey $key): void
    {
        $key->update(['revoked_at' => now()]);
        $this->audit->record('site_key.revoked', $key);
    }

    /** Site-ul pentru widget, dacă cheia e activă, site-ul și firma sunt active și Origin-ul e permis. */
    public function resolveForWidget(string $publicKey, ?string $origin): ?Site
    {
        $key = $this->activeKey($publicKey);
        if ($key === null || ! $key->site->allowsOrigin($origin)) {
            return null;
        }

        return $key->site;
    }

    /** Site-ul pentru o cerere server-to-server semnată; null la orice neconcordanță. */
    public function verifySignedRequest(string $publicKey, string $timestamp, string $nonce, string $body, string $signature): ?Site
    {
        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::SIGNATURE_WINDOW_SECONDS) {
            return null;
        }
        if (strlen($nonce) < 16 || strlen($nonce) > 128) {
            return null;
        }
        $key = $this->activeKey($publicKey);
        if ($key === null) {
            return null;
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.$body, (string) $key->secret);
        if (! hash_equals($expected, $signature)) {
            return null;
        }
        // nonce-ul se consumă doar după o semnătură validă (altfel oricine ar putea „arde” nonce-uri)
        if (! Cache::add('site-nonce:'.$key->getKey().':'.$nonce, 1, self::SIGNATURE_WINDOW_SECONDS * 2)) {
            return null;
        }
        SiteKey::withoutTenancy()->whereKey($key->getKey())->update(['last_used_at' => now()]);

        return $key->site;
    }

    private function activeKey(string $publicKey): ?SiteKey
    {
        if (! str_starts_with($publicKey, 'pk_')) {
            return null;
        }
        $key = SiteKey::withoutTenancy()
            ->where('public_key', $publicKey)
            ->whereNull('revoked_at')
            ->first();
        if ($key === null) {
            return null;
        }
        $site = Site::withoutTenancy()->with('organization')->find($key->site_id);
        if ($site === null || ! $site->isActive() || ! $site->organization->isActive()) {
            return null;
        }
        $key->setRelation('site', $site);

        return $key;
    }
}
