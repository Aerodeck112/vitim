<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Organization;
use App\Models\Site;
use App\Models\SiteIssue;
use App\Models\SocialPost;
use App\Models\Subscription;
use App\Models\User;
use App\Office\Office;
use App\Services\AgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Biroul: fiecare agent cu starea lui reală, pe firmă și peste toți clienții. */
final class OfficeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Organization, 1: User, 2: Site} */
    private function firm(string $name, string $domain): array
    {
        $owner = User::factory()->create(['last_login_at' => now()]);
        $org = $this->makeOrganization($name, $owner);
        Subscription::withoutTenancy()->where('organization_id', $org->id)->update(['status' => 'active', 'trial_ends_at' => null]);
        [$site] = $this->makeSite($org, $domain);
        $site->forceFill(['platform' => 'wordpress', 'last_seen_at' => now(), 'last_audit_at' => now()])->save();
        $this->tenant()->runAs($org, fn () => app(AgentService::class)->create(['name' => 'Asistent', 'site_id' => $site->id, 'status' => 'active', 'template' => 'generic']));

        return [$org, $owner, $site->fresh()];
    }

    /** @return array<string, string> */
    private function states(Organization $org): array
    {
        return array_column($this->tenant()->runAs($org, fn () => app(Office::class)->desks()), 'state', 'key');
    }

    public function test_each_agent_shows_its_real_state(): void
    {
        [$org, $owner, $site] = $this->firm('Podreg', 'podreg.ro');
        $this->assertSame(['chat' => 'working', 'knowledge' => 'idle', 'seo' => 'working', 'security' => 'working', 'backup' => 'off', 'social' => 'off', 'marketing' => 'idle'], $this->states($org));

        $this->tenant()->runAs($org, function () use ($site): void {
            SiteIssue::create(['site_id' => $site->id, 'code' => 'plugin_update:x/x.php', 'category' => 'updates', 'source' => 'plugin', 'severity' => 'warning', 'title' => 'Plugin de actualizat', 'status' => 'open', 'first_seen_at' => now(), 'last_seen_at' => now()]);
            SiteIssue::create(['site_id' => $site->id, 'code' => 'seo.noindex_home', 'category' => 'seo', 'source' => 'audit', 'severity' => 'critical', 'title' => 'Site ascuns din Google', 'status' => 'open', 'first_seen_at' => now(), 'last_seen_at' => now()]);
            SocialPost::create(['day' => now()->toDateString(), 'topic' => 't', 'hook' => 'h', 'caption' => 'c', 'status' => 'draft', 'source' => 'ai']);
        });
        Subscription::withoutTenancy()->where('organization_id', $org->id)->update(['status' => 'trial', 'trial_ends_at' => now()->subDay()]);
        $states = $this->states($org->fresh());
        $this->assertSame(['off', 'problem', 'needs', 'needs'], [$states['chat'], $states['seo'], $states['security'], $states['social']]);

        $this->actingAs($owner)->get("/app/{$org->slug}/birou")->assertOk()->assertSee('Biroul Podreg')->assertSee('Specialistul SEO')
            ->assertSee('1 postări așteaptă aprobarea ta.')->assertSee('Oprit: abonamentul nu este activ.')->assertSee('1 actualizări disponibile.');
    }

    public function test_staff_office_sums_all_clients_and_firms_see_only_their_own(): void
    {
        [$a, $ownerA] = $this->firm('Firma A', 'a.ro');
        [$b, , $siteB] = $this->firm('Firma B', 'b.ro');
        $this->tenant()->runAs($b, fn () => SiteIssue::create(['site_id' => $siteB->id, 'code' => 'backup.failed', 'category' => 'backup', 'source' => 'backup', 'severity' => 'critical',
            'title' => 'Backup eșuat B', 'status' => 'open', 'first_seen_at' => now(), 'last_seen_at' => now()]));
        $siteB->forceFill(['health' => ['backup_schedule' => 'daily']])->save();

        $this->actingAs($ownerA)->get("/app/{$a->slug}/birou")->assertOk()->assertDontSee('Ultimul backup a eșuat');
        $this->get("/app/{$b->slug}/birou")->assertNotFound();
        $this->get('/admin/birou')->assertNotFound();

        $this->actingAs($this->staff())->get('/admin/birou')->assertOk()->assertSee('Biroul VITIM AI')->assertSee('Agentul de monitorizare')
            ->assertSee('Arhivarul')->assertSee('Firma B')->assertSee('Ultimul backup a eșuat sau e prea vechi.')->assertSee('Lucrează la 2 firme');
    }
}
