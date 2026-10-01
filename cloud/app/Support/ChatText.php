<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Afișează textul agentului cu formatarea de bază (îngroșat, linkuri), după escapare completă.
 * Linkurile sunt permise doar relative sau https, și se deschid separat, fără referrer.
 */
final class ChatText
{
    public static function render(string $text): HtmlString
    {
        $html = e($text);
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
        $html = preg_replace_callback('/\[([^\]\n]{1,200})\]\(((?:https:\/\/|\/(?!\/))[^\s)]{0,500})\)/', function (array $m): string {
            return '<a href="'.$m[2].'" target="_blank" rel="nofollow noopener noreferrer">'.$m[1].'</a>';
        }, (string) $html);

        return new HtmlString((string) $html);
    }
}
