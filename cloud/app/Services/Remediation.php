<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Lista închisă de acțiuni pe care panoul le poate cere pluginului. Aceeași listă e codată și în plugin:
 * orice altceva e refuzat de ambele părți. Nu există execuție de cod arbitrar.
 */
final class Remediation
{
    /** acțiune => [etichetă, are țintă (plugin / temă)] */
    public const ACTIONS = [
        'scan' => ['Scanează acum', false],
        'backup' => ['Backup acum', false],
        'update_plugin' => ['Actualizează pluginul', true],
        'update_all_plugins' => ['Actualizează toate pluginurile', false],
        'update_theme' => ['Actualizează tema', true],
        'update_core' => ['Actualizează WordPress', false],
        'reinstall_core' => ['Reinstalează fișierele WordPress (aceeași versiune)', false],
        'delete_debug_log' => ['Șterge debug.log', false],
        'delete_readme' => ['Șterge readme.html', false],
        'disable_xmlrpc' => ['Dezactivează XML-RPC', false],
        'disable_file_edit' => ['Dezactivează editorul de fișiere din WordPress', false],
        'block_php_uploads' => ['Blochează PHP în uploads', false],
        'allow_indexing' => ['Permite indexarea în Google', false],
    ];

    /** @return array{0: string, 1: ?string}|null [acțiune, țintă] dintr-un șir „acțiune” sau „acțiune:țintă” */
    public static function parse(string $fix): ?array
    {
        [$action, $target] = array_pad(explode(':', $fix, 2), 2, null);
        if (! isset(self::ACTIONS[$action])) {
            return null;
        }
        $needsTarget = self::ACTIONS[$action][1];
        if ($needsTarget !== ($target !== null && $target !== '')) {
            return null;
        }
        if ($target !== null && (! preg_match('#^[A-Za-z0-9._/-]{1,190}$#', $target) || str_contains($target, '..') || str_starts_with($target, '/'))) {
            return null;
        }

        return [$action, $target];
    }

    public static function label(string $fix): string
    {
        $parsed = self::parse($fix);

        return $parsed ? self::ACTIONS[$parsed[0]][0] : $fix;
    }
}
