<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Enums\Channel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\ContactSource;
use App\Enums\MessageStatus;
use App\Messaging\MessagingService;
use App\Models\Contact;
use App\Models\ContactConsent;
use App\Models\Conversation;
use App\Models\DomainEvent;
use App\Models\Message;
use App\Models\Organization;
use App\Models\Suppression;
use App\Services\ConsentService;
use App\Services\ContactService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

final class ConsentAndMessagingTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();
        config(['messaging.channels.email' => 'log', 'messaging.channels.sms' => 'log']);
        $this->org = $this->makeOrganization('Firma A');
        $this->contact = $this->in(fn () => app(ContactService::class)->create(
            ['first_name' => 'Ion', 'email' => 'ion@example.test', 'phone' => '0722000001'], ContactSource::Form));
    }

    private function in(callable $fn): mixed
    {
        return $this->tenant()->runAs($this->org, $fn);
    }

    private function consent(Channel $ch, ConsentPurpose $p, ConsentStatus $s): void
    {
        $this->in(fn () => app(ConsentService::class)->record($this->contact, $ch, $p, $s, 'test', ['form' => 'contact'], '10.0.0.1'));
    }

    private function send(Channel $ch, ConsentPurpose $p): Message
    {
        return $this->in(function () use ($ch, $p) {
            $conversation = Conversation::create(['contact_id' => $this->contact->id, 'channel' => $ch]);

            return app(MessagingService::class)->send($conversation, $ch, $p, 'Conținut', 'Subiect');
        });
    }

    public function test_consent_history_is_append_only_and_current_is_latest(): void
    {
        $this->consent(Channel::Email, ConsentPurpose::Marketing, ConsentStatus::Granted);
        $this->consent(Channel::Email, ConsentPurpose::Marketing, ConsentStatus::Revoked);

        $this->in(function (): void {
            $service = app(ConsentService::class);
            $this->assertSame(ConsentStatus::Revoked, $service->current($this->contact, Channel::Email, ConsentPurpose::Marketing));
            $this->assertSame(ConsentStatus::Unknown, $service->current($this->contact, Channel::Sms, ConsentPurpose::Marketing));
            $history = ContactConsent::query()->orderBy('id')->get();
            $this->assertCount(2, $history);
            $this->assertSame('10.0.0.1', $history[0]->ip_address);
            $this->assertSame('test', $history[0]->source);

            $this->expectException(LogicException::class);
            $history[0]->update(['status' => ConsentStatus::Revoked]);
        });
    }

    public function test_revoking_marketing_suppresses_and_regranting_lifts_only_unsubscribe(): void
    {
        $this->consent(Channel::Email, ConsentPurpose::Marketing, ConsentStatus::Revoked);
        $this->in(fn () => $this->assertSame('unsubscribed', Suppression::query()->where('channel', 'email')->value('reason')));
        $this->assertSame(0, Suppression::withoutTenancy()->where('value_hash', 'ion@example.test')->count(), 'nu se stochează adresa în clar');

        $this->consent(Channel::Email, ConsentPurpose::Marketing, ConsentStatus::Granted);
        $this->in(fn () => $this->assertSame(0, Suppression::query()->count()));

        // un bounce rămâne chiar și după un nou acord
        $this->in(fn () => Suppression::suppress(Channel::Email, 'ion@example.test', 'bounced'));
        $this->consent(Channel::Email, ConsentPurpose::Marketing, ConsentStatus::Granted);
        $this->in(fn () => $this->assertSame('bounced', Suppression::query()->value('reason')));
        // o dezabonare ulterioară nu „îmblânzește” bounce-ul
        $this->in(fn () => Suppression::suppress(Channel::Email, 'ion@example.test', 'unsubscribed'));
        $this->in(fn () => $this->assertSame('bounced', Suppression::query()->value('reason')));
    }

    public function test_marketing_requires_explicit_consent(): void
    {
        $unknown = $this->send(Channel::Email, ConsentPurpose::Marketing);
        $this->assertSame(MessageStatus::Cancelled, $unknown->status);

        $this->consent(Channel::Email, ConsentPurpose::Marketing, ConsentStatus::Granted);
        $granted = $this->send(Channel::Email, ConsentPurpose::Marketing);
        $this->assertSame(MessageStatus::Sent, $granted->status);
        $this->assertSame('log', $granted->provider);

        $this->consent(Channel::Email, ConsentPurpose::Marketing, ConsentStatus::Revoked);
        $this->assertSame(MessageStatus::Cancelled, $this->send(Channel::Email, ConsentPurpose::Marketing)->status);
        // consimțământul pe email nu acoperă SMS
        $this->assertSame(MessageStatus::Cancelled, $this->send(Channel::Sms, ConsentPurpose::Marketing)->status);
    }

    public function test_transactional_is_separate_from_marketing(): void
    {
        $this->consent(Channel::Email, ConsentPurpose::Marketing, ConsentStatus::Revoked);
        $this->assertSame(MessageStatus::Sent, $this->send(Channel::Email, ConsentPurpose::Transactional)->status);

        $this->in(fn () => Suppression::suppress(Channel::Email, 'ion@example.test', 'complaint'));
        $this->assertSame(MessageStatus::Cancelled, $this->send(Channel::Email, ConsentPurpose::Transactional)->status);
    }

    public function test_missing_provider_fails_visibly_and_events_are_recorded(): void
    {
        config(['messaging.channels.whatsapp' => null]);
        $this->consent(Channel::WhatsApp, ConsentPurpose::Service, ConsentStatus::Granted);
        $message = $this->send(Channel::WhatsApp, ConsentPurpose::Service);

        $this->assertSame(MessageStatus::Failed, $message->status);
        $this->assertStringContainsString('Niciun furnizor', (string) $message->error);
        $this->in(fn () => $this->assertTrue(DomainEvent::query()->where('type', 'message.failed')->exists()));
    }
}
