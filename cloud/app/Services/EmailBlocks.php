<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Emailurile construite din blocuri (editorul vizual): validare strictă (doar tipuri și câmpuri cunoscute,
 * linkuri https, culori hex) și randare în HTML compatibil cu clienții de email (tabele, stiluri inline).
 * Textele trec prin e() după completarea variabilelor; HTML-ul introdus de utilizator nu ajunge în email.
 */
final class EmailBlocks
{
    public const TYPES = [
        'logo' => 'Logo',
        'heading' => 'Titlu',
        'text' => 'Text',
        'image' => 'Imagine',
        'button' => 'Buton',
        'columns' => 'Două coloane',
        'product' => 'Produs',
        'divider' => 'Linie',
        'spacer' => 'Spațiu',
        'social' => 'Rețele sociale',
    ];

    private const MAX_BLOCKS = 60;

    /** @param mixed $blocks @return list<array<string, mixed>> */
    public static function clean(mixed $blocks): array
    {
        $out = [];
        foreach (array_slice(is_array($blocks) ? $blocks : [], 0, self::MAX_BLOCKS) as $b) {
            $b = (array) $b;
            $type = (string) ($b['type'] ?? '');
            $clean = match ($type) {
                'logo' => ['align' => self::align($b)],
                'heading' => ['text' => self::str($b['text'] ?? '', 200), 'size' => ($b['size'] ?? '') === 'h2' ? 'h2' : 'h1', 'align' => self::align($b)],
                'text' => ['text' => self::str($b['text'] ?? '', 5000), 'align' => self::align($b)],
                'image' => ['url' => self::url($b['url'] ?? ''), 'alt' => self::str($b['alt'] ?? '', 200), 'link' => self::url($b['link'] ?? ''), 'width' => max(20, min(100, (int) ($b['width'] ?? 100)))],
                'button' => ['label' => self::str($b['label'] ?? 'Vezi oferta', 60), 'url' => self::url($b['url'] ?? ''), 'align' => self::align($b, 'center'), 'color' => self::color($b['color'] ?? '')],
                'columns' => ['left_image' => self::url($b['left_image'] ?? ''), 'left_text' => self::str($b['left_text'] ?? '', 1500), 'left_link' => self::url($b['left_link'] ?? ''),
                    'right_image' => self::url($b['right_image'] ?? ''), 'right_text' => self::str($b['right_text'] ?? '', 1500), 'right_link' => self::url($b['right_link'] ?? '')],
                'product' => ['product_id' => (int) ($b['product_id'] ?? 0) ?: null, 'name' => self::str($b['name'] ?? '', 200), 'price' => self::str($b['price'] ?? '', 60),
                    'image' => self::url($b['image'] ?? ''), 'url' => self::url($b['url'] ?? ''), 'label' => self::str($b['label'] ?? 'Cumpără acum', 40), 'description' => self::str($b['description'] ?? '', 500)],
                'divider' => [],
                'spacer' => ['height' => max(8, min(80, (int) ($b['height'] ?? 24)))],
                'social' => [],
                default => null,
            };
            if ($clean !== null) {
                $out[] = ['type' => $type] + $clean;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @param  array<string, mixed>  $brand  logo_url, color, font, facebook, instagram, website
     * @param  callable(string): string  $fill  completează variabilele într-un text
     */
    public static function html(array $blocks, array $brand, callable $fill, string $footer, string $unsubscribeUrl, ?string $preheader = null): string
    {
        $color = self::color($brand['color'] ?? '') ?? '#2f6bff';
        $font = in_array($brand['font'] ?? '', ['Georgia, serif', 'Verdana, sans-serif', 'Trebuchet MS, sans-serif'], true) ? $brand['font'] : 'Arial, Helvetica, sans-serif';
        $rows = '';
        foreach ($blocks as $b) {
            $rows .= '<tr><td style="padding:0 32px">'.self::block($b, $brand, $color, $fill).'</td></tr>';
        }
        $pre = $preheader ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0">'.e($fill($preheader)).'</div>' : '';

        return '<!doctype html><html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title></title></head>'
            .'<body style="margin:0;padding:0;background:#f4f6fb">'.$pre
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fb"><tr><td align="center" style="padding:24px 12px">'
            .'<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:12px;font-family:'.e($font).';color:#0f172a;font-size:16px;line-height:1.55">'
            .'<tr><td style="height:24px"></td></tr>'.$rows.'<tr><td style="height:24px"></td></tr></table>'
            .'<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px"><tr><td style="padding:16px 12px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:#64748b;text-align:center">'
            .nl2br(e($footer)).'<br><a href="'.e($unsubscribeUrl).'" style="color:#64748b">Dezabonare</a></td></tr></table>'
            .'</td></tr></table></body></html>';
    }

    /** Varianta text (pentru clienții de email fără HTML și pentru filtrele anti-spam). @param list<array<string, mixed>> $blocks */
    public static function text(array $blocks, callable $fill): string
    {
        $lines = [];
        foreach ($blocks as $b) {
            $lines[] = match ($b['type']) {
                'heading' => mb_strtoupper($fill((string) $b['text'])),
                'text' => preg_replace('/\*\*(.+?)\*\*/s', '$1', $fill((string) $b['text'])),
                'button' => $b['url'] ? $fill((string) $b['label']).': '.$b['url'] : '',
                'image' => $b['link'] ? (string) $b['link'] : '',
                'product' => trim($fill((string) $b['name']).' '.$b['price'])."\n".($b['url'] ?? ''),
                'columns' => trim($fill((string) $b['left_text'])."\n\n".$fill((string) $b['right_text'])),
                default => '',
            };
        }

        return trim(implode("\n\n", array_filter(array_map('trim', $lines))));
    }

    /** @param array<string, mixed> $b @param array<string, mixed> $brand */
    private static function block(array $b, array $brand, string $color, callable $fill): string
    {
        $align = $b['align'] ?? 'left';

        return match ($b['type']) {
            'logo' => ! empty($brand['logo_url'])
                ? '<div style="text-align:'.$align.';padding:8px 0 16px"><img src="'.e($brand['logo_url']).'" alt="'.e((string) ($brand['name'] ?? 'Logo')).'" style="max-width:180px;max-height:72px;height:auto;border:0"></div>' : '',
            'heading' => '<'.$b['size'].' style="margin:8px 0 12px;font-size:'.($b['size'] === 'h1' ? '26px' : '20px').';line-height:1.25;text-align:'.$align.';color:#0f172a">'.e($fill((string) $b['text'])).'</'.$b['size'].'>',
            'text' => '<div style="margin:0 0 14px;text-align:'.$align.'">'.self::rich($fill((string) $b['text']), $color).'</div>',
            'image' => $b['url'] ? '<div style="margin:6px 0 16px;text-align:center">'.self::linked($b['link'] ?? null,
                '<img src="'.e($b['url']).'" alt="'.e($fill((string) $b['alt'])).'" width="'.(int) round(536 * $b['width'] / 100).'" style="width:'.$b['width'].'%;max-width:100%;height:auto;border:0;border-radius:8px;display:inline-block">').'</div>' : '',
            'button' => '<div style="margin:10px 0 18px;text-align:'.$align.'"><a href="'.e($b['url'] ?: '#').'" style="display:inline-block;background:'.($b['color'] ?? $color).';color:#ffffff;text-decoration:none;font-weight:bold;padding:13px 26px;border-radius:8px">'.e($fill((string) $b['label'])).'</a></div>',
            'columns' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:6px 0 16px"><tr>'
                .self::column($b['left_image'] ?? null, (string) $b['left_text'], $b['left_link'] ?? null, $fill, $color).'<td width="16"></td>'
                .self::column($b['right_image'] ?? null, (string) $b['right_text'], $b['right_link'] ?? null, $fill, $color).'</tr></table>',
            'product' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:6px 0 18px;border:1px solid #e5e8f0;border-radius:10px"><tr>'
                .($b['image'] ? '<td width="180" style="padding:12px">'.self::linked($b['url'] ?? null, '<img src="'.e($b['image']).'" alt="'.e($fill((string) $b['name'])).'" width="156" style="width:156px;height:auto;border:0;border-radius:6px">').'</td>' : '')
                .'<td style="padding:12px 16px 12px 4px;vertical-align:top"><div style="font-weight:bold;font-size:17px">'.e($fill((string) $b['name'])).'</div>'
                .($b['price'] ? '<div style="color:'.$color.';font-weight:bold;margin:4px 0">'.e((string) $b['price']).'</div>' : '')
                .($b['description'] ? '<div style="font-size:14px;color:#475569;margin-bottom:8px">'.e($fill((string) $b['description'])).'</div>' : '')
                .($b['url'] ? '<a href="'.e($b['url']).'" style="display:inline-block;background:'.$color.';color:#fff;text-decoration:none;font-weight:bold;padding:9px 18px;border-radius:7px;font-size:14px">'.e($fill((string) $b['label'])).'</a>' : '')
                .'</td></tr></table>',
            'divider' => '<hr style="border:0;border-top:1px solid #e5e8f0;margin:18px 0">',
            'spacer' => '<div style="height:'.(int) $b['height'].'px;line-height:'.(int) $b['height'].'px">&nbsp;</div>',
            'social' => self::social($brand, $color),
            default => '',
        };
    }

    private static function column(?string $image, string $text, ?string $link, callable $fill, string $color): string
    {
        return '<td width="50%" style="vertical-align:top">'.($image ? self::linked($link, '<img src="'.e($image).'" alt="" width="260" style="width:100%;height:auto;border:0;border-radius:8px;margin-bottom:8px">') : '')
            .'<div style="font-size:15px">'.self::rich($fill($text), $color).'</div></td>';
    }

    /** @param array<string, mixed> $brand */
    private static function social(array $brand, string $color): string
    {
        $links = array_filter(['Facebook' => self::url($brand['facebook'] ?? ''), 'Instagram' => self::url($brand['instagram'] ?? ''), 'Site' => self::url($brand['website'] ?? '')]);
        if (! $links) {
            return '';
        }

        return '<div style="text-align:center;margin:12px 0 6px">'.implode(' &nbsp;·&nbsp; ', array_map(fn ($name, $url) => '<a href="'.e($url).'" style="color:'.$color.';font-weight:bold;text-decoration:none">'.$name.'</a>', array_keys($links), $links)).'</div>';
    }

    private static function linked(?string $url, string $html): string
    {
        return $url ? '<a href="'.e($url).'">'.$html.'</a>' : $html;
    }

    /** **îngroșat**, linkuri https, rânduri noi; restul escapat. */
    private static function rich(string $text, string $color): string
    {
        $html = nl2br(e($text));
        $html = (string) preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);

        return (string) preg_replace('#(https://[^\s<]+)#', '<a href="$1" style="color:'.$color.'">$1</a>', $html);
    }

    private static function str(mixed $value, int $max): string
    {
        return mb_substr(str_replace(["\r\n", "\r"], "\n", (string) $value), 0, $max);
    }

    private static function url(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' && strlen($value) <= 1000 && str_starts_with($value, 'https://') && filter_var($value, FILTER_VALIDATE_URL) ? $value : null;
    }

    private static function color(mixed $value): ?string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value) ? strtolower((string) $value) : null;
    }

    /** @param array<string, mixed> $b */
    private static function align(array $b, string $default = 'left'): string
    {
        return in_array($b['align'] ?? '', ['left', 'center', 'right'], true) ? $b['align'] : $default;
    }
}
