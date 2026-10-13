<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\ConversationStatus;
use App\Enums\Permission;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Site;
use App\Models\SiteVisitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Ce se întâmplă acum, pentru panoul deschis: câți vizitatori sunt pe site-urile firmei (și pe ce pagini)
 * și mesajele noi de la vizitatori, pentru notificarea sonoră. Citit din browser la câteva secunde.
 */
final class LiveController extends PortalController
{
    public function __invoke(Request $request): JsonResponse
    {
        // rândurile vechi nu mai spun nimic: le ștergem din când în când
        if (random_int(1, 20) === 1) {
            SiteVisitor::query()->where('seen_at', '<', now()->subMinutes(10))->delete();
        }
        $active = SiteVisitor::query()->active()->get(['site_id', 'page', 'title', 'started_at']);
        $domains = Site::query()->whereIn('id', $active->pluck('site_id')->unique())->pluck('domain', 'id');
        $sites = $active->groupBy('site_id')->map(fn ($rows, $siteId) => [
            'domain' => $domains[$siteId] ?? '',
            'count' => $rows->count(),
            'pages' => $rows->groupBy('page')->map(fn ($p, $page) => ['page' => $page, 'title' => $p->first()->title, 'count' => $p->count()])
                ->sortByDesc('count')->take(8)->values(),
        ])->sortByDesc('count')->values();

        $data = ['visitors' => $active->count(), 'sites' => $sites, 'at' => now()->setTimezone('Europe/Bucharest')->format('H:i:s')];

        if (Gate::allows(Permission::ViewContacts->value)) {
            $real = Conversation::query()->where('is_test', false)->select('id');
            $inbound = Message::query()->where('direction', 'inbound')->whereIn('conversation_id', $real);
            $data['last_id'] = (int) (clone $inbound)->max('id');
            $data['waiting'] = Conversation::query()->where('is_test', false)->where('status', ConversationStatus::Pending->value)->count();
            $after = (int) $request->query('after', 0);
            $data['messages'] = [];
            if ($after > 0) {
                $new = (clone $inbound)->where('id', '>', $after)->orderBy('id')->limit(10)->get(['id', 'conversation_id', 'content']);
                $conversations = Conversation::query()->whereIn('id', $new->pluck('conversation_id'))->get(['id', 'contact_id', 'subject'])->keyBy('id');
                $names = Contact::query()->whereIn('id', $conversations->pluck('contact_id')->filter())->get()->keyBy('id');
                $data['messages'] = $new->map(function (Message $m) use ($conversations, $names) {
                    $conversation = $conversations[$m->conversation_id] ?? null;
                    $contact = $conversation?->contact_id ? ($names[$conversation->contact_id] ?? null) : null;

                    return [
                        'id' => $m->id,
                        'conversation_id' => $m->conversation_id,
                        'from' => $contact?->displayName() ?: 'Vizitator',
                        'preview' => Str::limit(trim((string) $m->content), 90),
                        'url' => route('portal.conversations.show', [$this->organization()->slug, $m->conversation_id]),
                    ];
                })->values()->all();
            }
        }

        return response()->json($data)->header('Cache-Control', 'no-store');
    }
}
