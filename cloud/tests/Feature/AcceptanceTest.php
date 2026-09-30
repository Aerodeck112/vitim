<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContactSource;
use App\Enums\PlatformRole;
use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use App\Security\Totp;
use App\Services\ContactService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Criteriul de acceptanță Phase 1 (brief §34), parcurs prin HTTP, ca un om:
 * SUPER_ADMIN → Organizația A → Site A → Agent A → utilizator al firmei → acesta vede doar A →
 * Contact A + Lead A → Organizația B → acces la B refuzat → jurnalul de audit reflectă acțiunile.
 */
final class AcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private function totpNow(string $secret): string
    {
        return Totp::code($secret, intdiv(time(), 30));
    }

    public function test_phase_one_acceptance_flow(): void
    {
        Notification::fake();

        // 1. SUPER_ADMIN se autentifică (parolă + 2FA)
        $secret = Totp::secret();
        $admin = User::create(['name' => 'Admin VITIM', 'email' => 'admin@vitim.test', 'password' => 'parola-admin-foarte-sigura']);
        $admin->forceFill(['platform_role' => PlatformRole::SuperAdmin, 'totp_secret' => $secret, 'totp_confirmed_at' => now()])->save();
        $this->post('/login', ['email' => 'admin@vitim.test', 'password' => 'parola-admin-foarte-sigura'])->assertRedirect();
        $this->get('/admin')->assertRedirect('/2fa');
        $this->post('/2fa', ['code' => $this->totpNow($secret)])->assertRedirect();
        $this->get('/admin')->assertOk();

        // 2. creează Organizația A
        $this->post('/admin/clienti', ['name' => 'Organizația A', 'plan' => 'pro', 'owner_name' => 'Proprietar A', 'owner_email' => 'owner-a@example.test', 'country' => 'RO', 'default_language' => 'ro'])
            ->assertRedirect('/admin/clienti/organizatia-a');
        $orgA = Organization::where('slug', 'organizatia-a')->firstOrFail();

        // 3. Site A și 4. Agent A (din dashboardul firmei, în numele echipei VITIM)
        $this->post('/admin/clienti/organizatia-a/site-uri', ['domain' => 'site-a.ro', 'platform' => 'wordpress'])->assertRedirect();
        $this->post('/app/organizatia-a/agent', ['name' => 'Agent A'])->assertRedirect();

        // 5. creează un utilizator al organizației (operator)
        $this->post('/app/organizatia-a/setari/utilizatori', ['name' => 'Operator A', 'email' => 'operator-a@example.test', 'role' => 'agent'])->assertRedirect();
        $operator = User::where('email', 'operator-a@example.test')->firstOrFail();
        $token = null;
        Notification::assertSentTo($operator, ResetPassword::class, function (ResetPassword $n) use (&$token) {
            $token = $n->token;

            return true;
        });

        // 6. utilizatorul își setează parola și se autentifică
        $this->post('/logout');
        Auth::forgetGuards();
        $this->post('/parola/noua', ['token' => $token, 'email' => 'operator-a@example.test', 'password' => 'parola-operator-sigura', 'password_confirmation' => 'parola-operator-sigura'])->assertRedirect('/login');
        $this->post('/login', ['email' => 'operator-a@example.test', 'password' => 'parola-operator-sigura'])->assertRedirect();
        $this->assertAuthenticatedAs($operator);

        // 7. vede DOAR Organizația A
        $this->get('/')->assertRedirect('/app/organizatia-a');
        $this->get('/admin')->assertNotFound();
        $this->assertSame(['organizatia-a'], $operator->organizations()->pluck('slug')->all());

        // 8. creează Contact A și Lead A (prin API)
        $contactId = $this->postJson('/api/v1/orgs/organizatia-a/contacts', ['first_name' => 'Contact', 'last_name' => 'A', 'email' => 'contact-a@example.test'])
            ->assertCreated()->json('data.id');
        $leadId = $this->postJson('/api/v1/orgs/organizatia-a/leads', ['contact_id' => $contactId, 'intent' => 'quote_request', 'summary' => 'Lead A'])
            ->assertCreated()->json('data.id');

        // 9. toate entitățile au organizația corectă
        foreach ([Site::class, Agent::class, Contact::class, Lead::class] as $model) {
            $this->assertSame([$orgA->id], $model::withoutTenancy()->pluck('organization_id')->unique()->values()->all(), $model);
        }

        // 10. Organizația B există, cu propriile date
        // (creată în afara sesiunii operatorului, ca de echipa VITIM într-o altă sesiune)
        Auth::logout();
        $orgB = $this->makeOrganization('Organizația B', null, 'pro');
        $contactB = $this->tenant()->runAs($orgB, fn () => app(ContactService::class)->create(['first_name' => 'Contact B'], ContactSource::Manual));
        Auth::login($operator);

        // 11. utilizatorul din A încearcă resursele lui B → ACCES REFUZAT
        $this->getJson("/api/v1/orgs/organizatia-a/contacts/{$contactB->id}")->assertNotFound();
        $this->getJson('/api/v1/orgs/organizatia-b/contacts')->assertNotFound();
        $this->get("/app/organizatia-b/contacte/{$contactB->id}")->assertNotFound();
        $this->patchJson("/api/v1/orgs/organizatia-a/leads/{$leadId}", ['status' => 'won'])->assertOk(); // propriile date: permis

        // 12. jurnalul de audit reflectă acțiunile importante, în organizația corectă
        $actions = AuditLog::withoutTenancy()->where('organization_id', $orgA->id)->pluck('action')->all();
        foreach (['organization.created', 'site.created', 'site_key.issued', 'agent.created', 'user.invited'] as $expected) {
            $this->assertContains($expected, $actions, "Lipsește din audit: {$expected}");
        }
        $this->assertTrue(AuditLog::withoutTenancy()->where('organization_id', $orgA->id)->where('action', 'platform.accessed_organization')->exists());
        $this->assertTrue(AuditLog::withoutTenancy()->whereNull('organization_id')->where('action', 'auth.login')->exists());
        $this->assertSame(0, AuditLog::withoutTenancy()->where('organization_id', $orgB->id)->where('actor_user_id', $operator->id)->count());
    }
}
