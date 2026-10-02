<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CampaignRecipient;
use App\Models\ContactEvent;

/**
 * Deschideri (pixel invizibil) și click-uri (redirect semnat) pe fiecare destinatar.
 * Semnătura leagă linkul de destinatar: adresa de redirect nu poate fi folosită pentru alte URL-uri (open redirect).
 * Notă: Apple Mail deschide automat imaginile, deci rata de deschidere e orientativă; click-urile sunt sigure.
 */
final class Tracking
{
    public function __construct(private readonly ContactActivity $activity) {}

    public static function pixel(string $code): string
    {
        return route('track.open', $code);
    }

    public static function link(string $code, string $url): string
    {
        return route('track.click', ['code' => $code, 'u' => $url, 's' => self::signature($code, $url)]);
    }

    public static function valid(string $code, string $url, string $signature): bool
    {
        return hash_equals(self::signature($code, $url), $signature);
    }

    /** Înlocuiește linkurile https din HTML cu linkuri urmărite (fără cel de dezabonare). */
    public static function rewrite(string $html, string $code): string
    {
        return (string) preg_replace_callback('/href="(https:\/\/[^"]+)"/', function (array $m) use ($code): string {
            $url = html_entity_decode($m[1]);
            if (str_contains($url, '/d/')) {
                return $m[0];
            }

            return 'href="'.e(self::link($code, $url)).'"';
        }, $html);
    }

    public function opened(CampaignRecipient $recipient): void
    {
        $first = $recipient->opened_at === null;
        $recipient->forceFill(['opened_at' => $recipient->opened_at ?? now(), 'open_count' => $recipient->open_count + 1])->save();
        if ($first && $recipient->contact_id) {
            $this->activity->record($recipient->contact_id, 'email_opened', [], null, $recipient->campaign_id, $recipient->flow_id ?? null, $recipient->id);
        }
    }

    public function clicked(CampaignRecipient $recipient, string $url): void
    {
        $recipient->forceFill([
            'opened_at' => $recipient->opened_at ?? now(), 'clicked_at' => $recipient->clicked_at ?? now(), 'click_count' => $recipient->click_count + 1,
        ])->save();
        if ($recipient->contact_id) {
            // un click pe același link în ultimul minut nu se numără de două ori (dublu-click, scanere)
            $recent = ContactEvent::query()->where('recipient_id', $recipient->id)->where('type', 'email_clicked')->where('occurred_at', '>=', now()->subMinute())->exists();
            if (! $recent) {
                $this->activity->record($recipient->contact_id, 'email_clicked', ['url' => mb_substr($url, 0, 500)], null, $recipient->campaign_id, $recipient->flow_id ?? null, $recipient->id);
            }
        }
    }

    private static function signature(string $code, string $url): string
    {
        return substr(hash_hmac('sha256', $code.'|'.$url, (string) config('app.key')), 0, 20);
    }
}
