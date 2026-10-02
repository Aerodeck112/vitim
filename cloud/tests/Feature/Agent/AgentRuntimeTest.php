<?php

declare(strict_types=1);

namespace Tests\Feature\Agent;

use App\Ai\AgentReply;
use App\Ai\AgentRuntime;
use App\Ai\AiClient;
use App\Ai\AiUnavailable;
use App\Ai\Cost;
use App\Ai\PromptBuilder;
use App\Enums\Channel;
use App\Enums\ContactSource;
use App\Models\Agent;
use App\Models\AiTurn;
use App\Models\Contact;
use App\Models\ContactConsent;
use App\Models\Conversation;
use App\Models\DomainEvent;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Organization;
use App\Models\ToolExecution;
use App\Services\AgentService;
use App\Services\ContactService;
use App\Services\UsageMeter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeAiClient;
use Tests\TestCase;

final class AgentRuntimeTest extends TestCase
{
    use RefreshDatabase;

    private FakeAiClient $ai;

    private const LEAD = [
        'name' => 'Ion Pop', 'phone' => '0722 123 456', 'email' => '', 'company' => '', 'requested_service' => 'Schimb distribuție',
        'product' => 'Ford Focus 2015 1.6', 'budget' => '', 'preferred_date' => 'sâmbătă', 'notes' => '', 'intent' => 'appointment',
        'summary' => 'Vrea schimb de distribuție sâmbătă.', 'consent' => true,
    ];

    /** @param list<array<string, mixed>|AiUnavailable> $responses */
    private function fake(array $responses): void
    {
        $this->ai = new FakeAiClient($responses);
        $this->app->instance(AiClient::class, $this->ai);
    }

    /** @param array<string, mixed> $system */
    private function agent(Organization $org, array $system = [], string $status = 'active'): Agent
    {
        return $this->tenant()->runAs($org, fn () => app(AgentService::class)->create([
            'name' => 'Asistent', 'status' => $status, 'template' => 'auto_service',
            'system_configuration' => $system + ['business_facts' => 'Schimb distribuție: de la 900 lei manopera.', 'contact_line' => '0740 000 000'],
        ]));
    }

    private function conversation(Organization $org, Agent $agent, bool $test = false): Conversation
    {
        return $this->tenant()->runAs($org, fn () => Conversation::create([
            'agent_id' => $agent->id, 'channel' => Channel::Web, 'status' => 'open', 'mode' => 'ai', 'is_test' => $test,
        ]));
    }

    private function say(Organization $org, Conversation $conversation, string $text): AgentReply
    {
        return $this->tenant()->runAs($org, fn () => app(AgentRuntime::class)->reply($conversation, $text, '203.0.113.5', 'PHPUnit'));
    }

    public function test_answers_and_records_history_messages_and_cost(): void
    {
        $this->fake([FakeAiClient::text('Schimbul de distribuție costă de la 900 lei manopera.'), FakeAiClient::text('Cu plăcere!')]);
        $org = $this->makeOrganization('Service Auto');
        $agent = $this->agent($org);
        $conversation = $this->conversation($org, $agent);

        $reply = $this->say($org, $conversation, 'Cât costă distribuția?');
        $this->assertSame('ok', $reply->status);
        $this->assertStringContainsString('900 lei', $reply->text);
        $this->say($org, $conversation, 'Mulțumesc');

        // a doua cerere retrimite istoricul neschimbat: tura asistentului e exact răspunsul primit
        $second = $this->ai->requests[1];
        $this->assertSame('Cât costă distribuția?', $second['messages'][0]['content']);
        $this->assertSame('sig', $second['messages'][1]['raw']['content'][0]['signature']);
        $this->assertSame('Mulțumesc', $second['messages'][2]['content']);
        $this->assertSame($this->ai->requests[0]['system'], $second['system'], 'prefixul cache-uit e stabil');
        $this->assertStringContainsString('900 lei', $second['system']);

        $this->tenant()->runAs($org, function () use ($conversation): void {
            $this->assertSame(4, AiTurn::query()->count());
            $this->assertSame(['inbound', 'outbound', 'inbound', 'outbound'], Message::query()->orderBy('id')->pluck('direction')->all());
            $expected = 2 * Cost::microUsd('claude-opus-5-5', ['input_tokens' => 100, 'output_tokens' => 50, 'cache_read_input_tokens' => 2000]);
            $this->assertSame($expected, (int) $conversation->fresh()->ai_cost_micro_usd);
            $usage = app(UsageMeter::class);
            $this->assertSame($expected, $usage->thisMonth('ai_cost_micro_usd'));
            $this->assertSame(2, $usage->thisMonth('ai_messages'));
            $this->assertSame(1, $usage->thisMonth('conversations_started'));
        });
    }

    public function test_create_lead_saves_contact_consent_and_lead_linked_to_conversation(): void
    {
        $this->fake([FakeAiClient::tool('create_lead', self::LEAD, 'Salvez cererea.'), FakeAiClient::text('Gata, te sunăm azi.')]);
        $org = $this->makeOrganization('Service Auto');
        $agent = $this->agent($org);
        $conversation = $this->conversation($org, $agent);

        $reply = $this->say($org, $conversation, 'Da, sunați-mă.');
        $this->assertSame('Gata, te sunăm azi.', $reply->text);
        $this->assertSame('ok', $reply->tools[0]->status);

        $result = $this->ai->requests[1]['messages'][2]['content'][0];
        $this->assertSame('tool_result', $result['type']);
        $this->assertFalse($result['is_error']);

        $this->tenant()->runAs($org, function () use ($conversation, $agent): void {
            $lead = Lead::query()->sole();
            $this->assertSame($conversation->id, $lead->conversation_id);
            $this->assertSame($agent->id, $lead->agent_id);
            $this->assertSame('appointment', $lead->intent->value);
            $this->assertStringContainsString('Ford Focus', (string) $lead->summary);
            $contact = Contact::query()->sole();
            $this->assertSame('+40722123456', $contact->phone);
            $this->assertSame(ContactSource::WebsiteAi, $contact->source);
            $consent = ContactConsent::query()->sole();
            $this->assertSame(['phone', 'service', 'granted'], [$consent->channel->value, $consent->purpose->value, $consent->status->value]);
            $this->assertSame('203.0.113.5', $consent->ip_address);
            $this->assertSame($contact->id, $conversation->fresh()->contact_id);
        });
    }

    public function test_second_create_lead_in_same_conversation_updates_instead_of_duplicating(): void
    {
        $this->fake([
            FakeAiClient::tool('create_lead', self::LEAD), FakeAiClient::text('Salvat.'),
            FakeAiClient::tool('create_lead', ['summary' => 'Și ITP.'] + self::LEAD), FakeAiClient::text('Actualizat.'),
        ]);
        $org = $this->makeOrganization('Service Auto');
        $conversation = $this->conversation($org, $this->agent($org));
        $this->say($org, $conversation, 'Da');
        $this->say($org, $conversation, 'Și ITP, da');

        $this->tenant()->runAs($org, function (): void {
            $this->assertSame(1, Lead::query()->count());
            $this->assertStringStartsWith('Și ITP.', (string) Lead::query()->sole()->summary);
            $this->assertSame(1, Contact::query()->count());
        });
    }

    public function test_lead_is_rejected_without_consent_or_required_fields(): void
    {
        $this->fake([
            FakeAiClient::tool('create_lead', ['consent' => false] + self::LEAD), FakeAiClient::text('Ești de acord să te sunăm?'),
            FakeAiClient::tool('create_lead', ['requested_service' => '', 'product' => ''] + self::LEAD), FakeAiClient::text('Ce lucrare dorești?'),
            FakeAiClient::tool('create_lead', ['phone' => '123'] + self::LEAD), FakeAiClient::text('Verifici numărul?'),
        ]);
        $org = $this->makeOrganization('Service Auto');
        $conversation = $this->conversation($org, $this->agent($org));

        $this->assertSame('rejected', $this->say($org, $conversation, 'Ion, 0722123456')->tools[0]->status);
        $this->assertTrue($this->ai->requests[1]['messages'][2]['content'][0]['is_error']);
        $this->assertStringContainsString('acord', $this->ai->requests[1]['messages'][2]['content'][0]['content']);

        $this->assertSame('rejected', $this->say($org, $conversation, 'da')->tools[0]->status);
        $this->assertStringContainsString('serviciul dorit', $this->ai->requests[3]['messages'][6]['content'][0]['content']);

        $this->assertSame('rejected', $this->say($org, $conversation, 'da')->tools[0]->status);
        $this->tenant()->runAs($org, fn () => $this->assertSame(0, Lead::query()->count() + Contact::query()->count()));
    }

    public function test_test_conversations_do_not_create_real_data(): void
    {
        $this->fake([
            FakeAiClient::tool('create_lead', self::LEAD), FakeAiClient::text('Salvat.'),
            FakeAiClient::tool('request_human', ['reason' => 'visitor_request', 'summary' => 'Vrea un om.']), FakeAiClient::text('Un coleg preia.'),
        ]);
        $org = $this->makeOrganization('Service Auto');
        $conversation = $this->conversation($org, $this->agent($org, status: 'draft'), test: true);

        $this->assertSame('dry_run', $this->say($org, $conversation, 'Da')->tools[0]->status);
        $this->assertSame('dry_run', $this->say($org, $conversation, 'Vreau un om')->tools[0]->status);
        $this->tenant()->runAs($org, function () use ($conversation): void {
            $this->assertSame(0, Lead::query()->count() + Contact::query()->count() + ContactConsent::query()->count());
            $this->assertSame('open', $conversation->fresh()->status->value);
            $this->assertSame(0, app(UsageMeter::class)->thisMonth('conversations_started'));
        });
    }

    public function test_tools_cannot_reach_another_organization(): void
    {
        $orgA = $this->makeOrganization('Firma A');
        $orgB = $this->makeOrganization('Firma B');
        $contactA = $this->tenant()->runAs($orgA, fn () => app(ContactService::class)->create(['first_name' => 'Ion', 'phone' => '0722123456'], ContactSource::Manual));

        // modelul încearcă să trimită o firmă / un contact străin: argumentele în plus sunt ignorate
        $this->fake([FakeAiClient::tool('create_lead', self::LEAD + ['organization_id' => $orgA->id, 'contact_id' => $contactA->id]), FakeAiClient::text('Salvat.')]);
        $conversation = $this->conversation($orgB, $this->agent($orgB));
        $this->assertSame('ok', $this->say($orgB, $conversation, 'Da')->tools[0]->status);

        $this->tenant()->runAs($orgA, function () use ($contactA): void {
            $this->assertSame(0, Lead::query()->count());
            $this->assertSame([$contactA->id], Contact::query()->pluck('id')->all());
        });
        $this->tenant()->runAs($orgB, function () use ($contactA, $orgB): void {
            $lead = Lead::query()->sole();
            $this->assertNotSame($contactA->id, $lead->contact_id);
            $this->assertSame($orgB->id, $lead->organization_id);
        });
    }

    public function test_actions_not_allowed_for_the_agent_are_neither_offered_nor_executed(): void
    {
        $this->fake([FakeAiClient::tool('create_lead', self::LEAD), FakeAiClient::text('Te rog sună-ne.')]);
        $org = $this->makeOrganization('Service Auto');
        $conversation = $this->conversation($org, $this->agent($org, ['allowed_actions' => ['request_human']]));

        $reply = $this->say($org, $conversation, 'Da');
        $this->assertSame(['request_human'], array_column($this->ai->requests[0]['tools'], 'name'));
        $this->assertSame('rejected', $reply->tools[0]->status);
        $this->tenant()->runAs($org, fn () => $this->assertSame(0, Lead::query()->count()));
    }

    public function test_request_human_marks_conversation_pending_and_emits_event(): void
    {
        $this->fake([FakeAiClient::tool('request_human', ['reason' => 'complaint', 'summary' => 'Reclamație factură.']), FakeAiClient::text('Un coleg preia.')]);
        $org = $this->makeOrganization('Service Auto');
        $conversation = $this->conversation($org, $this->agent($org));
        $this->say($org, $conversation, 'Vreau să fac o reclamație');

        $this->tenant()->runAs($org, function () use ($conversation): void {
            $this->assertSame('pending', $conversation->fresh()->status->value);
            $event = DomainEvent::query()->where('type', 'conversation.human_requested')->sole();
            $this->assertSame('complaint', $event->payload['reason']);
        });
    }

    public function test_cost_cap_and_conversation_cap_stop_ai_calls(): void
    {
        $this->fake([FakeAiClient::text('Bună!')]);
        $org = $this->makeOrganization('Service Auto'); // plan start: plafon $40, 300 conversații
        $agent = $this->agent($org);
        $ongoing = $this->conversation($org, $agent);
        $this->say($org, $ongoing, 'Salut');

        $this->tenant()->runAs($org, fn () => app(UsageMeter::class)->increment('conversations_started', 299));
        $new = $this->conversation($org, $agent);
        $capped = $this->say($org, $new, 'Bună');
        $this->assertSame('capped', $capped->status);
        $this->assertStringContainsString('0740 000 000', $capped->text);
        $this->assertSame('ok', $this->say($org, $ongoing, 'Încă o întrebare')->status, 'conversațiile începute continuă');

        $this->tenant()->runAs($org, fn () => app(UsageMeter::class)->increment('ai_cost_micro_usd', 40_000_000));
        $this->assertSame('capped', $this->say($org, $ongoing, 'Și acum?')->status);
        $this->assertCount(2, $this->ai->requests, 'peste plafon nu se apelează modelul');
    }

    public function test_refusal_errors_and_inactive_agent_give_safe_replies(): void
    {
        $this->fake([FakeAiClient::refusal(), new AiUnavailable('unavailable'), new AiUnavailable('unavailable')]);
        $org = $this->makeOrganization('Service Auto', null, 'pro');
        $agent = $this->agent($org, ['engine' => 'claude']);
        $conversation = $this->conversation($org, $agent);

        $refused = $this->say($org, $conversation, 'ceva');
        $this->assertSame('refused', $refused->status);
        $unavailable = $this->say($org, $conversation, 'altceva');
        $this->assertSame('unavailable', $unavailable->status);
        $this->assertStringContainsString('0740 000 000', $unavailable->text);
        // tura goală a refuzului nu intră în istoric
        $this->tenant()->runAs($org, fn () => $this->assertSame(['user', 'user'], AiTurn::query()->orderBy('id')->pluck('role')->all()));

        // modul automat: dacă Claude nu răspunde, răspunsul vine din informațiile firmei
        $this->tenant()->runAs($org, fn () => app(AgentService::class)->update($agent, ['system_configuration' => ['engine' => 'auto'] + $agent->system_configuration]));
        $auto = $this->conversation($org, $agent);
        $fallback = $this->say($org, $auto, 'Cât costă schimbul de distribuție?');
        $this->assertSame(['ok', 'local'], [$fallback->status, $fallback->engine]);
        $this->assertStringContainsString('900 lei', $fallback->text);

        $draft = $this->conversation($org, $this->agent($org, status: 'draft'));
        $this->assertSame('inactive', $this->say($org, $draft, 'alo')->status);
        $this->assertCount(3, $this->ai->requests);
    }

    public function test_agent_versions_and_templates(): void
    {
        $org = $this->makeOrganization('Service Auto');
        $agent = $this->agent($org);
        $this->tenant()->runAs($org, function () use ($agent): void {
            $this->assertSame('auto_service', $agent->template);
            $this->assertContains('requested_service', $agent->system_configuration['lead_rules']['required_fields']);
            $this->assertSame([1], $agent->versions()->pluck('version')->all());

            $service = app(AgentService::class);
            $service->update($agent, ['system_configuration' => ['tone' => 'formal'] + $agent->system_configuration]);
            $service->update($agent, ['name' => 'Alt nume']);
            $this->assertSame([2, 1], $agent->versions()->pluck('version')->all());
            $this->assertSame('formal', $agent->versions()->first()->system_configuration['tone']);
        });
    }

    public function test_prompt_is_deterministic_and_contains_company_facts(): void
    {
        $org = $this->makeOrganization('Service Auto');
        $agent = $this->agent($org, ['instructions' => 'Nu lucrăm duminica.']);
        $prompt = $this->tenant()->runAs($org, fn () => app(PromptBuilder::class)->build($org, $agent));
        $this->assertSame($prompt, $this->tenant()->runAs($org, fn () => app(PromptBuilder::class)->build($org, $agent)));
        $this->assertStringContainsString('900 lei', $prompt);
        $this->assertStringContainsString('Nu lucrăm duminica.', $prompt);
        $this->assertStringContainsString('create_lead', $prompt);
    }

    public function test_cost_is_computed_from_reported_tokens(): void
    {
        // 1M input × $4 + 1M output × $20 = $24
        $this->assertSame(24_000_000, Cost::microUsd('claude-opus-5-5', ['input_tokens' => 1_000_000, 'output_tokens' => 1_000_000]));
        $this->assertSame(400_000, Cost::microUsd('claude-opus-5-5', ['cache_read_input_tokens' => 1_000_000]));
    }

    public function test_tool_execution_log_is_tenant_scoped(): void
    {
        $this->fake([FakeAiClient::tool('create_lead', self::LEAD), FakeAiClient::text('ok')]);
        $orgA = $this->makeOrganization('Firma A');
        $orgB = $this->makeOrganization('Firma B');
        $this->say($orgA, $this->conversation($orgA, $this->agent($orgA)), 'Da');

        $this->tenant()->runAs($orgA, fn () => $this->assertSame(1, ToolExecution::query()->count()));
        $this->tenant()->runAs($orgB, fn () => $this->assertSame(0, ToolExecution::query()->count() + AiTurn::query()->count()));
    }
}
