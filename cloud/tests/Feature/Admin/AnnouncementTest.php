<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\OrgRole;
use App\Enums\WorkCategory;
use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\AnnouncementDelivery;
use App\Models\ClientService;
use App\Models\Conversation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\WorkLog;
use App\Services\Announcements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Organization, 1: User} */
    private function client(string $name, array $services = []): array
    {
        $owner = User::factory()->create(['name' => 'Ana '.$name, 'email' => strtolower($name).'@firma.ro', 'last_login_at' => now()]);
        $org = $this->makeOrganization($name, $owner);
        $this->tenant()->runAs($org, function () use ($services, $name): void {
            foreach ($services as $s) {
                ClientService::create(['service' => $s, 'status' => 'active', 'started_at' => now()]);
            }
            WorkLog::create(['category' => WorkCategory::Security, 'title' => 'Am securizat site-ul '.$name, 'performed_at' => now()->subDays(3), 'visible_to_client' => true, 'source' => 'manual']);
            WorkLog::create(['category' => WorkCategory::Other, 'title' => 'Notă internă '.$name, 'performed_at' => now()->subDays(2), 'visible_to_client' => false, 'source' => 'manual']);
        });

        return [$org, $owner];
    }

    /** @return list<AnnouncementMail> */
    private function sent(): array
    {
        return Mail::sent(AnnouncementMail::class)->all();
    }

    public function test_announcement_is_personalised_per_firm_and_respects_audience_and_opt_out(): void
    {
        Mail::fake();
        [$a, $ownerA] = $this->client('Podreg', ['seo']);
        [$b, $ownerB] = $this->client('Alta', ['maintenance']);
        $agent = User::factory()->create(['email' => 'agent@podreg.ro']);
        $this->tenant()->runAs($a, fn () => Membership::create(['user_id' => $agent->id, 'role' => OrgRole::Agent]));
        $quiet = User::factory()->create(['email' => 'admin@podreg.ro', 'product_updates' => false]);
        $this->tenant()->runAs($a, fn () => Membership::create(['user_id' => $quiet->id, 'role' => OrgRole::Admin]));

        $this->actingAs($ownerA)->get('/admin/noutati')->assertNotFound();
        $staff = $this->staff();
        $this->actingAs($staff);
        $this->get('/admin/noutati')->assertOk()->assertSee('Trimite automat noutățile');
        $this->post('/admin/noutati', ['kind' => 'news', 'title' => 'Cookie-uri pe site', 'subject' => 'Nou pentru {{firma}}', 'intro' => 'Bună veste pentru {{firma}}:',
            'body' => "Am adăugat **bannerul de cookie-uri**.\n\n- registru de dovezi\n- Consent Mode", 'cta_label' => 'Vezi', 'cta_url' => 'https://ai.vitim.ro',
            'include_work' => '1', 'services' => ['seo'], 'roles' => 'owners'])->assertRedirect();
        $ann = Announcement::query()->sole();
        $this->get("/admin/noutati/{$ann->id}")->assertOk()->assertSee('1</strong> destinatari', false);
        $preview = $this->get("/admin/noutati/{$ann->id}/previzualizare?org={$a->id}")->assertOk()->getContent();
        $this->assertStringContainsString('Am securizat site-ul Podreg', $preview);
        $this->assertStringNotContainsString('Notă internă', $preview);
        $this->assertStringContainsString('<strong>bannerul de cookie-uri</strong>', $preview);

        $this->post("/admin/noutati/{$ann->id}/test")->assertSessionHas('ok');
        $this->assertSame('[TEST] Nou pentru Podreg', $this->sent()[0]->subjectLine);
        $this->post("/admin/noutati/{$ann->id}/trimite", [])->assertSessionHasErrors('confirm');
        $this->post("/admin/noutati/{$ann->id}/trimite", ['confirm' => '1'])->assertSessionHas('ok');
        $this->artisan('vitim:announcements')->assertSuccessful();

        $real = array_values(array_filter($this->sent(), fn ($m) => ! str_starts_with($m->subjectLine, '[TEST]')));
        $this->assertCount(1, $real, 'doar proprietarul firmei cu SEO; nu agentul, nu cel dezabonat, nu firma fără SEO');
        $this->assertTrue($real[0]->hasTo('podreg@firma.ro'));
        $this->assertStringContainsString('Bună ziua, Ana,', $real[0]->htmlBody);
        $this->assertStringContainsString('Am securizat site-ul Podreg', $real[0]->htmlBody);
        $this->assertStringNotContainsString('Alta', $real[0]->htmlBody);
        $this->assertSame('sent', $ann->fresh()->status);

        // deschidere, panoul clientului, dezabonare
        $delivery = AnnouncementDelivery::withoutTenancy()->sole();
        $this->get('/n/'.$delivery->code.'.gif')->assertOk();
        $this->assertNotNull($delivery->fresh()->opened_at);
        $this->actingAs($ownerA)->get("/app/{$a->slug}/noutati-vitim")->assertOk()->assertSee('Cookie-uri pe site');
        $this->get("/app/{$a->slug}/noutati-vitim/{$ann->id}")->assertOk();
        $this->actingAs($ownerB)->get("/app/{$b->slug}/noutati-vitim")->assertOk()->assertDontSee('Cookie-uri pe site');
        $this->get("/app/{$b->slug}/noutati-vitim/{$ann->id}")->assertNotFound();
        auth()->logout();
        $this->get('/noutati/dezabonare/'.$ownerA->id)->assertForbidden();
        $link = URL::signedRoute('updates.unsubscribe', ['user' => $ownerA->id]);
        $this->get($link)->assertOk()->assertSee('Da, oprește');
        $this->post($link)->assertOk()->assertSee('nu mai primești');
        $this->assertFalse($ownerA->fresh()->product_updates);
        $this->assertSame(0, app(Announcements::class)->count($ann->fresh()));
    }

    public function test_changelog_draft_auto_send_and_monthly_digest(): void
    {
        Mail::fake();
        [$a] = $this->client('Podreg');
        [$b] = $this->client('Liniste');
        $changelog = "# Versiuni\n\n## 0.19.0 — noutăți către clienți\n\n- **Emailuri** cu noutățile.\n\n**Actualizare:** pluginul 1.6.0.\n\n## 0.18.0 — cookie-uri\n\n- vechi\n";
        $service = app(Announcements::class);

        $draft = $service->draftFromChangelog('0.19.0', $changelog);
        $this->assertSame('draft', $draft->status);
        $this->assertSame('Noutăți către clienți', $draft->title);
        $this->assertStringContainsString('**Emailuri**', $draft->body);
        $this->assertStringNotContainsString('Actualizare', $draft->body);
        $this->assertStringNotContainsString('vechi', $draft->body);
        $this->assertNull($service->draftFromChangelog('0.19.0', $changelog));
        $draft->delete();

        PlatformSetting::put('announcements', ['auto_updates' => true, 'digest' => false, 'digest_day' => 3, 'roles' => 'owners']);
        $auto = $service->draftFromChangelog('0.19.0', $changelog);
        $this->assertSame('scheduled', $auto->status);
        $this->artisan('vitim:announcements');
        $this->assertCount(0, $this->sent(), 'pleacă abia a doua zi la 10:00');
        $this->travelTo($auto->scheduled_at->copy()->addMinute());
        $this->artisan('vitim:announcements');
        $this->assertCount(2, $this->sent());
        $this->assertSame('sent', $auto->fresh()->status);

        // rezumatul lunar: doar firma cu activitate în luna trecută
        PlatformSetting::put('announcements', ['auto_updates' => true, 'digest' => true, 'digest_day' => 3, 'roles' => 'owners']);
        $this->travelTo(now('Europe/Bucharest')->startOfMonth()->addMonth()->setDay(3)->setTime(10, 30)->utc());
        $period = now('Europe/Bucharest')->subMonthNoOverflow();
        $this->tenant()->runAs($a, function () use ($period): void {
            WorkLog::create(['category' => WorkCategory::Updates, 'title' => 'Actualizări lunare', 'performed_at' => $period->copy()->setDay(10), 'visible_to_client' => true, 'source' => 'manual']);
            Conversation::create(['channel' => 'web', 'status' => 'open', 'mode' => 'ai', 'is_test' => false])->forceFill(['created_at' => $period->copy()->setDay(12)])->save();
        });
        $this->artisan('vitim:announcements');
        $digest = Announcement::query()->where('kind', 'digest')->sole();
        $this->assertSame($period->format('Y-m'), $digest->period);
        $mails = array_values(array_filter($this->sent(), fn ($m) => str_contains($m->subjectLine, 'ce am făcut împreună')));
        $this->assertCount(1, $mails, 'firma fără activitate nu primește rezumatul');
        $this->assertTrue($mails[0]->hasTo('podreg@firma.ro'));
        $this->assertStringContainsString('Conversații cu clienții pe site', $mails[0]->htmlBody);
        $this->assertStringContainsString('Actualizări lunare', $mails[0]->htmlBody);
        $this->artisan('vitim:announcements');
        $this->assertSame(1, Announcement::query()->where('kind', 'digest')->count());
    }
}
