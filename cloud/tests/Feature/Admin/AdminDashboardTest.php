<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\OrgRole;
use App\Enums\PlatformRole;
use App\Http\Middleware\EnsureTwoFactor;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_area_is_hidden_from_clients(): void
    {
        $owner = User::factory()->create();
        $this->makeOrganization('Firma A', $owner);

        $this->actingAs($owner)->get('/admin')->assertNotFound();
    }

    public function test_admin_creates_client_and_owner_gets_password_link(): void
    {
        Notification::fake();
        $admin = $this->staff();

        $this->actingAs($admin)->get('/admin/clienti/nou')->assertOk();
        $this->post('/admin/clienti', ['name' => 'Demo Auto SRL', 'plan' => 'pro', 'owner_name' => 'Ion Pop', 'owner_email' => 'Ion@DemoAuto.ro'])
            ->assertRedirect('/admin/clienti/demo-auto-srl');

        $org = Organization::where('slug', 'demo-auto-srl')->firstOrFail();
        $owner = User::where('email', 'ion@demoauto.ro')->firstOrFail();
        $this->assertSame(OrgRole::Owner, $owner->roleIn($org));
        Notification::assertSentTo($owner, ResetPassword::class);
        $this->get('/admin')->assertOk()->assertSee('Demo Auto SRL');
    }

    public function test_site_secret_is_shown_once(): void
    {
        $admin = $this->staff();
        $org = $this->makeOrganization('Demo Auto SRL');

        $this->actingAs($admin)->post("/admin/clienti/{$org->slug}/site-uri", ['domain' => 'https://www.demoauto.ro', 'platform' => 'wordpress'])
            ->assertRedirect("/admin/clienti/{$org->slug}");
        $secret = session('issued')['secret'];
        $this->assertStringStartsWith('sk_', $secret);

        $this->get("/admin/clienti/{$org->slug}")->assertOk()->assertSee($secret)->assertSee('demoauto.ro');
        $this->get("/admin/clienti/{$org->slug}")->assertOk()->assertDontSee($secret);
    }

    public function test_vitim_admin_manages_clients(): void
    {
        $vitimAdmin = $this->staff(PlatformRole::VitimAdmin);
        $org = $this->makeOrganization('Firma A');
        [$site] = $this->makeSite($org, 'firma-a.ro');

        $this->actingAs($vitimAdmin)->get('/admin')->assertOk();
        $this->get("/admin/clienti/{$org->slug}")->assertOk()->assertSee('Schimbă cheile');
        $this->get('/admin/clienti/nou')->assertOk();
        $this->post("/admin/clienti/{$org->slug}/site-uri/{$site->id}/chei")->assertRedirect();
    }

    public function test_cannot_rotate_site_of_another_client_through_url(): void
    {
        $admin = $this->staff();
        $a = $this->makeOrganization('Firma A');
        $b = $this->makeOrganization('Firma B');
        [$siteB] = $this->makeSite($b, 'firma-b.ro');

        $this->actingAs($admin)->post("/admin/clienti/{$a->slug}/site-uri/{$siteB->id}/chei")->assertNotFound();
    }

    public function test_portal_shows_install_code_only_to_site_managers(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $org = $this->makeOrganization('Firma A', $owner);
        [, $key] = $this->makeSite($org, 'firma-a.ro');
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $viewer->id, 'role' => OrgRole::Viewer]));

        $this->actingAs($owner)->get('/')->assertRedirect("/app/{$org->slug}");
        $this->get("/app/{$org->slug}")->assertOk()->assertSee($key->publicKey)->assertDontSee($key->secret);
        $this->actingAs($viewer)->get("/app/{$org->slug}")->assertOk()->assertDontSee($key->publicKey);

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->withSession([EnsureTwoFactor::SESSION_KEY => true])->get("/app/{$org->slug}")->assertNotFound();
    }
}
