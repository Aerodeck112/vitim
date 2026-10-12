<?php

declare(strict_types=1);

namespace App\Knowledge;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Textul util al unei pagini HTML pentru agent: titlu, descriere, conținutul principal (fără meniu, subsol,
 * scripturi, bannere de cookie) și, pentru produse, numele, prețul și stocul (din datele structurate ale paginii).
 */
final class PageExtractor
{
    public const MAX_CONTENT = 12000;

    private const DROP = ['script', 'style', 'noscript', 'svg', 'iframe', 'template', 'form', 'nav', 'aside', 'button', 'select', 'canvas', 'video', 'audio', 'object'];

    private const BLOCK = ['p', 'div', 'section', 'article', 'main', 'ul', 'ol', 'table', 'tr', 'blockquote', 'dl', 'dt', 'dd', 'figure', 'figcaption', 'address', 'pre'];

    /** @return array{title: string, description: string, content: string, kind: string, price: ?string, links: list<string>} */
    public function extract(string $html): array
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xp = new DOMXPath($doc);

        // titlul paginii: titlul principal (h1), mai precis decât <title>, care pe multe site-uri e același peste tot
        $h1 = self::clean((string) $xp->evaluate('string((//main//h1 | //article//h1 | //h1)[1])'));
        $title = $h1 !== '' && mb_strlen($h1) <= 160 ? $h1 : ($this->meta($xp, 'og:title') ?: trim((string) $xp->evaluate('string(//title)')));
        $description = $this->meta($xp, 'description') ?: $this->meta($xp, 'og:description');
        [$kind, $price, $productText] = $this->product($xp);

        $links = [];
        foreach ($xp->query('//a[@href]') ?: [] as $a) {
            if ($a instanceof DOMElement && ! preg_match('/\bnofollow\b/i', $a->getAttribute('rel'))) {
                $links[] = html_entity_decode($a->getAttribute('href'));
            }
        }

        foreach (self::DROP as $tag) {
            foreach (iterator_to_array($xp->query('//'.$tag) ?: []) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }
        // antetul și subsolul site-ului (dar nu antetul unui articol, care are titlul paginii)
        foreach (iterator_to_array($xp->query('//header[not(ancestor::main) and not(ancestor::article)] | //footer[not(ancestor::main) and not(ancestor::article)]') ?: []) as $node) {
            $node->parentNode?->removeChild($node);
        }
        // bannere de cookie, ferestre pop-up, coșul flotant
        foreach (iterator_to_array($xp->query("//*[contains(translate(@class,'COKIEPNS','cokiepns'),'cookie') or contains(translate(@id,'COKIEPNS','cokiepns'),'cookie') or contains(@class,'popup') or contains(@class,'modal') or contains(@class,'mini-cart') or contains(@class,'screen-reader')]") ?: []) as $node) {
            $node->parentNode?->removeChild($node);
        }
        $root = $xp->query('//main')?->item(0) ?? $xp->query('//article')?->item(0) ?? $xp->query('//*[@id="content"]')?->item(0) ?? $xp->query('//body')?->item(0);
        $content = $root ? $this->text($root) : '';
        $content = trim(preg_replace(["/[ \t\x{00A0}]+/u", "/ *\n */", "/\n{3,}/"], [' ', "\n", "\n\n"], $content) ?? '');
        if ($productText !== '') {
            $content = $productText."\n\n".$content;
        }
        if (mb_strlen($content) > self::MAX_CONTENT) {
            $content = rtrim(mb_substr($content, 0, self::MAX_CONTENT - 1)).'…';
        }

        return [
            'title' => mb_substr(self::clean($title), 0, 300),
            'description' => mb_substr(self::clean($description), 0, 600),
            'content' => $content,
            'kind' => $kind,
            'price' => $price,
            'links' => array_values(array_unique($links)),
        ];
    }

    private function text(DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $out .= $child->textContent;

                continue;
            }
            if (! $child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            $inner = $this->text($child);
            $out .= match (true) {
                (bool) preg_match('/^h[1-6]$/', $tag) => "\n\n".trim($inner)."\n",
                $tag === 'li' => "\n- ".trim($inner),
                $tag === 'br' => "\n",
                $tag === 'td' || $tag === 'th' => ' '.trim($inner).' |',
                in_array($tag, self::BLOCK, true) => "\n\n".$inner."\n\n",
                default => $inner,
            };
        }

        return $out;
    }

    private function meta(DOMXPath $xp, string $name): string
    {
        $q = str_contains($name, ':') ? "//meta[@property='{$name}']/@content" : "//meta[translate(@name,'DESCRIPTION','description')='{$name}']/@content";

        return trim((string) $xp->evaluate("string({$q})"));
    }

    /** Datele de produs din JSON-LD (schema.org Product), altfel din meta product:price. @return array{0: string, 1: ?string, 2: string} */
    private function product(DOMXPath $xp): array
    {
        foreach ($xp->query('//script[@type="application/ld+json"]') ?: [] as $script) {
            $data = json_decode((string) $script->textContent, true);
            foreach ($this->nodes(is_array($data) ? $data : []) as $node) {
                $types = (array) ($node['@type'] ?? []);
                if (! in_array('Product', $types, true)) {
                    continue;
                }
                $offer = $node['offers'] ?? [];
                $offer = isset($offer[0]) ? $offer[0] : $offer;
                $amount = $offer['price'] ?? $offer['lowPrice'] ?? null;
                $currency = (string) ($offer['priceCurrency'] ?? '');
                $price = is_numeric($amount) ? self::money((float) $amount, $currency) : null;
                if ($price && isset($offer['highPrice']) && is_numeric($offer['highPrice']) && (float) $offer['highPrice'] > (float) $amount) {
                    $price = 'de la '.$price;
                }
                $stock = match (true) {
                    str_contains((string) ($offer['availability'] ?? ''), 'OutOfStock') => 'stoc epuizat',
                    str_contains((string) ($offer['availability'] ?? ''), 'PreOrder') || str_contains((string) ($offer['availability'] ?? ''), 'BackOrder') => 'la comandă',
                    str_contains((string) ($offer['availability'] ?? ''), 'InStock') => 'în stoc',
                    default => '',
                };
                $lines = array_filter([
                    'Produs: '.self::clean((string) ($node['name'] ?? '')),
                    $price ? 'Preț: '.$price : null,
                    $stock !== '' ? 'Disponibilitate: '.$stock : null,
                    ! empty($node['sku']) ? 'Cod: '.self::clean((string) $node['sku']) : null,
                    ! empty($node['brand']['name'] ?? null) ? 'Marcă: '.self::clean((string) $node['brand']['name']) : null,
                ]);

                return ['product', $price ? trim($price.($stock !== '' ? ', '.$stock : '')) : null, implode("\n", $lines)];
            }
        }
        $amount = $this->meta($xp, 'product:price:amount');
        if (is_numeric($amount)) {
            $price = self::money((float) $amount, $this->meta($xp, 'product:price:currency'));

            return ['product', $price, 'Preț: '.$price];
        }

        return ['page', null, ''];
    }

    /** @param array<mixed> $data @return list<array<string, mixed>> */
    private function nodes(array $data): array
    {
        if (isset($data['@graph']) && is_array($data['@graph'])) {
            return array_values(array_filter($data['@graph'], 'is_array'));
        }
        if (array_is_list($data)) {
            return array_values(array_filter($data, 'is_array'));
        }

        return [$data];
    }

    private static function money(float $amount, string $currency): string
    {
        $currency = strtoupper(trim($currency));

        return number_format($amount, fmod($amount, 1.0) == 0.0 ? 0 : 2, ',', '.').' '.(in_array($currency, ['', 'RON'], true) ? 'lei' : $currency);
    }

    private static function clean(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }
}
