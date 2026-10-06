<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Enums\Channel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\ContactSource;
use App\Enums\OrgRole;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\ContactListMember;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Suppression;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\ContactService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ContactBulkTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Organization, 1: User} */
    private function firm(string $name, OrgRole $role = OrgRole::Owner): array
    {
        $org = $this->makeOrganization($name);
        $user = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $user->id, 'role' => $role]));

        return [$org, $user];
    }

    private function contact(Organization $org, string $first, ?string $email, ?string $phone = null): Contact
    {
        return $this->tenant()->runAs($org, fn () => app(ContactService::class)->create(array_filter(['first_name' => $first, 'email' => $email, 'phone' => $phone]), ContactSource::Manual));
    }

    private function consent(Organization $org, Contact $c): ConsentStatus
    {
        return $this->tenant()->runAs($org, fn () => app(ConsentService::class)->current($c, Channel::Email, ConsentPurpose::Marketing));
    }

    public function test_selected_contacts_go_into_a_new_list_with_consent_declared_once(): void
    {
        [$org, $owner] = $this->firm('Podreg');
        $ana = $this->contact($org, 'Ana', 'ana@ex.ro');
        $ion = $this->contact($org, 'Ion', 'ion@ex.ro');
        $dan = $this->contact($org, 'Dan', null, '0722123456');
        $vlad = $this->contact($org, 'Vlad', 'vlad@ex.ro');
        $this->tenant()->runAs($org, fn () => app(ConsentService::class)->record($vlad, Channel::Email, ConsentPurpose::Marketing, ConsentStatus::Revoked, 'unsubscribe'));
        $base = "/app/{$org->slug}/contacte";

        $this->actingAs($owner)->get($base)->assertOk()->assertSee('Selectează toate cele 4 contacte')->assertSee('fără acord')->assertSee('Email retras');

        // fără sursa acordului și confirmare nu se înregistrează nimic
        $this->post("{$base}/actiuni", ['scope' => 'selected', 'ids' => [$ana->id], 'consent' => ['email']])->assertSessionHasErrors(['evidence', 'declare']);

        $this->post("{$base}/actiuni", ['scope' => 'selected', 'ids' => [$ana->id, $ion->id, $dan->id, $vlad->id], 'new_list' => 'Clienți 2026',
            'consent' => ['email'], 'evidence' => 'abonați la casă și pe site', 'declare' => '1'])
            ->assertSessionHas('ok', fn ($m) => str_contains($m, '4 adăugate în lista „Clienți 2026”') && str_contains($m, '2 acorduri de marketing')
                && str_contains($m, '1 au refuzat') && str_contains($m, '1 fără adresă'));

        $this->tenant()->runAs($org, function () use ($ana, $ion, $vlad): void {
            $list = ContactList::query()->where('name', 'Clienți 2026')->sole();
            $this->assertSame(4, ContactListMember::query()->where('contact_list_id', $list->id)->count());
            $this->assertSame(ConsentStatus::Granted, app(ConsentService::class)->current($ana, Channel::Email, ConsentPurpose::Marketing));
            $this->assertSame(ConsentStatus::Granted, app(ConsentService::class)->current($ion, Channel::Email, ConsentPurpose::Marketing));
            $this->assertSame(ConsentStatus::Revoked, app(ConsentService::class)->current($vlad, Channel::Email, ConsentPurpose::Marketing), 'dezabonatul rămâne dezabonat');
        });
        $this->get("{$base}/{$ana->id}")->assertSee('abonați la casă și pe site');
    }

    public function test_all_matching_contacts_and_suppressed_addresses_stay_out(): void
    {
        [$org, $owner] = $this->firm('Podreg');
        foreach (['Ana', 'Ion', 'Maria'] as $n) {
            $this->contact($org, $n, strtolower($n).'@ex.ro');
        }
        $this->contact($org, 'Altcineva', 'x@altdomeniu.ro');
        $this->tenant()->runAs($org, fn () => Suppression::suppress(Channel::Email, 'maria@ex.ro', 'unsubscribed', 'test'));
        $list = $this->tenant()->runAs($org, fn () => ContactList::create(['name' => 'Toți']));

        $this->actingAs($owner)->post("/app/{$org->slug}/contacte/actiuni", ['scope' => 'all', 'q' => '@ex.ro', 'list_id' => $list->id,
            'consent' => ['email'], 'evidence' => 'formular site', 'declare' => '1'])
            ->assertSessionHas('ok', fn ($m) => str_contains($m, '3 adăugate') && str_contains($m, '2 acorduri') && str_contains($m, '1 au refuzat'));
        $this->assertSame(3, $this->tenant()->runAs($org, fn () => ContactListMember::query()->where('contact_list_id', $list->id)->count()));

        // a doua oară: nimic dublat
        $this->post("/app/{$org->slug}/contacte/actiuni", ['scope' => 'all', 'q' => '', 'list_id' => $list->id])
            ->assertSessionHas('ok', fn ($m) => str_contains($m, '1 adăugate') && str_contains($m, '3 erau deja'));
    }

    public function test_permissions_and_isolation_between_firms(): void
    {
        [$a, $ownerA] = $this->firm('Firma A');
        [$b] = $this->firm('Firma B');
        $mine = $this->contact($a, 'Ana', 'ana@a.ro');
        $theirs = $this->contact($b, 'Bogdan', 'bogdan@b.ro');
        $listB = $this->tenant()->runAs($b, fn () => ContactList::create(['name' => 'Lista B']));

        // contactul și lista altei firme nu se ating
        $this->actingAs($ownerA)->post("/app/{$a->slug}/contacte/actiuni", ['scope' => 'selected', 'ids' => [$theirs->id], 'new_list' => 'X'])->assertSessionHasErrors('ids');
        $this->post("/app/{$a->slug}/contacte/actiuni", ['scope' => 'selected', 'ids' => [$mine->id], 'list_id' => $listB->id])->assertNotFound();
        $this->post("/app/{$b->slug}/contacte/actiuni", ['scope' => 'all', 'new_list' => 'X'])->assertNotFound();
        $this->post("/app/{$a->slug}/contacte/actiuni", ['scope' => 'all', 'consent' => ['email'], 'evidence' => 'site', 'declare' => '1'])->assertSessionHas('ok');
        $this->assertSame(ConsentStatus::Unknown, $this->consent($b, $theirs));
        $this->assertSame(0, $this->tenant()->runAs($b, fn () => ContactListMember::query()->count()));

        // operatorul poate înregistra acordul, dar nu umblă la listele de marketing; vizualizarea nu poate nimic
        $operator = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($a, fn () => Membership::create(['user_id' => $operator->id, 'role' => OrgRole::Agent]));
        $this->actingAs($operator)->post("/app/{$a->slug}/contacte/actiuni", ['scope' => 'all', 'new_list' => 'Y'])->assertForbidden();
        $viewer = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($a, fn () => Membership::create(['user_id' => $viewer->id, 'role' => OrgRole::Viewer]));
        $this->actingAs($viewer)->get("/app/{$a->slug}/contacte")->assertOk()->assertDontSee('data-bulk-id', false);
        $this->post("/app/{$a->slug}/contacte/actiuni", ['scope' => 'all', 'consent' => ['email'], 'evidence' => 'x', 'declare' => '1'])->assertForbidden();
    }
}
