<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Agent;
use App\Models\Site;

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

        return [
            'enabled' => (bool) ($input['enabled'] ?? false),
            'color' => preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : self::DEFAULTS['color'],
            'position' => ($input['position'] ?? '') === 'left' ? 'left' : 'right',
            'title' => mb_substr(trim((string) ($input['title'] ?? '')), 0, 60) ?: null,
            'launcher' => mb_substr(trim((string) ($input['launcher'] ?? '')), 0, 40) ?: self::DEFAULTS['launcher'],
            'privacy_url' => filter_var($input['privacy_url'] ?? null, FILTER_VALIDATE_URL) && preg_match('#^https?://#', (string) $input['privacy_url']) ? mb_substr((string) $input['privacy_url'], 0, 255) : null,
        ];
    }

    /** Agentul care răspunde pe site: cel activ legat de site, altfel unul activ al firmei fără site. */
    public static function agentFor(Site $site): ?Agent
    {
        return Agent::query()->where('status', 'active')->where('site_id', $site->id)->orderBy('id')->first()
            ?? Agent::query()->where('status', 'active')->whereNull('site_id')->orderBy('id')->first();
    }
}
