<?php

declare(strict_types=1);

namespace Tests\Feature\WorkLogs;

use App\Enums\OrgRole;
use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkLog;
use App\Services\WorkLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WorkLogTest extends TestCase
{
    use RefreshDatabase;

    private function member(Organization $org, OrgRole $role): User
    {
        $user = User::factory()->create();
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $user->id, 'role' => $role]));

        return $user;
    }

    public function test_staff_logs_work_and_client_sees_only_visible_entries(): void
    {
        $org = $this->makeOrganization('Podreg');
        [$site] = $this->makeSite($org, 'podreg.ro');
        $staff = $this->staff(PlatformRole::VitimAdmin);
        $this->actingAs($staff);
        $base = "/admin/clienti/{$org->slug}/lucrari";

        $this->get($base)->assertOk()->assertSee('Lucrare nouă');
        $this->post($base, ['performed_at' => '2026-10-01 10:00', 'category' => 'updates', 'title' => 'Actualizare WordPress și 12 pluginuri',
            'site_id' => $site->id, 'duration_minutes' => 45, 'visible_to_client' => '1'])->assertRedirect($base);
        $this->post($base, ['performed_at' => '2026-10-02 10:00', 'category' => 'other', 'title' => 'Notă internă: client dificil'])->assertRedirect($base);
        $this->post($base, ['performed_at' => '2026-10-02', 'category' => 'nu-exista', 'title' => 'x'])->assertSessionHasErrors('category');

        $logs = $this->tenant()->runAs($org, fn () => WorkLog::query()->orderBy('id')->get());
        $this->assertCount(2, $logs);
        $this->assertSame($staff->id, $logs[0]->performed_by);
        $this->assertFalse($logs[1]->visible_to_client);

        $this->put("{$base}/{$logs[0]->id}", ['performed_at' => '2026-10-01 10:00', 'category' => 'updates', 'title' => 'Actualizare WordPress 6.8 și 12 pluginuri',
            'site_id' => $site->id, 'duration_minutes' => 50, 'visible_to_client' => '1'])->assertRedirect($base);
        $this->assertSame(50, $logs[0]->fresh()->duration_minutes);
        $this->assertSame(2, AuditLog::withoutTenancy()->whereIn('action', ['worklog.created', 'worklog.updated'])->where('action', 'worklog.created')->count());

        $client = $this->member($org, OrgRole::Viewer);
        $this->actingAs($client);
        $this->get("/app/{$org->slug}/lucrari")->assertOk()->assertSee('Actualizare WordPress 6.8')->assertSee('50 min')
            ->assertDontSee('Notă internă');
        $this->get("/app/{$org->slug}/lucrari?luna=2026-09")->assertOk()->assertDontSee('Actualizare WordPress 6.8');
        $this->get("/app/{$org->slug}")->assertOk()->assertSee('Ultimele lucrări VITIM')->assertSee('Actualizare WordPress 6.8')->assertDontSee('Notă internă');

        // clientul nu poate scrie în jurnal
        $this->get($base)->assertNotFound();
        $this->post($base, ['performed_at' => '2026-10-01', 'category' => 'other', 'title' => 'fals'])->assertNotFound();
    }

    public function test_work_logs_are_isolated_between_clients(): void
    {
        $a = $this->makeOrganization('Firma A');
        $b = $this->makeOrganization('Firma B');
        [$siteB] = $this->makeSite($b, 'firmab.ro');
        $logA = $this->tenant()->runAs($a, fn () => app(WorkLogService::class)->create(['category' => 'backup', 'title' => 'Backup A'], null));

        $this->actingAs($this->member($b, OrgRole::Owner));
        $this->get("/app/{$b->slug}/lucrari")->assertOk()->assertDontSee('Backup A');
        $this->get("/app/{$a->slug}/lucrari")->assertNotFound();

        $this->actingAs($this->staff());
        // jurnalul lui B nu poate atinge lucrarea lui A și nu poate folosi un site străin
        $this->get("/admin/clienti/{$b->slug}/lucrari/{$logA->id}/editare")->assertNotFound();
        $this->delete("/admin/clienti/{$b->slug}/lucrari/{$logA->id}")->assertNotFound();
        $this->post("/admin/clienti/{$a->slug}/lucrari", ['performed_at' => '2026-10-01', 'category' => 'other', 'title' => 'x', 'site_id' => $siteB->id])
            ->assertSessionHasErrors('site_id');
        $this->tenant()->runAs($a, fn () => $this->assertSame(1, WorkLog::query()->count()));
    }
}
