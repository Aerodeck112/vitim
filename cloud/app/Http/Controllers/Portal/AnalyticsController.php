<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\ContactEvent;
use App\Models\Flow;
use App\Models\FormSubmission;
use App\Services\SendTime;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Analiza marketingului: venituri (atribuite emailurilor vs. restul), implicare, audiență, campanii, fluxuri, predicții. */
final class AnalyticsController extends PortalController
{
    public const PERIODS = [7 => '7 zile', 30 => '30 de zile', 90 => '90 de zile', 365 => '12 luni'];

    public function index(Request $request): View
    {
        $days = array_key_exists((int) $request->query('zile'), self::PERIODS) ? (int) $request->query('zile') : 30;
        $since = now()->setTimezone('Europe/Bucharest')->startOfDay()->subDays($days - 1)->utc();

        $orders = ContactEvent::query()->where('type', 'placed_order')->where('occurred_at', '>=', $since)->get(['value', 'occurred_at', 'campaign_id', 'flow_id']);
        $attributed = $orders->filter(fn ($o) => $o->campaign_id || $o->flow_id);
        $series = [];
        for ($d = 0; $d < $days; $d++) {
            $key = Carbon::parse($since)->setTimezone('Europe/Bucharest')->addDays($d)->format('Y-m-d');
            $series[$key] = ['email' => 0.0, 'other' => 0.0];
        }
        foreach ($orders as $o) {
            $key = $o->occurred_at->copy()->setTimezone('Europe/Bucharest')->format('Y-m-d');
            if (isset($series[$key])) {
                $series[$key][($o->campaign_id || $o->flow_id) ? 'email' : 'other'] += (float) $o->value;
            }
        }

        // emailuri trimise în perioadă (campanii + automatizări); deschiderile / click-urile unice ale acestor emailuri
        $emails = CampaignRecipient::query()->where('sent_at', '>=', $since)->whereIn('id', ContactEvent::query()->where('type', 'email_sent')->where('occurred_at', '>=', $since)->select('recipient_id'));
        $sent = (clone $emails)->count();
        $mail = (object) ['opened' => (clone $emails)->whereNotNull('opened_at')->count(), 'clicked' => (clone $emails)->whereNotNull('clicked_at')->count()];
        $events = ContactEvent::query()->where('occurred_at', '>=', $since)->whereIn('type', ['subscribed', 'unsubscribed'])
            ->selectRaw('type, count(*) as n')->groupBy('type')->pluck('n', 'type');

        $campaigns = Campaign::query()->whereIn('status', ['sending', 'completed', 'paused'])->where('started_at', '>=', $since)->latest('started_at')->limit(10)->get()
            ->map(function (Campaign $c) {
                $e = $c->engagement();
                $rev = $c->id ? ContactEvent::query()->where('type', 'placed_order')->where('campaign_id', $c->id) : null;

                return ['campaign' => $c, 'engagement' => $e, 'orders' => (int) $rev->count(), 'revenue' => (float) $rev->sum('value')];
            });
        $flows = Flow::query()->orderBy('name')->get()->map(fn (Flow $f) => [
            'flow' => $f,
            'sent' => CampaignRecipient::query()->where('flow_id', $f->id)->where('sent_at', '>=', $since)->whereNotNull('sent_at')->count(),
            'revenue' => (float) ContactEvent::query()->where('type', 'placed_order')->where('flow_id', $f->id)->where('occurred_at', '>=', $since)->sum('value'),
        ])->filter(fn ($r) => $r['flow']->status === 'live' || $r['sent'] > 0);

        return view('portal.analytics', [
            'organization' => $this->organization(),
            'days' => $days, 'periods' => self::PERIODS,
            'revenue' => ['total' => (float) $orders->sum('value'), 'email' => (float) $attributed->sum('value'), 'orders' => $orders->count(),
                'aov' => $orders->count() ? (float) $orders->sum('value') / $orders->count() : 0.0],
            'series' => $series,
            'email' => ['sent' => $sent, 'opened' => (int) ($mail->opened ?? 0), 'clicked' => (int) ($mail->clicked ?? 0),
                'open_rate' => $sent ? round(100 * (int) $mail->opened / $sent, 1) : 0.0, 'click_rate' => $sent ? round(100 * (int) $mail->clicked / $sent, 1) : 0.0],
            'audience' => ['subscribed' => (int) ($events['subscribed'] ?? 0), 'unsubscribed' => (int) ($events['unsubscribed'] ?? 0),
                'forms' => FormSubmission::query()->where('created_at', '>=', $since)->count()],
            'campaigns' => $campaigns, 'flows' => $flows,
            'sendTime' => SendTime::best(),
            'risk' => Contact::query()->whereNotNull('churn_risk')->selectRaw('churn_risk, count(*) as n')->groupBy('churn_risk')->pluck('n', 'churn_risk'),
            'clv' => (float) Contact::query()->sum('predicted_clv'),
        ]);
    }
}
