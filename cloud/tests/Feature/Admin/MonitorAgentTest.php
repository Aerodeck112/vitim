<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\PlatformRole;
use App\Mail\MonitorReport;
use App\Models\Organization;
use App\Models\Site;
use App\Models\SiteIssue;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Agentul de monitorizare: rezumatul de dimineață și alertele critice, doar pentru echipa VITIM. */
final class MonitorAgentTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Organization, 1: Site} */
    private function client(string $name, string $domain): array
    {
        $org = $this->makeOrganization($name);
        Subscription::withoutTenancy()->where('organization_id', $org->id)->update(['status' => 'active', 'trial_ends_at' => null]);
        [$site] = $this->makeSite($org, $domain);
        $site->forceFill(['last_seen_at' => now(), 'last_audit_at' => now(), 'scores' => ['seo' => 90, 'security' => 80]])->save();

        return [$org, $site->fresh()];
    }

    private function issue(Organization $org, Site $site, string $code, string $severity, string $title): void
    {
        $this->tenant()->runAs($org, fn () => SiteIssue::create(['site_id' => $site->id, 'code' => $code, 'category' => explode('.', $code)[0], 'source' => 'audit',
            'severity' => $severity, 'title' => $title, 'status' => 'open', 'first_seen_at' => now(), 'last_seen_at' => now()]));
    }

    public function test_morning_digest_lists_what_needs_attention_once_a_day(): void
    {
        Mail::fake();
        $this->travelTo(now()->setTimezone('Europe/Bucharest')->setTime(6, 0));
        $admin = $this->staff();
        [$podreg, $sitePodreg] = $this->client('Podreg', 'podreg.ro');
        [$dental, $siteDental] = $this->client('Dental Arena', 'dentalarena.ro');
        [$trial] = $this->client('Sevalia', 'sevalia.ro');
        $this->issue($podreg, $sitePodreg, 'backup.failed', 'critical', 'Ultimul backup a eșuat');
        $this->issue($podreg, $sitePodreg, 'seo.og_missing', 'warning', 'Lipsesc etichetele Open Graph');
        $this->issue($dental, $siteDental, 'sec.ssl_expiring', 'warning', 'Certificatul HTTPS expiră în 9 zile');
        $siteDental->forceFill(['last_seen_at' => now()->subHours(30)])->save();
        Subscription::withoutTenancy()->where('organization_id', $trial->id)->update(['status' => 'trial', 'trial_ends_at' => now()->addDays(2)]);

        // înainte de ora rezumatului pleacă doar alerta pentru problemele critice (o singură dată)
        $this->artisan('vitim:monitor')->assertSuccessful();
        Mail::assertSent(MonitorReport::class, 1);
        Mail::assertSent(MonitorReport::class, function (MonitorReport $m) use ($admin) {
            $html = $m->render();

            return $m->urgent && $m->hasTo($admin->email) && str_contains($html, 'Ultimul backup a eșuat') && str_contains($html, 'conectorul nu mai raportează')
                && ! str_contains($html, 'Certificatul HTTPS expiră');
        });
        $this->artisan('vitim:monitor')->assertSuccessful();
        Mail::assertSent(MonitorReport::class, 1);

        // la ora rezumatului: totul, grupat pe client
        $this->travelTo(now()->setTimezone('Europe/Bucharest')->setTime(7, 5));
        $this->artisan('vitim:monitor')->assertSuccessful();
        Mail::assertSent(MonitorReport::class, function (MonitorReport $m) {
            $html = $m->render();

            return ! $m->urgent && str_contains($m->envelope()->subject, '2 critice, 2 de urmărit') && str_contains($html, 'Certificatul HTTPS expiră în 9 zile')
                && str_contains($html, 'Perioada de probă expiră') && str_contains($html, 'Alte 1 avertismente') && ! str_contains($html, 'Open Graph');
        });
        $this->artisan('vitim:monitor')->assertSuccessful();
        Mail::assertSent(MonitorReport::class, 2);

        // a doua zi: scorul scăzut apare în rezumat
        $sitePodreg->forceFill(['scores' => ['seo' => 70, 'security' => 80]])->save();
        $this->travel(1)->days();
        Site::withoutTenancy()->whereKey($sitePodreg->id)->update(['last_seen_at' => now(), 'last_audit_at' => now()]);
        $this->artisan('vitim:monitor')->assertSuccessful();
        Mail::assertSent(MonitorReport::class, fn (MonitorReport $m) => ! $m->urgent && str_contains($m->render(), 'a scăzut de la 90 la 70'));
    }

    public function test_quiet_days_send_nothing_and_resolved_problems_alert_again_when_they_return(): void
    {
        Mail::fake();
        $this->staff();
        [$org, $site] = $this->client('Podreg', 'podreg.ro');
        $this->travelTo(now()->setTimezone('Europe/Bucharest')->setTime(8, 0));

        $this->artisan('vitim:monitor')->assertSuccessful(); // rezumatul zilei: nimic de semnalat
        Mail::assertNothingSent();

        $this->issue($org, $site, 'sec.ssl_invalid', 'critical', 'Site-ul nu se deschide pe HTTPS');
        $this->artisan('vitim:monitor')->assertSuccessful();
        Mail::assertSent(MonitorReport::class, 1);
        SiteIssue::withoutTenancy()->update(['status' => 'resolved']);
        $this->artisan('vitim:monitor')->assertSuccessful();
        SiteIssue::withoutTenancy()->update(['status' => 'open']);
        $this->artisan('vitim:monitor')->assertSuccessful();
        Mail::assertSent(MonitorReport::class, 2);

        // oprit din setări: nu mai pleacă nimic
        $this->actingAs($this->staff())->put('/admin/monitorizare', ['hour' => 7, 'extra' => 'nu-e-adresa'])->assertSessionHasErrors('extra.0');
        $this->put('/admin/monitorizare', ['hour' => 9, 'urgent' => 1, 'extra' => 'alerte@exemplu.ro'])->assertSessionHas('ok');
        SiteIssue::withoutTenancy()->update(['code' => 'backup.failed']);
        $this->artisan('vitim:monitor')->assertSuccessful();
        Mail::assertSent(MonitorReport::class, 2);
    }

    public function test_only_vitim_staff_see_the_monitor_and_can_send_it(): void
    {
        Mail::fake();
        $owner = User::factory()->create(['last_login_at' => now()]);
        $org = $this->makeOrganization('Podreg', $owner);
        [$site] = $this->makeSite($org, 'podreg.ro');
        $site->forceFill(['last_seen_at' => now()->subDays(3)])->save();

        $this->actingAs($owner)->get('/admin/monitorizare')->assertNotFound();
        $this->post('/admin/monitorizare/trimite')->assertNotFound();
        $this->put('/admin/monitorizare', ['hour' => 7])->assertNotFound();
        Mail::assertNothingSent();

        $admin = $this->staff(PlatformRole::VitimAdmin);
        $this->actingAs($admin)->get('/admin/monitorizare')->assertOk()->assertSee('Agent de monitorizare')
            ->assertSee('podreg.ro: conectorul nu mai raportează')->assertSee('acum 3 zile');
        $this->put('/admin/monitorizare', ['enabled' => 1, 'hour' => 7, 'extra' => 'alerte@exemplu.ro'])->assertSessionHas('ok');
        $this->post('/admin/monitorizare/trimite')->assertSessionHas('ok');
        Mail::assertSent(MonitorReport::class, fn (MonitorReport $m) => $m->hasTo('alerte@exemplu.ro'));
        Mail::assertSent(MonitorReport::class, fn (MonitorReport $m) => $m->hasTo($admin->email));
        Mail::assertNotSent(MonitorReport::class, fn (MonitorReport $m) => $m->hasTo($owner->email));
        $this->get('/admin')->assertOk()->assertSee('Monitorizare');
    }
}
