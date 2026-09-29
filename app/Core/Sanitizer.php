<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Curăță HTML-ul din editor: păstrează doar etichete și atribute sigure.
 */
final class Sanitizer
{
    private const TAGS = [
        'p' => ['class'], 'br' => [], 'hr' => [], 'h2' => ['id', 'class'], 'h3' => ['id', 'class'], 'h4' => ['id', 'class'],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'mark' => [], 'small' => [], 'sup' => [], 'sub' => [],
        'ul' => ['class'], 'ol' => ['class', 'start'], 'li' => [],
        'a' => ['href', 'title', 'target', 'rel', 'class'],
        'blockquote' => ['class'], 'code' => [], 'pre' => [],
        'img' => ['src', 'alt', 'width', 'height', 'loading', 'class', 'srcset', 'sizes'],
        'figure' => ['class'], 'figcaption' => [],
        'table' => ['class'], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan', 'scope'], 'td' => ['colspan', 'rowspan'],
        'div' => ['class'], 'span' => ['class'], 'details' => [], 'summary' => [],
        'iframe' => ['src', 'width', 'height', 'title', 'allow', 'allowfullscreen', 'loading'],
    ];

    private const IFRAME_HOSTS = ['www.youtube.com', 'www.youtube-nocookie.com', 'player.vimeo.com', 'www.google.com'];

    public static function html(?string $html, bool $email = false): string
    {
        $html = trim((string)$html);
        if ($html === '') {
            return '';
        }
        $doc = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $root = $doc->getElementById('__root');
        if (!$root) {
            return '';
        }
        $allowed = self::TAGS;
        if ($email) {
            // în email permitem stil inline pe câteva elemente
            foreach (['p', 'a', 'span', 'div', 'td', 'th', 'table', 'img', 'h2', 'h3', 'h4'] as $t) {
                $allowed[$t][] = 'style';
                $allowed[$t][] = 'align';
            }
            $allowed['table'] = array_merge($allowed['table'], ['width', 'cellpadding', 'cellspacing', 'border', 'role']);
            $allowed['td'] = array_merge($allowed['td'], ['width', 'valign', 'bgcolor']);
        }
        self::clean($root, $allowed);
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }

    private static function clean(\DOMNode $node, array $allowed): void
    {
        $children = [];
        foreach ($node->childNodes as $c) {
            $children[] = $c;
        }
        foreach ($children as $child) {
            if ($child instanceof \DOMComment) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, ['script', 'style', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'meta', 'link', 'base', 'svg', 'math'], true)) {
                $node->removeChild($child);
                continue;
            }
            if (!isset($allowed[$tag])) {
                // păstrăm conținutul, eliminăm eticheta
                self::clean($child, $allowed);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            $attrs = [];
            foreach ($child->attributes as $a) {
                $attrs[] = $a->name;
            }
            foreach ($attrs as $name) {
                $lname = strtolower($name);
                $val = $child->getAttribute($name);
                if (!in_array($lname, $allowed[$tag], true) || str_starts_with($lname, 'on')) {
                    $child->removeAttribute($name);
                    continue;
                }
                if (in_array($lname, ['href', 'src'], true) && !self::safeUrl($val)) {
                    $child->removeAttribute($name);
                    continue;
                }
                if ($lname === 'style' && preg_match('/expression|javascript|url\s*\(/i', $val)) {
                    $child->removeAttribute($name);
                }
            }
            if ($tag === 'iframe') {
                $host = parse_url($child->getAttribute('src'), PHP_URL_HOST);
                if (!in_array($host, self::IFRAME_HOSTS, true)) {
                    $node->removeChild($child);
                    continue;
                }
                $child->setAttribute('loading', 'lazy');
            }
            if ($tag === 'a' && $child->getAttribute('target') === '_blank') {
                $child->setAttribute('rel', 'noopener');
            }
            if ($tag === 'img' && !$child->hasAttribute('loading')) {
                $child->setAttribute('loading', 'lazy');
            }
            self::clean($child, $allowed);
        }
    }

    private static function safeUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '/') || str_starts_with($url, '#') || str_starts_with($url, '?')) {
            return true;
        }
        return (bool)preg_match('#^(https?:|mailto:|tel:)#i', $url);
    }

    public static function text(?string $s, int $max = 5000): string
    {
        $s = trim(strip_tags((string)$s));
        $s = (string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s);
        return mb_substr($s, 0, $max);
    }

    /** Adaugă id-uri pe titluri (pentru cuprins) și întoarce [html, cuprins]. */
    public static function withToc(string $html): array
    {
        $toc = [];
        $used = [];
        $html = (string)preg_replace_callback('#<h([23])([^>]*)>(.*?)</h\1>#si', function ($m) use (&$toc, &$used) {
            $text = trim(html_entity_decode(strip_tags($m[3]), ENT_QUOTES, 'UTF-8'));
            if (preg_match('/id="([^"]+)"/', $m[2], $idm)) {
                $id = $idm[1];
                $attrs = $m[2];
            } else {
                $id = slugify($text);
                $base = $id;
                $i = 2;
                while (isset($used[$id])) {
                    $id = $base . '-' . $i++;
                }
                $attrs = $m[2] . ' id="' . $id . '"';
            }
            $used[$id] = true;
            $toc[] = ['level' => (int)$m[1], 'id' => $id, 'text' => $text];
            return '<h' . $m[1] . $attrs . '>' . $m[3] . '</h' . $m[1] . '>';
        }, $html);
        return [$html, $toc];
    }
}
