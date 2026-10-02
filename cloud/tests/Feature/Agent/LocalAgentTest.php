<?php

declare(strict_types=1);

namespace Tests\Feature\Agent;

use App\Ai\AgentReply;
use App\Ai\AgentRuntime;
use App\Ai\AiClient;
use App\Ai\Local\Knowledge;
use App\Enums\Channel;
use App\Models\Agent;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Organization;
use App\Services\AgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeAiClient;
use Tests\TestCase;

/** Agentul fără cheie Claude: răspunde din informațiile firmei, adună lead-uri cu acord, anunță echipa. */
final class LocalAgentTest extends TestCase
{
    use RefreshDatabase;

    private const FACTS = <<<'TXT'
Podreg construiește cabane din lemn din 2005. Firma a fost înființată de familia Popescu în Suceava și are o echipă de 25 de oameni. Am finalizat peste 400 de proiecte în România și în Austria.

Prețuri: cabanele la roșu pornesc de la 375 EUR/mp, la cheie de la 690 EUR/mp. Transportul se calculează separat.
Program: luni–vineri 8–17, sâmbătă 9–13. Duminica e închis.
Adresă: Str. Pădurii 12, Suceava. Showroom deschis în program.

Livrare
Livrăm în toată țara cu camioanele noastre. Montajul se face cu echipa proprie.

Cât durează construcția?
O cabană de 60 mp este gata în 6–8 săptămâni de la semnarea contractului.

Î: Oferiți garanție?
R: Da, 10 ani garanție la structură și 2 ani la finisaje.
TXT;

    private FakeAiClient $ai;

    /** @return array{0: Organization, 1: Agent} */
    private function setup_(array $system = []): array
    {
        $this->ai = new FakeAiClient([]);
        $this->ai->configured = false; // ca pe server fără ANTHROPIC_API_KEY
        $this->app->instance(AiClient::class, $this->ai);
        $org = $this->makeOrganization('Podreg');
        $agent = $this->tenant()->runAs($org, fn () => app(AgentService::class)->create([
            'name' => 'Asistent Podreg', 'status' => 'active',
            'system_configuration' => $system + ['business_facts' => self::FACTS, 'contact_line' => '0744 599 333', 'greeting' => 'Bună! Sunt asistentul Podreg.'],
        ]));

        return [$org, $agent];
    }

    private function conversation(Organization $org, Agent $agent): Conversation
    {
        return $this->tenant()->runAs($org, fn () => Conversation::create(['agent_id' => $agent->id, 'channel' => Channel::Web, 'status' => 'open', 'mode' => 'ai']));
    }

    private function say(Organization $org, Conversation $c, string $text): AgentReply
    {
        return $this->tenant()->runAs($org, fn () => app(AgentRuntime::class)->reply($c->fresh(), $text, '203.0.113.5', 'PHPUnit'));
    }

    public function test_answers_from_the_company_text_without_claude(): void
    {
        [$org, $agent] = $this->setup_();
        $c = $this->conversation($org, $agent);

        $cases = [
            'Cât costă o cabană?' => '375 EUR/mp',
            'cat costa la cheie' => '690 EUR/mp',
            'Ce program aveți sâmbăta?' => 'sâmbătă 9–13',
            'unde vă găsesc?' => 'Pădurii 12',
            'Livrați și în Cluj?' => 'toată țara',
            'În cât timp e gata o cabană?' => '6–8 săptămâni',
            'Aveți garanție?' => '10 ani garanție',
            'De când existați?' => '2005',
            'cine a infiintat firma' => 'familia Popescu',
        ];
        foreach ($cases as $question => $expected) {
            $reply = $this->say($org, $c, $question);
            $this->assertSame(['ok', 'local'], [$reply->status, $reply->engine], $question);
            $this->assertStringContainsString($expected, $reply->text, $question);
        }
        $this->assertSame('Bună! Sunt asistentul Podreg.', $this->say($org, $c, 'Bună ziua')->text);
        $this->assertStringContainsString('Cu plăcere', $this->say($org, $c, 'Mulțumesc!')->text);
        $this->assertCount(0, $this->ai->requests, 'fără apeluri către Claude');
        $this->assertSame(0, $this->tenant()->runAs($org, fn () => (int) Conversation::query()->find($c->id)->ai_cost_micro_usd));
    }

    public function test_collects_a_lead_with_explicit_consent(): void
    {
        [$org, $agent] = $this->setup_();
        $c = $this->conversation($org, $agent);

        $unknown = $this->say($org, $c, 'Faceți și piscine?');
        $this->assertStringContainsString('telefon sau un email', $unknown->text);
        $this->assertStringContainsString('prețuri', $unknown->text, 'propune subiectele cunoscute');

        $this->assertStringContainsString('Cum te numești', $this->say($org, $c, '0722 123 456')->text);
        $consent = $this->say($org, $c, 'Ion Pop');
        $this->assertStringContainsString('Ești de acord să te contactăm la 0722 123 456', $consent->text);
        $this->tenant()->runAs($org, fn () => $this->assertSame(0, Lead::query()->count()), 'fără acord nu se salvează nimic');

        $saved = $this->say($org, $c, 'Da');
        $this->assertStringContainsString('Mulțumesc, Ion!', $saved->text);
        $this->tenant()->runAs($org, function () use ($c): void {
            $lead = Lead::query()->with('contact')->sole();
            $this->assertSame($c->id, $lead->conversation_id);
            $this->assertSame('+40722123456', $lead->contact->phone);
            $this->assertStringContainsString('piscine', (string) $lead->summary);
        });

        // refuz: datele nu se păstrează
        $other = $this->conversation($org, $agent);
        $this->say($org, $other, 'Sunt Maria Ionescu, 0733 222 111');
        $this->assertStringContainsString('nu păstrez datele', $this->say($org, $other, 'nu')->text);
        $this->tenant()->runAs($org, fn () => $this->assertSame(1, Lead::query()->count()));

        // acord dat direct în mesaj
        $direct = $this->conversation($org, $agent);
        $this->assertStringContainsString('Mulțumesc, Ana!', $this->say($org, $direct, 'Ana Marin, 0744 111 222, sunați-mă vă rog')->text);
        $this->tenant()->runAs($org, fn () => $this->assertSame(2, Lead::query()->count()));
    }

    public function test_hands_off_to_a_human_and_respects_settings(): void
    {
        [$org, $agent] = $this->setup_(['tone' => 'formal']);
        $c = $this->conversation($org, $agent);
        $reply = $this->say($org, $c, 'Vreau să vorbesc cu un om');
        $this->assertStringContainsString('Am anunțat un coleg', $reply->text);
        $this->assertStringContainsString('ne puteți lăsa', $reply->text, 'tonul formal');
        $this->tenant()->runAs($org, fn () => $this->assertSame('pending', Conversation::query()->find($c->id)->status->value));

        // „Doar Claude” fără cheie: nu răspunde din text
        [$org2, $agent2] = [$org, $agent];
        $this->tenant()->runAs($org2, fn () => app(AgentService::class)->update($agent2, ['system_configuration' => ['engine' => 'claude'] + $agent2->system_configuration]));
        $this->ai->configured = true;
        $claude = $this->say($org2, $this->conversation($org2, $agent2->fresh()), 'Cât costă?');
        $this->assertNull($claude->engine);
    }

    public function test_knowledge_parsing_of_free_text(): void
    {
        $k = new Knowledge(self::FACTS);
        $this->assertContains('Prețuri', $k->topics());
        $this->assertContains('Livrare', $k->topics());
        $this->assertSame([], (new Knowledge(''))->search('preț'));
        $this->assertStringContainsString('375', (new Knowledge('Toate cabanele costă de la 375 EUR/mp.'))->search('care e pretul')[0]['body']);
    }
}
