<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Agent;
use App\Models\Site;
use Illuminate\Support\Carbon;

/** Aspectul și comportamentul widgetului de chat al unui site (în sites.widget_config). */
final class WidgetSettings
{
    public const DEFAULTS = [
        'enabled' => true,
        'color' => '#2f6bff',
        'position' => 'right',
        'title' => null,
        'launcher' => 'Întreabă-ne',
        'privacy_url' => null,
        'avatar_url' => null,
        'welcome_title' => 'Salut 👋',
        'welcome_text' => 'Cu ce te putem ajuta azi?',
        'quick_replies' => ['Vreau o ofertă de preț', 'Care este programul?', 'Vreau să vorbesc cu cineva'],
        'proactive_delay' => 20,
        'proactive_text' => 'Bună! 👋 Ai o întrebare? Îți răspundem imediat.',
        'hours_start' => '09:00',
        'hours_end' => '18:00',
        'weekends' => false,
        'email_capture' => true,
        'sound' => true,
    ];

    /** @return array<string, mixed> */
    public static function for(Site $site): array
    {
        return array_replace(self::DEFAULTS, array_intersect_key((array) ($site->widget_config ?? []), self::DEFAULTS));
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public static function normalize(array $input): array
    {
        $color = (string) ($input['color'] ?? '');
        $text = fn (string $key, int $max, ?string $default = null) => mb_substr(trim((string) ($input[$key] ?? '')), 0, $max) ?: $default;
        $replies = is_array($input['quick_replies'] ?? null) ? $input['quick_replies'] : preg_split('/\R/', (string) ($input['quick_replies'] ?? ''));
        $replies = array_values(array_slice(array_filter(array_map(fn ($r) => mb_substr(trim((string) $r), 0, 60), $replies)), 0, 4));

        return [
            'enabled' => (bool) ($input['enabled'] ?? false),
            'color' => preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : self::DEFAULTS['color'],
            'position' => ($input['position'] ?? '') === 'left' ? 'left' : 'right',
            'title' => $text('title', 60),
            'launcher' => $text('launcher', 40, self::DEFAULTS['launcher']),
            'privacy_url' => self::url($input['privacy_url'] ?? null, 'https?'),
            'avatar_url' => self::url($input['avatar_url'] ?? null, 'https'),
            'welcome_title' => $text('welcome_title', 40, self::DEFAULTS['welcome_title']),
            'welcome_text' => $text('welcome_text', 120, self::DEFAULTS['welcome_text']),
            'quick_replies' => $replies,
            'proactive_delay' => max(0, min(300, (int) ($input['proactive_delay'] ?? 0))),
            'proactive_text' => $text('proactive_text', 140, self::DEFAULTS['proactive_text']),
            'hours_start' => self::time($input['hours_start'] ?? null, self::DEFAULTS['hours_start']),
            'hours_end' => self::time($input['hours_end'] ?? null, self::DEFAULTS['hours_end']),
            'weekends' => (bool) ($input['weekends'] ?? false),
            'email_capture' => (bool) ($input['email_capture'] ?? false),
            'sound' => (bool) ($input['sound'] ?? false),
        ];
    }

    /** Echipa e „online” în programul setat (ora României). */
    public static function online(array $settings, ?Carbon $now = null): bool
    {
        $now = ($now ?? now())->copy()->setTimezone('Europe/Bucharest');
        if (! $settings['weekends'] && $now->isWeekend()) {
            return false;
        }
        $time = $now->format('H:i');

        return $settings['hours_start'] < $settings['hours_end']
            ? $time >= $settings['hours_start'] && $time < $settings['hours_end']
            : $time >= $settings['hours_start'] || $time < $settings['hours_end'];
    }

    /** Agentul care răspunde pe site: cel activ legat de site, altfel unul activ al firmei fără site. */
    public static function agentFor(Site $site): ?Agent
    {
        return Agent::query()->where('status', 'active')->where('site_id', $site->id)->orderBy('id')->first()
            ?? Agent::query()->where('status', 'active')->whereNull('site_id')->orderBy('id')->first();
    }

    private static function url(mixed $value, string $schemes): ?string
    {
        $value = trim((string) $value);

        return $value !== '' && strlen($value) <= 255 && filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^'.$schemes.'://#', $value) ? $value : null;
    }

    private static function time(mixed $value, string $default): string
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $value) ? (string) $value : $default;
    }
}
