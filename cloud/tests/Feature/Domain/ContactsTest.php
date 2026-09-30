<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Enums\ContactSource;
use App\Enums\IdentityType;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\ContactIdentity;
use App\Models\DomainEvent;
use App\Models\Organization;
use App\Services\ContactService;
use App\Services\DuplicateContactException;
use App\Services\IdentityNormalizer;
use App\Services\UsageMeter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ContactsTest extends TestCase
{
    use RefreshDatabase;

    private function contacts(): ContactService
    {
        return app(ContactService::class);
    }

    private function in(Organization $org, callable $fn): mixed
    {
        return $this->tenant()->runAs($org, $fn);
    }

    public function test_phone_and_email_normalization(): void
    {
        $this->assertSame('+40722123456', IdentityNormalizer::phone('0722 123 456'));
        $this->assertSame('+40722123456', IdentityNormalizer::phone('+40 (722) 123-456'));
        $this->assertSame('+40722123456', IdentityNormalizer::phone('0040722123456'));
        $this->assertSame('+493012345678', IdentityNormalizer::phone('030 12345678', 'DE'));
        $this->assertNull(IdentityNormalizer::phone('722123456')); // fără prefix și fără 0: nu ghicim
        $this->assertNull(IdentityNormalizer::phone('123'));
        $this->assertSame('ion@example.test', IdentityNormalizer::email('  Ion@Example.TEST '));
        $this->assertNull(IdentityNormalizer::email('nu-e-email'));
    }

    public function test_contact_can_exist_without_email_or_phone(): void
    {
        $org = $this->makeOrganization('Firma A');
        $contact = $this->in($org, fn () => $this->contacts()->create(['first_name' => 'Anonim'], ContactSource::WebsiteAi));

        $this->assertNull($contact->email);
        $this->assertSame(0, $this->in($org, fn () => $contact->identities()->count()));
    }

    public function test_create_stores_identities_and_records_event_and_usage(): void
    {
        $org = $this->makeOrganization('Firma A');
        $contact = $this->in($org, fn () => $this->contacts()->create([
            'first_name' => 'Ion', 'email' => 'Ion@Example.test', 'phone' => '0722 000 001',
            'external_ids' => [['provider' => 'woocommerce', 'id' => '1001']],
        ], ContactSource::Manual));

        $this->assertSame('ion@example.test', $contact->email);
        $this->assertSame('+40722000001', $contact->phone);
        $this->in($org, function () use ($contact): void {
            $this->assertEqualsCanonicalizing(['email', 'phone', 'external_id'], $contact->identities()->pluck('type')->map->value->all());
            $this->assertSame(1, DomainEvent::query()->where('type', 'contact.created')->count());
            $this->assertSame(1, app(UsageMeter::class)->thisMonth('contacts_created'));
        });
    }

    public function test_duplicate_identity_is_detected_within_organization(): void
    {
        $org = $this->makeOrganization('Firma A');
        $first = $this->in($org, fn () => $this->contacts()->create(['email' => 'ion@example.test'], ContactSource::Manual));

        try {
            $this->in($org, fn () => $this->contacts()->create(['email' => 'ION@example.test'], ContactSource::Form));
            $this->fail('Trebuia să detecteze duplicatul.');
        } catch (DuplicateContactException $e) {
            $this->assertSame($first->id, $e->existingContactId);
        }
        $this->assertSame($first->id, $this->in($org, fn () => $this->contacts()->findByIdentity(IdentityType::Email, 'ion@EXAMPLE.test')?->id));
    }

    public function test_same_email_is_independent_across_organizations(): void
    {
        $a = $this->makeOrganization('Firma A');
        $b = $this->makeOrganization('Firma B');
        $ca = $this->in($a, fn () => $this->contacts()->create(['email' => 'ion@example.test'], ContactSource::Manual));
        $cb = $this->in($b, fn () => $this->contacts()->create(['email' => 'ion@example.test'], ContactSource::Manual));

        $this->assertNotSame($ca->id, $cb->id);
        $this->in($a, function () use ($cb): void {
            $this->assertNull(Contact::query()->find($cb->id));
            $this->assertSame(1, ContactIdentity::query()->count());
            $this->assertNull($this->contacts()->findByIdentity(IdentityType::Email, 'nimeni@example.test'));
        });
    }

    public function test_invalid_phone_is_rejected(): void
    {
        $org = $this->makeOrganization('Firma A');

        $this->expectException(ValidationException::class);
        $this->in($org, fn () => $this->contacts()->create(['phone' => '12'], ContactSource::Manual));
    }

    public function test_update_replaces_primary_email_with_duplicate_check(): void
    {
        $org = $this->makeOrganization('Firma A');
        [$c1, $c2] = $this->in($org, fn () => [
            $this->contacts()->create(['email' => 'unu@example.test'], ContactSource::Manual),
            $this->contacts()->create(['email' => 'doi@example.test'], ContactSource::Manual),
        ]);

        $this->in($org, fn () => $this->contacts()->update($c1, ['email' => 'nou@example.test', 'company' => 'Firma X']));
        $this->assertSame('nou@example.test', $c1->fresh()->email);
        $this->assertSame(['nou@example.test'], $this->in($org, fn () => $c1->identities()->pluck('normalized_value')->all()));

        $this->expectException(DuplicateContactException::class);
        $this->in($org, fn () => $this->contacts()->update($c1, ['email' => 'doi@example.test']));
    }

    public function test_delete_is_audited_without_personal_data(): void
    {
        $org = $this->makeOrganization('Firma A');
        $contact = $this->in($org, fn () => $this->contacts()->create(['first_name' => 'Ion', 'email' => 'ion@example.test'], ContactSource::Manual));
        $this->in($org, fn () => $this->contacts()->delete($contact));

        $this->in($org, function () use ($contact): void {
            $this->assertSame(0, Contact::query()->count());
            $this->assertSame(0, ContactIdentity::query()->count());
            $log = AuditLog::query()->where('action', 'contact.deleted')->firstOrFail();
            $this->assertSame($contact->id, $log->entity_id);
            $this->assertStringNotContainsString('ion@example.test', json_encode($log->toArray()));
        });
    }
}
