<?php

declare(strict_types=1);

namespace Tests\Feature\Sites;

use App\Services\SiteKeyService;
use App\Services\SiteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class SiteKeyServiceTest extends TestCase
{
    use RefreshDatabase;

    private function keys(): SiteKeyService
    {
        return app(SiteKeyService::class);
    }

    /** @return array{0:string,1:string,2:string,3:string} [timestamp, nonce, body, signature] */
    private function sign(string $secret, string $body = '{"ping":true}', ?int $time = null): array
    {
        $ts = (string) ($time ?? time());
        $nonce = Str::random(24);

        return [$ts, $nonce, $body, hash_hmac('sha256', "{$ts}.{$nonce}.{$body}", $secret)];
    }

    public function test_secret_is_stored_encrypted(): void
    {
        [, $issued] = $this->makeSite($this->makeOrganization('Firma A'), 'firma-a.ro');

        $raw = \DB::table('site_keys')->where('id', $issued->key->id)->value('secret');
        $this->assertNotSame($issued->secret, $raw);
        $this->assertStringNotContainsString($issued->secret, (string) $raw);
    }

    public function test_widget_resolves_only_from_allowed_https_origins(): void
    {
        [$site, $issued] = $this->makeSite($this->makeOrganization('Firma A'), 'https://www.Firma-A.ro/contact');
        $this->assertSame('firma-a.ro', $site->domain);

        $this->assertTrue($this->keys()->resolveForWidget($issued->publicKey, 'https://firma-a.ro')?->is($site));
        $this->assertNotNull($this->keys()->resolveForWidget($issued->publicKey, 'https://www.firma-a.ro'));
        $this->assertNull($this->keys()->resolveForWidget($issued->publicKey, 'http://firma-a.ro'));
        $this->assertNull($this->keys()->resolveForWidget($issued->publicKey, 'https://firma-a.ro.evil.com'));
        $this->assertNull($this->keys()->resolveForWidget($issued->publicKey, null));
        $this->assertNull($this->keys()->resolveForWidget('pk_inexistent', 'https://firma-a.ro'));
    }

    public function test_suspended_organization_or_disabled_site_is_not_resolved(): void
    {
        $org = $this->makeOrganization('Firma A');
        [$site, $issued] = $this->makeSite($org, 'firma-a.ro');

        $this->tenant()->runAs($org, fn () => $site->update(['status' => 'disabled']));
        $this->assertNull($this->keys()->resolveForWidget($issued->publicKey, 'https://firma-a.ro'));

        $this->tenant()->runAs($org, fn () => $site->update(['status' => 'active']));
        $org->update(['status' => 'suspended']);
        $this->assertNull($this->keys()->resolveForWidget($issued->publicKey, 'https://firma-a.ro'));
    }

    public function test_signed_request_valid_once(): void
    {
        [$site, $issued] = $this->makeSite($this->makeOrganization('Firma A'), 'firma-a.ro');
        [$ts, $nonce, $body, $sig] = $this->sign($issued->secret);

        $this->assertTrue($this->keys()->verifySignedRequest($issued->publicKey, $ts, $nonce, $body, $sig)?->is($site));
        // același nonce = replay
        $this->assertNull($this->keys()->verifySignedRequest($issued->publicKey, $ts, $nonce, $body, $sig));
    }

    public function test_signed_request_rejections(): void
    {
        [, $issued] = $this->makeSite($this->makeOrganization('Firma A'), 'firma-a.ro');

        [$ts, $nonce, $body, $sig] = $this->sign($issued->secret);
        $this->assertNull($this->keys()->verifySignedRequest($issued->publicKey, $ts, $nonce, $body.'x', $sig), 'corp modificat');

        [$ts, $nonce, $body, $sig] = $this->sign($issued->secret, time: time() - 600);
        $this->assertNull($this->keys()->verifySignedRequest($issued->publicKey, $ts, $nonce, $body, $sig), 'expirat');

        [$ts, $nonce, $body, $sig] = $this->sign('sk_alt_secret');
        $this->assertNull($this->keys()->verifySignedRequest($issued->publicKey, $ts, $nonce, $body, $sig), 'secret greșit');
    }

    public function test_key_of_one_site_cannot_sign_for_another(): void
    {
        [, $keyA] = $this->makeSite($this->makeOrganization('Firma A'), 'firma-a.ro');
        [, $keyB] = $this->makeSite($this->makeOrganization('Firma B'), 'firma-b.ro');

        [$ts, $nonce, $body, $sig] = $this->sign($keyA->secret);
        $this->assertNull($this->keys()->verifySignedRequest($keyB->publicKey, $ts, $nonce, $body, $sig));
    }

    public function test_rotation_revokes_old_key(): void
    {
        $org = $this->makeOrganization('Firma A');
        [$site, $old] = $this->makeSite($org, 'firma-a.ro');

        $new = $this->tenant()->runAs($org, fn () => $this->keys()->rotate($site));

        $this->assertNull($this->keys()->resolveForWidget($old->publicKey, 'https://firma-a.ro'));
        $this->assertNotNull($this->keys()->resolveForWidget($new->publicKey, 'https://firma-a.ro'));
        [$ts, $nonce, $body, $sig] = $this->sign($old->secret);
        $this->assertNull($this->keys()->verifySignedRequest($old->publicKey, $ts, $nonce, $body, $sig));
    }

    public function test_domain_belongs_to_a_single_organization(): void
    {
        $this->makeSite($this->makeOrganization('Firma A'), 'firma-a.ro');

        $this->expectException(ValidationException::class);
        $this->makeSite($this->makeOrganization('Firma B'), 'https://www.firma-a.ro');
    }

    public function test_plan_limit_on_sites(): void
    {
        $org = $this->makeOrganization('Firma A'); // start: 1 site
        $this->makeSite($org, 'firma-a.ro');

        $this->expectException(ValidationException::class);
        $this->makeSite($org, 'firma-a2.ro');
    }

    public function test_domain_normalization(): void
    {
        $this->assertSame('firma.ro', SiteService::normalizeHost(' HTTPS://WWW.Firma.ro/pagina?x=1 '));
        $this->assertSame('shop.firma.co.uk', SiteService::normalizeHost('shop.firma.co.uk'));
        $this->assertNull(SiteService::normalizeHost('127.0.0.1'));
        $this->assertNull(SiteService::normalizeHost('localhost'));
        $this->assertNull(SiteService::normalizeHost('nu e domeniu'));
    }
}
