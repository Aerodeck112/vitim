<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\OrgRole;
use App\Mail\TeamAlert;
use App\Models\Conversation;
use App\Models\DomainEvent;
use App\Models\Lead;
use App\Models\Membership;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Mail;

/**
 * Email către echipa firmei (proprietar, administratori, operatori) când agentul AI salvează o cerere sau când un
 * vizitator cere un om. Fără conținut sensibil în subiect; detaliile sunt în panou.
 */
final class NotifyTeam implements DomainEventListener
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(DomainEvent $event): void
    {
        $organization = $this->context->organization();
        if ($event->type === 'lead.created') {
            if (($event->payload['source'] ?? '') !== 'website_ai') {
                return;
            }
            $lead = Lead::query()->with('contact')->find($event->subject_id);
            if (! $lead) {
                return;
            }
            $contact = $lead->contact;
            $title = 'Cerere nouă de pe site: '.($contact?->displayName() ?? 'vizitator');
            $lines = array_values(array_filter([
                $contact?->phone ? 'Telefon: '.$contact->phone : null,
                $contact?->email ? 'Email: '.$contact->email : null,
                'Intenție: '.$lead->intent->value,
                $lead->summary ? "\n".$lead->summary : null,
            ]));
            $url = $lead->conversation_id ? route('portal.conversations.show', [$organization->slug, $lead->conversation_id]) : route('portal.leads.index', $organization->slug);
        } elseif ($event->type === 'conversation.human_requested') {
            $conversation = Conversation::query()->find($event->subject_id);
            if (! $conversation || $conversation->is_test) {
                return;
            }
            $title = 'Un vizitator vrea să vorbească cu un om';
            $lines = ['Motiv: '.($event->payload['reason'] ?? '—'), (string) ($event->payload['summary'] ?? '')];
            $url = route('portal.conversations.show', [$organization->slug, $conversation->id]);
        } else {
            return;
        }

        $emails = Membership::query()->with('user')
            ->whereIn('role', [OrgRole::Owner->value, OrgRole::Admin->value, OrgRole::Agent->value])->get()
            ->map(fn (Membership $m) => $m->user?->email)->filter()->unique();
        foreach ($emails as $email) {
            Mail::to($email)->send(new TeamAlert($title, $lines, $url));
        }
    }
}
