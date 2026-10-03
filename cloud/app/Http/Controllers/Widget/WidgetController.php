<?php

declare(strict_types=1);

namespace App\Http\Controllers\Widget;

use App\Ai\AgentRuntime;
use App\Enums\Channel;
use App\Enums\ConversationStatus;
use App\Enums\IdentityType;
use App\Enums\SenderType;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\CookieConsent;
use App\Models\Message;
use App\Models\SignupForm;
use App\Models\Site;
use App\Models\User;
use App\Services\ContactService;
use App\Services\CookieSettings;
use App\Services\IdentityNormalizer;
use App\Services\LiveChatService;
use App\Services\ShopEvents;
use App\Services\SignupFormService;
use App\Services\SiteKeyService;
use App\Services\WidgetSettings;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

            $online = WidgetSettings::online($settings);

            return $this->json($origin, [
                'enabled' => (bool) $enabled,
                'title' => $settings['title'] ?? $agent?->name ?? $site->organization->name,
                'greeting' => $agent?->system_configuration['greeting'] ?? 'Bună! Cu ce te putem ajuta?',
                'color' => $settings['color'],
                'position' => $settings['position'],
                'launcher' => $settings['launcher'],
                'privacy_url' => $settings['privacy_url'],
                'avatar_url' => $settings['avatar_url'],
                'welcome_title' => $settings['welcome_title'],
                'welcome_text' => $settings['welcome_text'],
                'quick_replies' => $settings['quick_replies'],
                'proactive_delay' => $settings['proactive_delay'],
                'proactive_text' => $settings['proactive_text'],
                'online' => $online,
                'status_text' => $online ? 'Suntem online · răspundem imediat' : 'Asistentul răspunde acum · echipa revine la '.$settings['hours_start'],
                'email_capture' => $settings['email_capture'],
                'sound' => $settings['sound'],
                'notice' => 'Răspunsurile sunt date de un asistent virtual (AI) al firmei '.($site->organization->company_name ?: $site->organization->name).', iar la cerere de un coleg din echipă. Nu trimite date sensibile (CNP, card).',
                'cookies' => CookieSettings::for($site)['enabled'] ? CookieSettings::publicPayload($site) : null,
                'forms' => $site->organization->subscription?->isServiceable()
                    ? SignupForm::query()->where('status', 'live')->where(fn ($q) => $q->whereNull('site_id')->orWhere('site_id', $site->id))->orderBy('id')->limit(10)->get()
                        ->map(fn (SignupForm $form) => SignupFormService::publicPayload($form, $site->organization, $settings['privacy_url'] ?? null))->values()
                    : [],
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

    public function message(Request $request, AgentRuntime $runtime, LiveChatService $live): JsonResponse
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

        return $this->context->runAs($site->organization, function () use ($site, $origin, $body, $text, $runtime, $live, $request): JsonResponse {
            $conversation = $this->conversation($site, (string) ($body['token'] ?? ''));
            if (! $conversation) {
                return $this->json($origin, ['error' => 'unknown_conversation'], 404);
            }
            if (Message::query()->where('conversation_id', $conversation->id)->where('sender_type', SenderType::Contact)->count() >= self::MAX_MESSAGES_PER_CONVERSATION) {
                return $this->json($origin, ['reply' => 'Conversația a ajuns la limita de mesaje. Te rugăm să ne contactezi direct.', 'status' => 'limit'], 429);
            }
            $after = max(0, (int) ($body['after'] ?? 0));
            if ($conversation->isLive()) {
                // un coleg a preluat conversația: mesajul ajunge la el în panou, nu la AI
                $live->visitorMessage($conversation, $text);
                $this->seen($conversation);

                return $this->json($origin, ['reply' => null, 'status' => 'human'] + $this->since($live, $conversation, $after));
            }
            if ($conversation->status === ConversationStatus::Closed) {
                $conversation->forceFill(['status' => ConversationStatus::Open, 'closed_at' => null])->save();
            }
            $reply = $runtime->reply($conversation, $text, $request->ip(), $request->userAgent());

            return $this->json($origin, ['reply' => $reply->text, 'status' => $reply->status] + $this->since($live, $conversation, $after));
        });
    }

    /** Istoricul (la redeschidere) sau doar mesajele noi după `after` (verificarea periodică a widgetului). */
    public function history(Request $request, LiveChatService $live): JsonResponse
    {
        [$site, $origin, $body] = $this->site($request);
        if (! $site) {
            return $this->deny($origin);
        }

        return $this->context->runAs($site->organization, function () use ($site, $origin, $body, $live): JsonResponse {
            $conversation = $this->conversation($site, (string) ($body['token'] ?? ''));
            if (! $conversation) {
                return $this->json($origin, ['error' => 'unknown_conversation'], 404);
            }
            $this->seen($conversation);
            $messages = $live->forVisitor($conversation, max(0, (int) ($body['after'] ?? 0)));
            $operator = $conversation->assigned_to ? User::query()->whereKey($conversation->assigned_to)->value('name') : null;

            return $this->json($origin, [
                'messages' => $messages,
                'last_id' => (int) ($messages->last()['id'] ?? $body['after'] ?? 0),
                'live' => $conversation->isLive(),
                'operator' => $conversation->isLive() && $operator ? LiveChatService::firstName((string) $operator) : null,
                'typing' => $conversation->isLive() && $live->isTyping($conversation),
                'has_contact' => $conversation->contact_id !== null,
            ]);
        });
    }

    /** Vizitatorul lasă numele și emailul ca echipa să-i poată răspunde (cu bifa de acord). */
    public function contact(Request $request, LiveChatService $live): JsonResponse
    {
        [$site, $origin, $body] = $this->site($request);
        if (! $site) {
            return $this->deny($origin);
        }
        $email = mb_substr(trim((string) ($body['email'] ?? '')), 0, 190);
        $name = mb_substr(trim((string) ($body['name'] ?? '')), 0, 80);
        if (($body['consent'] ?? false) !== true) {
            return $this->json($origin, ['error' => 'consent_required'], 422);
        }
        if (IdentityNormalizer::email($email) === null) {
            return $this->json($origin, ['error' => 'invalid_email'], 422);
        }
        if (! RateLimiter::attempt('widget-contact:'.$request->ip(), 5, fn () => true, 3600)) {
            return $this->json($origin, ['error' => 'rate_limited'], 429);
        }

        return $this->context->runAs($site->organization, function () use ($site, $origin, $body, $live, $name, $email, $request): JsonResponse {
            $conversation = $this->conversation($site, (string) ($body['token'] ?? ''));
            if (! $conversation) {
                return $this->json($origin, ['error' => 'unknown_conversation'], 404);
            }
            $live->leaveContact($conversation, $name, $email, $request->ip(), $request->userAgent());

            return $this->json($origin, ['ok' => true]);
        });
    }

    /** Alegerea vizitatorului în bannerul de cookie-uri: se păstrează ca dovadă (fără IP în clar). */
    public function consent(Request $request): JsonResponse
    {
        [$site, $origin, $body] = $this->site($request);
        if (! $site) {
            return $this->deny($origin);
        }
        $id = (string) ($body['consent_id'] ?? '');
        if (! preg_match('/^[a-f0-9-]{16,36}$/', $id) || ! in_array($body['action'] ?? '', array_keys(CookieConsent::ACTIONS), true)) {
            return $this->json($origin, ['error' => 'invalid'], 422);
        }
        if (! RateLimiter::attempt('cookie-consent:'.$request->ip(), 30, fn () => true, 3600)) {
            return $this->json($origin, ['error' => 'rate_limited'], 429);
        }
        $this->context->runAs($site->organization, fn () => CookieConsent::create([
            'site_id' => $site->id, 'consent_id' => $id, 'action' => $body['action'],
            'preferences' => ($body['preferences'] ?? false) === true, 'statistics' => ($body['statistics'] ?? false) === true, 'marketing' => ($body['marketing'] ?? false) === true,
            'policy_version' => max(1, min(10000, (int) ($body['version'] ?? 1))),
            'ip_hash' => substr(hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')), 0, 32),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255) ?: null,
            'page' => mb_substr((string) ($body['page'] ?? ''), 0, 255) ?: null, 'created_at' => now(),
        ]));

        return $this->json($origin, ['ok' => true]);
    }

    /** Formularul a fost afișat (pentru rata de conversie din panou). */
    public function formView(Request $request): JsonResponse
    {
        [$site, $origin, $body] = $this->site($request);
        if (! $site) {
            return $this->deny($origin);
        }
        if (RateLimiter::attempt('form-view:'.$request->ip().':'.(int) ($body['form'] ?? 0), 3, fn () => true, 3600)) {
            $this->context->runAs($site->organization, fn () => $this->form($site, (int) ($body['form'] ?? 0))?->increment('views'));
        }

        return $this->json($origin, ['ok' => true]);
    }

    public function formSubmit(Request $request, SignupFormService $forms): JsonResponse
    {
        [$site, $origin, $body] = $this->site($request);
        if (! $site) {
            return $this->deny($origin);
        }
        if (trim((string) ($body['website'] ?? '')) !== '') {
            return $this->json($origin, ['status' => 'subscribed']); // capcană pentru roboți: răspuns normal, nimic salvat
        }
        if (! RateLimiter::attempt('form-submit:'.$request->ip(), 10, fn () => true, 3600)) {
            return $this->json($origin, ['error' => 'Prea multe înscrieri de pe această conexiune. Încearcă mai târziu.'], 429);
        }

        return $this->context->runAs($site->organization, function () use ($site, $origin, $body, $forms, $request): JsonResponse {
            $form = $this->form($site, (int) ($body['form'] ?? 0));
            if (! $form) {
                return $this->json($origin, ['error' => 'Formularul nu mai este activ.'], 404);
            }
            try {
                $status = $forms->submit($form, $site->organization, $body, $request->ip(), $request->userAgent());
            } catch (ValidationException $e) {
                return $this->json($origin, ['error' => collect($e->errors())->flatten()->first()], 422);
            }

            $contact = app(ContactService::class)->findByIdentity(IdentityType::Email, (string) IdentityNormalizer::email((string) ($body['email'] ?? '')));

            // tokenul ajunge în cookie-ul site-ului: magazinul recunoaște abonatul (coș abandonat, produse văzute)
            return $this->json($origin, ['status' => $status, 'contact' => $contact ? ShopEvents::token($contact) : null]);
        });
    }

    private function form(Site $site, int $id): ?SignupForm
    {
        return SignupForm::query()->where('status', 'live')->where(fn ($q) => $q->whereNull('site_id')->orWhere('site_id', $site->id))->find($id);
    }

    /** @return array{messages: mixed, last_id: int, has_contact: bool} mesajele echipei/AI apărute după `after` (fără ale vizitatorului, deja afișate) */
    private function since(LiveChatService $live, Conversation $conversation, int $after): array
    {
        $messages = $live->forVisitor($conversation, $after, false);
        $last = (int) Message::query()->where('conversation_id', $conversation->id)->max('id');

        return ['messages' => $messages, 'last_id' => $last, 'has_contact' => $conversation->fresh()?->contact_id !== null];
    }

    private function seen(Conversation $conversation): void
    {
        // o scriere cel mult la 15 secunde, nu la fiecare verificare
        if ($conversation->visitor_seen_at === null || $conversation->visitor_seen_at->lt(now()->subSeconds(15))) {
            $conversation->forceFill(['visitor_seen_at' => now()])->saveQuietly();
        }
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
