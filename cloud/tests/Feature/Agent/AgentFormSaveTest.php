<?php

declare(strict_types=1);

namespace Tests\Feature\Agent;

use App\Enums\OrgRole;
use App\Models\Agent;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Formularul agentului trimis exact cum îl trimite browserul (toate câmpurile din pagină). */
final class AgentFormSaveTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> câmpurile primului formular din pagină, ca în browser */
    private function formFields(string $html, string $action): array
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xp = new \DOMXPath($dom);
        $form = $xp->query("//form[contains(@action, '{$action}')]")->item(0);
        $this->assertNotNull($form, 'formularul lipsește');
        $data = [];
        foreach ($xp->query('.//input|.//textarea|.//select', $form) as $el) {
            $name = $el->getAttribute('name');
            if ($name === '') {
                continue;
            }
            $type = $el->getAttribute('type');
            if (in_array($type, ['checkbox', 'radio'], true) && ! $el->hasAttribute('checked')) {
                continue;
            }
            $value = match ($el->nodeName) {
                'textarea' => $el->textContent,
                'select' => (function () use ($xp, $el) {
                    $sel = $xp->query('.//option[@selected]', $el)->item(0) ?? $xp->query('.//option', $el)->item(0);

                    return $sel?->getAttribute('value') ?? '';
                })(),
                default => $el->getAttribute('value'),
            };
            if (str_ends_with($name, '[]')) {
                $data[substr($name, 0, -2)][] = $value;
            } else {
                $data[$name] = $value;
            }
        }

        return $data;
    }

    public function test_every_agent_kind_saves_from_the_page_as_the_browser_sends_it(): void
    {
        $org = $this->makeOrganization('Podreg');
        [$site] = $this->makeSite($org, 'podreg.ro');
        $owner = User::factory()->create();
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $owner->id, 'role' => OrgRole::Owner]));
        $this->actingAs($owner);
        $this->save($org, $site);
        // echipa VITIM (super admin) lucrează în panoul clientului
        $this->actingAs($this->staff());
        $this->save($org, $site);
    }

    private function save($org, $site): void
    {

        // agent creat din panou (cum face clientul), apoi fiecare template
        $this->post("/app/{$org->slug}/agent", ['name' => 'Asistent'])->assertRedirect();
        foreach (['generic'] as $t) {
            $this->post("/app/{$org->slug}/agent", ['name' => "A {$t}", 'template' => $t]);
        }
        $ids = $this->tenant()->runAs($org, fn () => Agent::query()->pluck('id'));
        $this->assertGreaterThan(0, $ids->count());
        foreach ($ids as $id) {
            $html = $this->get("/app/{$org->slug}/agent/{$id}")->assertOk()->getContent();
            $data = $this->formFields($html, "/agent/{$id}");
            $data['business_facts'] = str_repeat("Istoria firmei: cabane din lemn din 2005.\r\n", 1200); // ~50.000 caractere
            $data['site_id'] = (string) $site->id;
            $data['status'] = 'active';
            $data['engine'] = 'local';
            $data['languages'] = '';
            $data['default_language'] = '';
            $this->put("/app/{$org->slug}/agent/{$id}", $data)->assertSessionHasNoErrors();
            $agent = $this->tenant()->runAs($org, fn () => Agent::query()->with('site')->find($id));
            $this->assertStringContainsString('cabane din lemn', (string) $agent->system_configuration['business_facts'], "agentul {$id}");
            $this->assertSame(['active', 'local', ['ro'], 'ro'], [$agent->status->value, $agent->system_configuration['engine'], $agent->system_configuration['languages'], $agent->default_language]);

            // a doua salvare: acum pagina are și setările chatului, în același formular
            $data = $this->formFields($this->get("/app/{$org->slug}/agent/{$id}")->assertOk()->getContent(), "/agent/{$id}");
            $this->assertArrayHasKey('widget_form', $data);
            $data['welcome_title'] = 'Bună ziua!';
            $data['greeting'] = 'Salut de la Podreg';
            $this->put("/app/{$org->slug}/agent/{$id}", $data)->assertSessionHasNoErrors();
            $agent = $this->tenant()->runAs($org, fn () => Agent::query()->with('site')->find($id));
            $this->assertSame('Salut de la Podreg', $agent->system_configuration['greeting']);
            $this->assertSame('Bună ziua!', $agent->site->widget_config['welcome_title']);

            // eroare: mesaj în română, iar ce a scris utilizatorul rămâne în pagină
            $data['greeting'] = 'Text nou nesalvat';
            $data['hours_start'] = '9';
            $this->from("/app/{$org->slug}/agent/{$id}")->followingRedirects()->put("/app/{$org->slug}/agent/{$id}", $data)
                ->assertSee('Online de la', false)->assertSee('trebuie să fie o oră')->assertSee('Text nou nesalvat');
        }
    }
}
