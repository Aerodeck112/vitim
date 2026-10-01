<?php

declare(strict_types=1);

namespace App\Http\Controllers\Widget;

use App\Ai\AgentRuntime;
use App\Enums\Channel;
use App\Enums\ConversationStatus;
use App\Enums\SenderType;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Site;
use App\Services\SiteKeyService;
use App\Services\WidgetSettings;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * API-ul public al widgetului de chat. Fără sesiune: site-ul se identifică prin cheia publică + Origin-ul permis,
 * conversația prin tokenul vizitatorului (păstrat doar ca hash). Cererile sunt „simple” (text/plain), fără preflight CORS.
 */
final class WidgetController extends Controller
{
    private const MAX_MESSAGES_PER_CONVERSATION = 40;

    public function __construct(private readonly SiteKeyService $keys, private readonly TenantContext $context) {}

    public function config(Request $request): JsonResponse
    {
        [$site, $origin, $body] = $this->site($request);
        if (! $site) {
            return $this->deny($origin);
        }

        return $this->context->runAs($site->organization, function () use ($site, $origin): JsonResponse {
            $settings = WidgetSettings::for($site);
            $agent = WidgetSettings::agentFor($site);
            $enabled = $settings['enabled'] && $agent !== null && $site->organization->subscription?->isServiceable();

            return $this->json($origin, [
                'enabled' => (bool) $enabled,
                'title' => $settings['title'] ?? $agent?->name ?? $site->organization->name,
                'greeting' => $agent?->system_configuration['greeting'] ?? 'Bună! Cu ce te putem ajuta?',
                'color' => $settings['color'],
                'position' => $settings['position'],
                'launcher' => $settings['launcher'],
                'privacy_url' => $settings['privacy_url'],
                'notice' => 'Răspunsurile sunt date de un asistent virtual (AI) al firmei '.($site->organization->company_name ?: $site->organization->name).'. Nu trimite date sensibile (CNP, card).',
            ]);
        });
    }

    public function start(Request $request): JsonResponse
    {
        [$site, $origin, $body] = $this->site($request);
        if (! $site) {
            return $this->deny($origin);
        }
        if (! RateLimiter::attempt('widget-start:'.$request->ip(), 10, fn () => true, 3600)) {
            return $this->json($origin, ['error' => 'rate_limited'], 429);
        }

        return $this->context->runAs($site->organization, function () use ($site, $origin, $body): JsonResponse {
            $agent = WidgetSettings::agentFor($site);
            if (! $agent || ! WidgetSettings::for($site)['enabled']) {
                return $this->json($origin, ['error' => 'disabled'], 409);
            }
            $token = Str::random(48);
            Conversation::create([
                'site_id' => $site->id, 'agent_id' => $agent->id, 'channel' => Channel::Web, 'status' => ConversationStatus::Open,
                'mode' => 'ai', 'is_test' => false, 'visitor_token_hash' => hash('sha256', $token),
                'visitor_page' => mb_substr((string) ($body['page'] ?? ''), 0, 255) ?: null, 'subject' => 'Chat pe '.$site->domain,
            ]);

            return $this->json($origin, ['token' => $token]);
        });
    }

    public function message(Request $request, AgentRuntime $runtime): JsonResponse
    {
        [$site, $origin, $body] = $this->site($request);
        if (! $site) {
            return $this->deny($origin);
        }
        $text = trim((string) ($body['message'] ?? ''));
        if ($text === '' || mb_strlen($text) > 1000) {
            return $this->json($origin, ['error' => 'invalid_message'], 422);
        }
        if (! RateLimiter::attempt('widget-msg:'.$request->ip(), 30, fn () => true, 600)) {
            return $this->json($origin, ['reply' => 'Ai trimis multe mesaje într-un timp scurt. Încearcă din nou peste câteva minute.', 'status' => 'rate_limited'], 429);
        }

        return $this->context->runAs($site->organization, function () use ($site, $origin, $body, $text, $runtime, $request): JsonResponse {
            $conversation = $this->conversation($site, (string) ($body['token'] ?? ''));
            if (! $conversation) {
                return $this->json($origin, ['error' => 'unknown_conversation'], 404);
            }
            if (Message::query()->where('conversation_id', $conversation->id)->where('sender_type', SenderType::Contact)->count() >= self::MAX_MESSAGES_PER_CONVERSATION) {
                return $this->json($origin, ['reply' => 'Conversația a ajuns la limita de mesaje. Te rugăm să ne contactezi direct.', 'status' => 'limit'], 429);
            }
            $reply = $runtime->reply($conversation, $text, $request->ip(), $request->userAgent());

            return $this->json($origin, ['reply' => $reply->text, 'status' => $reply->status]);
        });
    }

    public function history(Request $request): JsonResponse
    {
        [$site, $origin, $body] = $this->site($request);
        if (! $site) {
            return $this->deny($origin);
        }

        return $this->context->runAs($site->organization, function () use ($site, $origin, $body): JsonResponse {
            $conversation = $this->conversation($site, (string) ($body['token'] ?? ''));
            if (! $conversation) {
                return $this->json($origin, ['error' => 'unknown_conversation'], 404);
            }
            $messages = Message::query()->where('conversation_id', $conversation->id)->orderBy('id')->limit(100)->get()
                ->map(fn (Message $m) => ['role' => $m->direction === 'inbound' ? 'visitor' : 'agent', 'text' => $m->content]);

            return $this->json($origin, ['messages' => $messages]);
        });
    }

    /** @return array{0: ?Site, 1: ?string, 2: array<string, mixed>} */
    private function site(Request $request): array
    {
        $body = json_decode($request->getContent(), true);
        $body = is_array($body) ? $body : [];
        $origin = $request->headers->get('Origin');
        $site = $this->keys->resolveForWidget((string) ($body['key'] ?? ''), $origin);

        return [$site, $origin, $body];
    }

    private function conversation(Site $site, string $token): ?Conversation
    {
        if (strlen($token) !== 48) {
            return null;
        }

        return Conversation::query()->where('site_id', $site->id)->where('is_test', false)
            ->where('visitor_token_hash', hash('sha256', $token))->first();
    }

    /** @param array<string, mixed> $data */
    private function json(?string $origin, array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)->withHeaders([
            'Access-Control-Allow-Origin' => (string) $origin,
            'Vary' => 'Origin',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function deny(?string $origin): JsonResponse
    {
        // fără Access-Control-Allow-Origin: browserul de pe un site neautorizat nu poate citi răspunsul
        return response()->json(['error' => 'forbidden'], 403)->withHeaders(['Vary' => 'Origin']);
    }
}
