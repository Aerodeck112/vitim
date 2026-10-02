<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\OrgRole;
use App\Models\ClientService;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\Invitation;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Invitațiile clienților: valabile 7 zile, retrimise din panou; resetarea parolei rămâne la 60 de minute. */
final class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private function createClient(): array
    {
        $this->actingAs($this->staff());
        $this->post('/admin/clienti', ['name' => 'Podreg', 'plan' => 'pro', 'owner_name' => 'Ion Pop', 'owner_email' => 'ion@podreg.ro',
            'country' => 'RO', 'default_language' => 'ro'])->assertRedirect();
        $owner = User::where('email', 'ion@podreg.ro')->sole();
        $org = Organization::where('name', 'Podreg')->sole();

        return [$org, $owner];
    }

    private function tokenFor(User $user): string
    {
        $token = null;
        Notification::assertSentTo($user, Invitation::class, function (Invitation $n) use (&$token, $user) {
            preg_match('#/parola/([^?]+)\?#', $n->toMail($user)->actionUrl, $m);
            $token = $m[1];

            return true;
        });

        return $token;
    }

    public function test_invitation_lasts_seven_days_and_can_be_resent_when_expired(): void
    {
        Notification::fake();
        [$org, $owner] = $this->createClient();
        Notification::assertNotSentTo($owner, ResetPassword::class);
        $mail = (new Invitation('t', 'Podreg'))->toMail($owner);
        $this->assertStringContainsString('invitatie=1', $mail->actionUrl);
        $this->assertStringContainsString('7 zile', implode(' ', $mail->outroLines));
        $token = $this->tokenFor($owner);

        // expirată după 8 zile; fișa clientului arată asta și are butonul de retrimitere
        $this->travel(8)->days();
        $member = $this->tenant()->runAs($org, fn () => Membership::query()->sole());
        $this->get("/admin/clienti/{$org->slug}")->assertOk()->assertSee('invitație expirată')->assertSee('Retrimite invitația');
        auth()->logout();
        $this->post('/parola/noua', ['token' => $token, 'email' => 'ion@podreg.ro', 'invite' => '1', 'password' => 'parola-foarte-sigura-1', 'password_confirmation' => 'parola-foarte-sigura-1'])
            ->assertSessionHasErrors('email');

        Notification::fake();
        $this->actingAs($this->staff());
        $this->post("/admin/clienti/{$org->slug}/utilizatori/{$member->id}/invitatie")->assertRedirect()->assertSessionHas('ok');
        $this->get("/admin/clienti/{$org->slug}")->assertSee('invitație trimisă');
        $fresh = $this->tokenFor($owner);
        auth()->logout();

        // linkul de invitație nu merge ca resetare de 60 de minute (tabel separat), dar merge ca invitație și după 3 zile
        $this->travel(3)->days();
        $this->post('/parola/noua', ['token' => $fresh, 'email' => 'ion@podreg.ro', 'password' => 'parola-foarte-sigura-1', 'password_confirmation' => 'parola-foarte-sigura-1'])->assertSessionHasErrors('email');
        $this->get("/parola/{$fresh}?email=ion@podreg.ro&invitatie=1")->assertOk()->assertSee('Creează parola');
        $this->post('/parola/noua', ['token' => $fresh, 'email' => 'ion@podreg.ro', 'invite' => '1', 'password' => 'parola-foarte-sigura-1', 'password_confirmation' => 'parola-foarte-sigura-1'])
            ->assertRedirect('/login');
        $this->post('/login', ['email' => 'ion@podreg.ro', 'password' => 'parola-foarte-sigura-1'])->assertRedirect();
    }

    public function test_client_owner_resends_to_a_colleague_and_isolation(): void
    {
        Notification::fake();
        [$org, $owner] = $this->createClient();
        $owner->forceFill(['last_login_at' => now()])->save();
        $this->actingAs($owner);
        $this->post("/app/{$org->slug}/setari/utilizatori", ['name' => 'Ana', 'email' => 'ana@podreg.ro', 'role' => OrgRole::Agent->value])->assertRedirect();
        $ana = User::where('email', 'ana@podreg.ro')->sole();
        Notification::assertSentTo($ana, Invitation::class);
        $anaMember = $this->tenant()->runAs($org, fn () => Membership::query()->where('user_id', $ana->id)->sole());
        $ownerMember = $this->tenant()->runAs($org, fn () => Membership::query()->where('user_id', $owner->id)->sole());

        $this->get("/app/{$org->slug}/setari")->assertSee('Retrimite invitația');
        $this->travel(1)->minutes();
        $this->post("/app/{$org->slug}/setari/utilizatori/{$anaMember->id}/invitatie")->assertRedirect()->assertSessionHas('ok');
        $this->post("/app/{$org->slug}/setari/utilizatori/{$ownerMember->id}/invitatie")->assertStatus(422); // are deja parolă

        // un viewer nu poate; altă firmă nu vede membrul
        $viewer = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $viewer->id, 'role' => OrgRole::Viewer]));
        $this->actingAs($viewer);
        $this->post("/app/{$org->slug}/setari/utilizatori/{$anaMember->id}/invitatie")->assertForbidden();
        $other = $this->makeOrganization('Alta');
        $stranger = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($other, fn () => Membership::create(['user_id' => $stranger->id, 'role' => OrgRole::Owner]));
        $this->actingAs($stranger);
        $this->post("/app/{$other->slug}/setari/utilizatori/{$anaMember->id}/invitatie")->assertNotFound();
        $this->actingAs($this->staff());
        $this->post("/admin/clienti/{$other->slug}/utilizatori/{$anaMember->id}/invitatie")->assertNotFound();
    }

    public function test_forgotten_password_link_still_expires_in_an_hour(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'x@y.ro']);
        $this->post('/parola', ['email' => 'x@y.ro'])->assertRedirect();
        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertNotSentTo($user, Invitation::class);
    }

    public function test_clients_are_filtered_by_service(): void
    {
        $seo = $this->makeOrganization('Cu SEO');
        $ads = $this->makeOrganization('Cu Ads');
        $none = $this->makeOrganization('Fără nimic');
        $this->tenant()->runAs($seo, fn () => ClientService::create(['service' => 'seo', 'status' => 'active']));
        $this->tenant()->runAs($ads, fn () => ClientService::create(['service' => 'google_ads', 'status' => 'active']));
        $this->tenant()->runAs($ads, fn () => ClientService::create(['service' => 'seo', 'status' => 'paused']));
        $this->actingAs($this->staff());

        $this->get('/admin')->assertOk()->assertSee('Cu SEO')->assertSee('Cu Ads')->assertSee('SEO (1)')->assertSee('Google Ads (1)')->assertSee('Fără servicii (1)');
        $this->get('/admin?serviciu=seo')->assertOk()->assertSee('Cu SEO')->assertDontSee('Cu Ads')->assertDontSee('Fără nimic');
        $this->get('/admin?serviciu=google_ads')->assertSee('Cu Ads')->assertDontSee('Cu SEO');
        $this->get('/admin?serviciu=fara')->assertSee('Fără nimic')->assertDontSee('Cu SEO');
        $this->get('/admin?serviciu=altceva')->assertSee('Cu SEO')->assertSee('Cu Ads');
    }
}
