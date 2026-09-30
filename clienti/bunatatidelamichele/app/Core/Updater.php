<?php
declare(strict_types=1);

namespace App\Core;

use ZipArchive;

/**
 * Actualizare din panou: încarci arhiva .zip a versiunii noi → backup automat → fișiere înlocuite → migrări.
 * Datele tale (config, baza de date, uploads, storage) nu sunt atinse niciodată.
 */
final class Updater
{
    /** Căi care nu se suprascriu niciodată la actualizare. */
    private const PROTECTED = ['app/config.php', 'uploads/', 'storage/', '.htaccess.custom', '.user.ini', 'php.ini', '.well-known/'];

    /** @return array{0: bool, 1: string, 2: array} */
    public static function apply(string $zipFile, bool $allowDowngrade = false): array
    {
        if (!class_exists(ZipArchive::class)) {
            return [false, 'Extensia PHP ZipArchive lipsește. Activeaz-o din cPanel → Select PHP Version → Extensions (zip).', []];
        }
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) {
            return [false, 'Arhiva nu poate fi deschisă. Verifică să fie un .zip valid.', []];
        }
        // Arhiva poate avea fișierele direct în rădăcină sau într-un singur director (ex. bunatatidelamichele-1.1.0/)
        $prefix = '';
        if ($zip->locateName('VERSION') === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $n = (string)$zip->getNameIndex($i);
                if (preg_match('#^([^/]+/)VERSION$#', $n, $m)) {
                    $prefix = $m[1];
                    break;
                }
            }
        }
        $newVersion = trim((string)$zip->getFromName($prefix . 'VERSION'));
        if ($newVersion === '' || $zip->locateName($prefix . 'app/bootstrap.php') === false || $zip->locateName($prefix . 'index.php') === false || $zip->locateName($prefix . 'app/Core/Shop.php') === false) {
            $zip->close();
            return [false, 'Arhiva nu pare a fi o actualizare a magazinului Bunătăți de la Michele (lipsesc VERSION, index.php, app/bootstrap.php sau app/Core/Shop.php).', []];
        }
        if (!$allowDowngrade && version_compare($newVersion, APP_VERSION, '<')) {
            $zip->close();
            return [false, "Arhiva conține versiunea $newVersion, mai veche decât cea instalată (" . APP_VERSION . '). Bifează „permite revenirea la o versiune mai veche” dacă asta vrei.', []];
        }

        // 1. backup cod + bază de date
        $backup = Backup::code('inainte-de-' . $newVersion);
        $dbBackup = Backup::database('inainte-de-' . $newVersion);

        // 2. extragere într-un director temporar (validare căi – fără „../”)
        $tmp = STORAGE_PATH . '/tmp/update-' . bin2hex(random_bytes(4));
        @mkdir($tmp, 0755, true);
        $files = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            if ($prefix !== '' && !str_starts_with($name, $prefix)) {
                continue;
            }
            $rel = substr($name, strlen($prefix));
            if ($rel === '' || str_ends_with($rel, '/')) {
                continue;
            }
            if (str_contains($rel, '..') || str_starts_with($rel, '/') || preg_match('#^[a-zA-Z]:#', $rel)) {
                continue;
            }
            if (self::isProtected($rel)) {
                continue;
            }
            $dest = $tmp . '/' . $rel;
            @mkdir(dirname($dest), 0755, true);
            $stream = $zip->getStream($name);
            if (!$stream) {
                continue;
            }
            file_put_contents($dest, $stream);
            fclose($stream);
            $files[] = $rel;
        }
        $zip->close();

        // 3. copiere peste instalarea curentă
        $errors = [];
        foreach ($files as $rel) {
            $target = ROOT_PATH . '/' . $rel;
            @mkdir(dirname($target), 0755, true);
            if (!@copy($tmp . '/' . $rel, $target)) {
                $errors[] = $rel;
            }
        }
        // fișiere eliminate în versiunea nouă (listate în REMOVED.txt)
        $removedList = is_file($tmp . '/REMOVED.txt') ? file($tmp . '/REMOVED.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
        foreach ($removedList ?: [] as $rm) {
            $rm = trim($rm);
            if ($rm !== '' && !str_contains($rm, '..') && !self::isProtected($rm) && is_file(ROOT_PATH . '/' . $rm)) {
                @unlink(ROOT_PATH . '/' . $rm);
            }
        }
        self::rrmdir($tmp);
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        if ($errors) {
            return [false, 'Unele fișiere nu au putut fi scrise (permisiuni): ' . implode(', ', array_slice($errors, 0, 10)) . '. Backup-ul este în Sistem → Backup-uri.', ['backup' => $backup]];
        }

        // 4. migrări (în proces separat de cod nou – le rulăm la prima cerere, dar încercăm și aici)
        $ran = [];
        try {
            $ran = self::runMigrationsFresh();
            Settings::set('db_version', $newVersion);
        } catch (\Throwable $e) {
            log_error($e);
        }
        Cache::clear();
        return [true, "Actualizare reușită la versiunea $newVersion (" . count($files) . ' fișiere).' . ($ran ? ' Migrări rulate: ' . implode(', ', $ran) . '.' : ''), ['backup' => $backup, 'db' => $dbBackup, 'version' => $newVersion]];
    }

    /** Migrările noi sunt fișiere noi pe disc – le putem include direct. */
    private static function runMigrationsFresh(): array
    {
        return Migrator::run();
    }

    private static function isProtected(string $rel): bool
    {
        foreach (self::PROTECTED as $p) {
            if ($rel === $p || (str_ends_with($p, '/') && str_starts_with($rel, $p))) {
                return true;
            }
        }
        return false;
    }

    public static function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
