<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\PlatformRole;
use App\Enums\SubscriptionStatus;
use App\Models\Agent;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AgentService;
use App\Services\IssuedSiteKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Proba expirată oprește chatul pe site; echipa VITIM vede imediat și activează abonamentul. */
final class SubscriptionAdminTest extends TestCase
{
    use RefreshDatabase;

    private function config(IssuedSiteKey $key, string $origin): TestResponse
    {
        return $this->call('POST', '/widget/v1/config', [], [], [], ['CONTENT_TYPE' => 'text/plain;charset=UTF-8', 'HTTP_ORIGIN' => $origin], (string) json_encode(['key' => $key->publicKey]));
    }

    /** @return array{0: Organization, 1: IssuedSiteKey, 2: User} */
    private function firm(string $name, string $domain): array
    {
        $owner = User::factory()->create(['last_login_at' => now()]);
        $org = $this->makeOrganization($name, $owner);
        [$site, $key] = $this->makeSite($org, $domain);
        $site->forceFill(['widget_config' => ['enabled' => true]])->save();
        $this->tenant()->runAs($org, fn () => app(AgentService::class)->create(['name' => 'Asistent', 'site_id' => $site->id, 'status' => 'active', 'template' => 'generic']));

        return [$org, $key, $owner];
    }

    private function expire(Organization $org): void
    {
        Subscription::withoutTenancy()->where('organization_id', $org->id)->update(['status' => 'trial', 'trial_ends_at' => now()->subDay()]);
    }

    public function test_expired_trials_are_flagged_and_reactivated_in_one_click(): void
    {
        [$a, $keyA, $ownerA] = $this->firm('Podreg', 'podreg.ro');
        [$b, $keyB] = $this->firm('Dental Arena', 'dentalarena.ro');
        $this->expire($a);
        $this->expire($b);

        $this->config($keyA, 'https://podreg.ro')->assertOk()->assertJson(['enabled' => false]);
        $this->actingAs($ownerA)->get("/app/{$a->slug}/agent/".Agent::withoutTenancy()->where('organization_id', $a->id)->value('id'))
            ->assertOk()->assertSee('perioada de probă a expirat');

        $admin = $this->staff();
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('2 firme au perioada de probă expirată')->assertSee('Activează toate');
        $this->post('/admin/abonamente/activeaza-expirate')->assertRedirect()->assertSessionHas('ok', '2 abonamente au fost activate.');

        $this->config($keyA, 'https://podreg.ro')->assertJson(['enabled' => true]);
        $this->config($keyB, 'https://dentalarena.ro')->assertJson(['enabled' => true]);
        $this->assertSame(SubscriptionStatus::Active, Subscription::withoutTenancy()->where('organization_id', $a->id)->first()->status);
        $this->get('/admin')->assertDontSee('perioada de probă expirată');
    }

    public function test_one_client_subscription_is_edited_from_its_page(): void
    {
        [$org, $key, $owner] = $this->firm('Podreg', 'podreg.ro');
        $this->expire($org);
        $this->actingAs($this->staff(PlatformRole::VitimAdmin));
        $this->get("/admin/clienti/{$org->slug}")->assertOk()->assertSee('Perioada de probă a expirat');
        $this->put("/admin/clienti/{$org->slug}/abonament", ['plan' => 'pro', 'status' => 'active'])->assertRedirect()->assertSessionHas('ok');
        $sub = Subscription::withoutTenancy()->where('organization_id', $org->id)->first();
        $this->assertSame('pro', $sub->plan);
        $this->assertSame(1000, $sub->limit('conversations_per_month'));
        $this->config($key, 'https://podreg.ro')->assertJson(['enabled' => true]);

        // bulk doar pentru super admin; clientul nu își schimbă singur abonamentul
        $this->post('/admin/abonamente/activeaza-expirate')->assertForbidden();
        $this->actingAs($owner)->put("/admin/clienti/{$org->slug}/abonament", ['plan' => 'enterprise', 'status' => 'active'])->assertNotFound();
    }
}
