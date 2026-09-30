<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ContactSource;
use App\Enums\OrgRole;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\ContactService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PortalTest extends TestCase
{
    use RefreshDatabase;

    private function member(Organization $org, OrgRole $role): User
    {
        $user = User::factory()->create();
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $user->id, 'role' => $role]));

        return $user;
    }

    public function test_every_portal_page_renders_for_owner(): void
    {
        $org = $this->makeOrganization('Firma A');
        $this->actingAs($this->member($org, OrgRole::Owner));
        $base = "/app/{$org->slug}";
        foreach (['', '/agent', '/contacte', '/contacte/nou', '/leaduri', '/setari', '/in-curand/inbox', '/in-curand/campanii',
            '/in-curand/automatizari', '/in-curand/analytics', '/in-curand/integrari'] as $path) {
            $this->get($base.$path)->assertOk();
        }
        $this->get("{$base}/in-curand/nu-exista")->assertNotFound();
        $this->get("{$base}/in-curand/campanii")->assertSee('În dezvoltare');
    }

    public function test_contact_and_lead_flow_through_the_ui(): void
    {
        $org = $this->makeOrganization('Firma A');
        $this->actingAs($this->member($org, OrgRole::Agent));
        $base = "/app/{$org->slug}";

        $this->post("{$base}/contacte", ['first_name' => 'Ion', 'last_name' => 'Popescu', 'email' => 'ion@example.test', 'phone' => '0722000001'])->assertRedirect();
        $contact = $this->tenant()->runAs($org, fn () => Contact::query()->firstOrFail());
        $this->get("{$base}/contacte?q=popescu")->assertSee('Ion Popescu');
        $this->post("{$base}/contacte", ['email' => 'ION@example.test'])->assertSessionHasErrors('email')->assertSessionHas('duplicate', $contact->id);

        $this->post("{$base}/contacte/{$contact->id}/consimtamant", ['channel' => 'email', 'purpose' => 'marketing', 'status' => 'granted', 'source' => 'telefon'])->assertRedirect();
        $this->get("{$base}/contacte/{$contact->id}")->assertOk()->assertSee('acordat');

        $this->post("{$base}/leaduri", ['contact_id' => $contact->id, 'intent' => 'quote_request', 'summary' => 'Vrea ofertă'])->assertRedirect();
        $lead = $this->tenant()->runAs($org, fn () => Lead::query()->firstOrFail());
        $this->put("{$base}/leaduri/{$lead->id}", ['status' => 'qualified', 'assigned_to' => ''])->assertRedirect();
        $this->assertSame('qualified', $lead->fresh()->status->value);

        // operatorul nu poate șterge (doar proprietarul)
        $this->delete("{$base}/contacte/{$contact->id}")->assertForbidden();
    }

    public function test_viewer_sees_but_cannot_change(): void
    {
        $org = $this->makeOrganization('Firma A');
        $contact = $this->tenant()->runAs($org, fn () => app(ContactService::class)->create(['first_name' => 'Ion'], ContactSource::Manual));
        $this->actingAs($this->member($org, OrgRole::Viewer));
        $base = "/app/{$org->slug}";

        $this->get("{$base}/contacte/{$contact->id}")->assertOk()->assertDontSee('Editează');
        $this->get("{$base}/contacte/nou")->assertForbidden();
        $this->post("{$base}/contacte", ['first_name' => 'X'])->assertForbidden();
        $this->post("{$base}/agent", ['name' => 'X'])->assertForbidden();
        $this->put("{$base}/setari/firma", ['name' => 'Alt nume', 'country' => 'RO', 'timezone' => 'Europe/Bucharest', 'default_language' => 'ro'])->assertForbidden();
    }

    public function test_portal_urls_with_ids_from_another_organization_are_denied(): void
    {
        $a = $this->makeOrganization('Firma A');
        $b = $this->makeOrganization('Firma B');
        $contactB = $this->tenant()->runAs($b, fn () => app(ContactService::class)->create(['first_name' => 'Secret'], ContactSource::Manual));
        $this->actingAs($this->member($a, OrgRole::Owner));

        $this->get("/app/{$a->slug}/contacte/{$contactB->id}")->assertNotFound();
        $this->put("/app/{$a->slug}/contacte/{$contactB->id}", ['first_name' => 'Modificat'])->assertNotFound();
        $this->delete("/app/{$a->slug}/contacte/{$contactB->id}")->assertNotFound();
        $this->get("/app/{$b->slug}")->assertNotFound();
        $this->get("/app/{$b->slug}/contacte")->assertNotFound();
        $this->assertSame('Secret', $contactB->fresh()->first_name);
    }

    public function test_vitim_admin_pages_show_real_numbers(): void
    {
        $org = $this->makeOrganization('Firma A');
        $this->tenant()->runAs($org, fn () => app(ContactService::class)->create(['first_name' => 'Ion'], ContactSource::Manual));
        $this->actingAs($this->staff());

        $this->get('/admin')->assertOk()->assertSee('Firma A')->assertSee('Conversații')->assertSee('contacts_created');
        foreach (['/admin/site-uri', '/admin/agenti', '/admin/utilizatori', "/admin/clienti/{$org->slug}", "/app/{$org->slug}", "/app/{$org->slug}/contacte"] as $path) {
            $this->get($path)->assertOk();
        }
    }
}
