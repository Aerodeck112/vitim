<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Enums\ContactSource;
use App\Enums\LeadStatus;
use App\Enums\OrgRole;
use App\Models\AuditLog;
use App\Models\DomainEvent;
use App\Models\Membership;
use App\Models\User;
use App\Services\AgentService;
use App\Services\ContactService;
use App\Services\LeadService;
use App\Services\MembershipService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class LeadsAgentsMembersTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_lifecycle_emits_events(): void
    {
        $owner = User::factory()->create();
        $org = $this->makeOrganization('Firma A', $owner);
        $this->tenant()->runAs($org, function () use ($owner): void {
            $contact = app(ContactService::class)->create(['first_name' => 'Ion'], ContactSource::WebsiteAi);
            $lead = app(LeadService::class)->create($contact, ['intent' => 'quote_request', 'summary' => 'Vrea ofertă', 'score' => 80]);
            $this->assertSame(LeadStatus::New, $lead->status);

            app(LeadService::class)->update($lead, ['status' => 'won', 'assigned_to' => $owner->id]);
            $this->assertNotNull($lead->fresh()->closed_at);
            $this->assertEqualsCanonicalizing(['contact.created', 'lead.created', 'lead.status_changed', 'lead.assigned'],
                DomainEvent::query()->pluck('type')->all());
            $this->assertSame(['from' => 'new', 'to' => 'won'], DomainEvent::query()->where('type', 'lead.status_changed')->value('payload'));
        });
    }

    public function test_lead_cannot_reference_other_organization(): void
    {
        $a = $this->makeOrganization('Firma A');
        $outsider = User::factory()->create();
        $b = $this->makeOrganization('Firma B', $outsider);
        [$siteB] = $this->makeSite($b, 'firma-b.ro');

        $this->tenant()->runAs($a, function () use ($outsider, $siteB): void {
            $contact = app(ContactService::class)->create(['first_name' => 'Ion'], ContactSource::Manual);
            foreach ([['assigned_to' => $outsider->id], ['site_id' => $siteB->id]] as $bad) {
                try {
                    app(LeadService::class)->create($contact, $bad);
                    $this->fail('Referință din altă firmă acceptată: '.json_encode($bad));
                } catch (ValidationException) {
                    $this->addToAssertionCount(1);
                }
            }
        });
    }

    public function test_agent_configuration_is_validated_and_has_safe_defaults(): void
    {
        $org = $this->makeOrganization('Firma A', null, 'pro');
        [$site] = $this->makeSite($org, 'firma-a.ro');
        $this->tenant()->runAs($org, function () use ($site): void {
            $agent = app(AgentService::class)->create(['name' => 'Asistent', 'site_id' => $site->id, 'system_configuration' => [
                'tone' => 'friendly', 'languages' => ['RO', 'en'], 'lead_rules' => ['required_fields' => ['name', 'email'], 'require_consent' => false],
                'business_hours' => ['timezone' => 'Europe/Bucharest', 'days' => ['mon' => [['09:00', '17:00']]]],
            ]]);
            $system = $agent->system_configuration;
            $this->assertSame('friendly', $system['tone']);
            $this->assertSame(['ro', 'en'], $system['languages']);
            $this->assertTrue($system['lead_rules']['require_consent'], 'acordul pentru lead nu se poate dezactiva');
            $this->assertSame('claude-opus-5-5', $agent->model_configuration['model']);
            $this->assertSame('draft', $agent->status->value);
            $this->assertTrue(AuditLog::query()->where('action', 'agent.created')->exists());

            foreach ([
                ['model_configuration' => ['api_key' => 'sk-123']],
                ['system_configuration' => ['handoff_rules' => ['webhook_secret' => 'x']]],
                ['system_configuration' => ['allowed_actions' => ['send_bulk_campaign']]],
                ['system_configuration' => ['business_hours' => ['days' => ['mon' => [['25:00', '10:00']]]]]],
                ['model_configuration' => ['model' => 'model-necunoscut']],
            ] as $bad) {
                try {
                    app(AgentService::class)->create(['name' => 'Rău'] + $bad);
                    $this->fail('Configurație invalidă acceptată: '.json_encode($bad));
                } catch (ValidationException) {
                    $this->addToAssertionCount(1);
                }
            }
        });
    }

    public function test_agent_site_must_belong_to_organization_and_plan_limit_applies(): void
    {
        $a = $this->makeOrganization('Firma A'); // start: 1 agent
        [$siteB] = $this->makeSite($this->makeOrganization('Firma B'), 'firma-b.ro');
        $this->tenant()->runAs($a, function () use ($siteB): void {
            try {
                app(AgentService::class)->create(['name' => 'X', 'site_id' => $siteB->id]);
                $this->fail('Site din altă firmă acceptat.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
            app(AgentService::class)->create(['name' => 'Unul']);
            $this->expectException(ValidationException::class);
            app(AgentService::class)->create(['name' => 'Al doilea']);
        });
    }

    public function test_invitations_roles_and_last_owner_protection(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $org = $this->makeOrganization('Firma A', $owner, 'pro');
        $members = app(MembershipService::class);

        $this->tenant()->runAs($org, function () use ($owner, $members): void {
            $adminMembership = $members->invite($owner, 'admin@example.test', 'Admin', OrgRole::Admin);
            $admin = $adminMembership->user;
            Notification::assertSentTo($admin, ResetPassword::class);
            $this->assertTrue(AuditLog::query()->where('action', 'user.invited')->exists());

            // un admin nu poate crea proprietari și nu poate modifica proprietarul
            foreach ([fn () => $members->invite($admin, 'x@example.test', 'X', OrgRole::Owner),
                fn () => $members->changeRole($admin, Membership::query()->where('user_id', $owner->id)->first(), OrgRole::Viewer)] as $attempt) {
                try {
                    $attempt();
                    $this->fail('Escaladare permisă.');
                } catch (ValidationException) {
                    $this->addToAssertionCount(1);
                }
            }

            $agent = $members->invite($admin, 'agent@example.test', 'Operator', OrgRole::Agent);
            $members->changeRole($admin, $agent, OrgRole::Viewer);
            $this->assertSame(['from' => 'agent', 'to' => 'viewer'], AuditLog::query()->where('action', 'role.changed')->value('meta'));

            $this->expectException(ValidationException::class);
            $members->remove($owner, Membership::query()->where('user_id', $owner->id)->first()); // ultimul proprietar
        });
    }

    public function test_event_processor_marks_events_processed(): void
    {
        $org = $this->makeOrganization('Firma A');
        $this->tenant()->runAs($org, fn () => app(ContactService::class)->create(['first_name' => 'Ion'], ContactSource::Manual));

        Artisan::call('vitim:events');

        $this->assertSame(0, DomainEvent::withoutTenancy()->whereNull('processed_at')->count());
    }
}
