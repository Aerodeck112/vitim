<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Enums\OrgRole;
use App\Mail\MonthlyReportPublished;
use App\Models\ClientService;
use App\Models\Membership;
use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Models\User;
use App\Services\WorkLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class MonthlyReportTest extends TestCase
{
    use RefreshDatabase;

    private function member(Organization $org, OrgRole $role, string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $user->id, 'role' => $role]));

        return $user;
    }

    public function test_staff_fills_and_publishes_report_client_sees_it_with_month_over_month(): void
    {
        Mail::fake();
        $this->travelTo(now()->setDate(2026, 10, 15));
        $org = $this->makeOrganization('Podreg');
        $owner = $this->member($org, OrgRole::Owner, 'owner@podreg.test');
        $this->member($org, OrgRole::Viewer, 'viewer@podreg.test');
        $this->tenant()->runAs($org, function (): void {
            $logs = app(WorkLogService::class);
            $logs->create(['category' => 'seo', 'title' => 'Optimizare pagină Cabane din lemn', 'performed_at' => '2026-10-03 10:00'], null);
            $logs->create(['category' => 'gbp', 'title' => '4 postări Google Business', 'performed_at' => '2026-10-05 10:00'], null);
            $logs->create(['category' => 'updates', 'title' => 'Actualizare WordPress', 'performed_at' => '2026-10-06 10:00'], null);
            $logs->create(['category' => 'seo', 'title' => 'Lucrare din septembrie', 'performed_at' => '2026-09-20 10:00'], null);
        });

        $this->actingAs($this->staff());
        $base = "/admin/clienti/{$org->slug}";
        $this->post("{$base}/servicii", ['services' => ['maintenance', 'seo', 'google_business']])->assertRedirect();
        $this->tenant()->runAs($org, fn () => $this->assertSame(3, ClientService::query()->where('status', 'active')->count()));

        // luna trecută, publicată, pentru comparație
        $this->put("{$base}/rapoarte/2026-09", ['data' => ['seo' => ['metrics' => ['clicks' => 100, 'avg_position' => 18]]], 'publish' => '1'])->assertRedirect();
        $this->put("{$base}/rapoarte/2026-10", [
            'data' => ['seo' => ['metrics' => ['clicks' => 140, 'avg_position' => 12.5], 'summary' => 'Am optimizat paginile de cabane.'],
                'google_business' => ['metrics' => ['views' => 900, 'rating' => 4.8]]],
            'summary' => 'O lună bună.',
        ])->assertRedirect();
        $this->put("{$base}/rapoarte/2026-10", ['data' => ['google_business' => ['metrics' => ['rating' => 7]]]])->assertSessionHasErrors('data.google_business.metrics.rating');
        $this->get("{$base}/rapoarte/2026-10")->assertOk()->assertSee('Previzualizare')->assertSee('+40');
        Mail::assertSentCount(1); // doar luna publicată

        // ciorna nu e vizibilă clientului
        $this->actingAs($owner);
        $this->get("/app/{$org->slug}/rapoarte/2026-10")->assertNotFound();

        $this->actingAs($this->staff());
        $this->put("{$base}/rapoarte/2026-10", [
            'data' => ['seo' => ['metrics' => ['clicks' => 140, 'avg_position' => 12.5], 'summary' => 'Am optimizat paginile de cabane.'],
                'google_business' => ['metrics' => ['views' => 900, 'rating' => 4.8]]],
            'summary' => 'O lună bună.', 'publish' => '1',
        ])->assertRedirect()->assertSessionHas('ok');
        Mail::assertSent(MonthlyReportPublished::class, fn ($m) => $m->hasTo('owner@podreg.test') && str_contains($m->url, '/rapoarte/2026-10') && $m->label === 'Octombrie 2026');
        Mail::assertNotSent(MonthlyReportPublished::class, fn ($m) => $m->hasTo('viewer@podreg.test'));

        $this->actingAs($owner);
        $this->get("/app/{$org->slug}/rapoarte")->assertOk()->assertSee('Octombrie 2026')->assertSee('Septembrie 2026');
        $page = $this->get("/app/{$org->slug}/rapoarte/2026-10")->assertOk();
        $page->assertSee('O lună bună.')->assertSee('Am optimizat paginile de cabane.')->assertSee('Optimizare pagină Cabane din lemn')
            ->assertSee('4 postări Google Business')->assertSee('Actualizare WordPress')->assertDontSee('Lucrare din septembrie')
            ->assertSee('+40')->assertSee('-5,5'); // poziția medie scade = bine
        $this->get("/admin/clienti/{$org->slug}/rapoarte")->assertNotFound();
    }

    public function test_reports_are_isolated_between_clients(): void
    {
        $a = $this->makeOrganization('Firma A');
        $b = $this->makeOrganization('Firma B');
        $this->tenant()->runAs($a, function (): void {
            $report = MonthlyReport::create(['period' => '2026-09', 'summary' => 'Secret A']);
            $report->forceFill(['published_at' => now()])->save();
        });
        $ownerB = $this->member($b, OrgRole::Owner, 'b@firma.test');
        $this->actingAs($ownerB);
        $this->get("/app/{$b->slug}/rapoarte/2026-09")->assertNotFound();
        $this->get("/app/{$b->slug}/rapoarte")->assertOk()->assertDontSee('Septembrie 2026');
        $this->get("/app/{$a->slug}/rapoarte/2026-09")->assertNotFound();
    }
}
