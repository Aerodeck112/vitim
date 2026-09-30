<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\LeadStatus;
use App\Models\Agent;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Lead;
use Illuminate\View\View;

final class OverviewController extends PortalController
{
    public function show(): View
    {
        $open = array_map(fn ($s) => $s->value, array_filter(LeadStatus::cases(), fn ($s) => ! $s->isClosed()));

        return view('portal.overview', [
            'organization' => $this->organization(),
            'kpi' => [
                ['Contacte', Contact::query()->count()],
                ['Lead-uri deschise', Lead::query()->whereIn('status', $open)->count()],
                ['Lead-uri luna aceasta', Lead::query()->where('created_at', '>=', now()->startOfMonth())->count()],
                ['Conversații', Conversation::query()->count()],
            ],
            'agents' => Agent::query()->orderBy('name')->get(),
            'recentLeads' => Lead::query()->with('contact')->latest('id')->limit(8)->get(),
        ]);
    }

    /** Secțiuni planificate: pagină explicită „în dezvoltare”, fără interfață falsă. */
    public function upcoming(string $section): View
    {
        $sections = [
            'inbox' => ['Inbox', 'Conversațiile de pe website, email, WhatsApp și SMS într-un singur loc.', 'Faza 4 (chat pe site), apoi canalele externe'],
            'campanii' => ['Campanii', 'Campanii email, WhatsApp și SMS pe segmente, cu aprobare înainte de trimitere.', 'după Inbox'],
            'automatizari' => ['Automatizări', 'Fluxuri declanșate de evenimente: lead nou, programare, lipsă răspuns.', 'după Campanii'],
            'analytics' => ['Analytics', 'Conversii, întrebări frecvente, ore economisite, cost și ROI.', 'Faza 7'],
            'integrari' => ['Integrări', 'WordPress, WooCommerce, Google Calendar, CRM, webhook-uri.', 'Faza 5 (WordPress), apoi restul'],
        ];
        abort_unless(isset($sections[$section]), 404);

        return view('portal.upcoming', ['organization' => $this->organization(), 'section' => $sections[$section]]);
    }
}
