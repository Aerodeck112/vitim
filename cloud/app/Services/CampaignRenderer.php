<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Channel;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Organization;

/**
 * Textul final al unei campanii pentru un destinatar: variabilele {{prenume}}, {{nume}}, {{firma}},
 * plus identificarea firmei și linkul de dezabonare, care nu pot lipsi dintr-un mesaj comercial.
 */
final class CampaignRenderer
{
    public const VARIABLES = ['{{prenume}}' => 'prenumele contactului', '{{nume}}' => 'numele de familie', '{{firma}}' => 'numele firmei tale'];

    /** @return array{subject: ?string, body: string, html: ?string, template: ?array<string, mixed>} */
    public function render(Campaign|MessageContent $content, Organization $organization, ?Contact $contact, string $unsubscribeUrl, bool $ascii = true, ?string $trackingCode = null, array $extra = []): array
    {
        $campaign = $content instanceof Campaign ? MessageContent::of($content) : $content;
        $items = is_array($extra['__items'] ?? null) ? $extra['__items'] : []; // produsele din evenimentul fluxului (blocul „Produsele din coș”)
        unset($extra['__items']);
        $vars = [
            '{{prenume}}' => trim((string) ($contact?->first_name ?? '')),
            '{{nume}}' => trim((string) ($contact?->last_name ?? '')),
            '{{firma}}' => self::company($organization),
        ] + $extra;
        $fill = fn (?string $text) => $text === null ? null : self::tidy(strtr($text, $vars));

        if ($campaign->channel === Channel::Sms) {
            $body = self::plain((string) $fill((string) $campaign->body))."\nDezabonare: ".$unsubscribeUrl;

            return ['subject' => null, 'body' => $ascii ? self::ascii($body) : $body, 'html' => null, 'template' => null];
        }
        if ($campaign->channel === Channel::WhatsApp) {
            $template = (array) $campaign->template;
            $template['variables'] = array_map(fn ($v) => $fill((string) $v) ?: '-', (array) ($template['variables'] ?? []));

            return ['subject' => null, 'body' => $fill((string) $campaign->body) ?? '', 'html' => null, 'template' => $template];
        }

        $footer = self::footer($organization);
        if (! empty($campaign->blocks)) {
            // design din editorul vizual, cu brandul firmei (logo, culoare, font, rețele)
            $brand = (array) ($organization->branding ?? []) + ['name' => self::company($organization)];
            $text = EmailBlocks::text($campaign->blocks, fn (string $t) => (string) $fill($t), $items)."\n\n--\n".$footer."\nDezabonare: ".$unsubscribeUrl;
            $html = EmailBlocks::html($campaign->blocks, $brand, fn (string $t) => (string) $fill($t), $footer, $unsubscribeUrl, $campaign->preheader, $items);
        } else {
            $body = (string) $fill((string) $campaign->body);
            $text = self::plain($body)."\n\n--\n".$footer."\nDezabonare: ".$unsubscribeUrl;
            $html = self::html($body, $footer, $unsubscribeUrl);
        }
        if ($trackingCode !== null) {
            // linkurile urmărite + pixelul de deschidere, doar în mesajele reale (nu în teste și previzualizări)
            $html = str_replace('</body>', '<img src="'.e(Tracking::pixel($trackingCode)).'" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0"></body>', Tracking::rewrite($html, $trackingCode));
        }

        return ['subject' => $fill($campaign->subject), 'body' => $text, 'html' => $html, 'template' => null];
    }

    /** Câte SMS-uri înseamnă textul (GSM: 160 / 153 pe bucată; cu diacritice: 70 / 67). */
    public static function smsParts(string $text): int
    {
        $unicode = (bool) preg_match('/[^\x{0000}-\x{007F}€£¥èéùìòÇØøÅå]/u', $text);
        $length = mb_strlen($text);
        [$single, $multi] = $unicode ? [70, 67] : [160, 153];

        return $length <= $single ? 1 : (int) ceil($length / $multi);
    }

    public static function ascii(string $text): string
    {
        return strtr($text, ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't',
            'Ă' => 'A', 'Â' => 'A', 'Î' => 'I', 'Ș' => 'S', 'Ş' => 'S', 'Ț' => 'T', 'Ţ' => 'T', '„' => '"', '”' => '"', '–' => '-', '…' => '...']);
    }

    public static function company(Organization $organization): string
    {
        return (string) ($organization->company_name ?: $organization->name);
    }

    /** Cine trimite (Legea 365/2002): denumire, CUI, adresă dacă există. */
    public static function footer(Organization $organization): string
    {
        $billing = (array) ($organization->billing_details ?? []);

        return implode(' · ', array_filter([self::company($organization), $organization->vat_id ? 'CUI '.$organization->vat_id : null, $billing['address'] ?? null]))
            ."\nPrimești acest mesaj pentru că ți-ai dat acordul să primești noutăți de la noi.";
    }

    private static function html(string $body, string $footer, string $unsubscribeUrl): string
    {
        $paragraphs = array_filter(preg_split('/\R{2,}/', trim($body)) ?: []);
        $content = implode('', array_map(function (string $p): string {
            $html = nl2br(e($p));
            $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
            $html = preg_replace('#(https://[^\s<]+)#', '<a href="$1" style="color:#2f6bff">$1</a>', (string) $html);

            return '<p style="margin:0 0 16px;line-height:1.55">'.$html.'</p>';
        }, $paragraphs));

        return '<!doctype html><html lang="ro"><body style="margin:0;background:#f4f6fb;font-family:Arial,Helvetica,sans-serif;color:#0f172a">'
            .'<div style="max-width:600px;margin:0 auto;padding:24px"><div style="background:#fff;border-radius:12px;padding:28px;font-size:15px">'.$content.'</div>'
            .'<p style="font-size:12px;color:#64748b;line-height:1.5;margin:16px 4px">'.nl2br(e($footer)).'<br><a href="'.e($unsubscribeUrl).'" style="color:#64748b">Dezabonare</a></p>'
            .'</div></body></html>';
    }

    /** Varianta fără formatare: **îngroșat** devine text simplu. */
    private static function plain(string $text): string
    {
        return (string) preg_replace('/\*\*(.+?)\*\*/s', '$1', $text);
    }

    /** „Bună , Ion” → „Bună, Ion” când lipsește prenumele. */
    private static function tidy(string $text): string
    {
        return (string) preg_replace(['/[ \t]+([,.!?])/u', '/[ \t]{2,}/u'], ['$1', ' '], $text);
    }
}
