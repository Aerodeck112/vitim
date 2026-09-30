<?php
declare(strict_types=1);

namespace App\Core;

use ZipArchive;

/**
 * Backup-uri: cod, bază de date (SQL / SQLite) și fișiere încărcate.
 */
final class Backup
{
    public static function dir(): string
    {
        $d = STORAGE_PATH . '/backups';
        if (!is_dir($d)) {
            @mkdir($d, 0755, true);
        }
        return $d;
    }

    /** Arhivează codul aplicației (fără uploads/storage/config). */
    public static function code(string $label = ''): ?string
    {
        if (!class_exists(ZipArchive::class)) {
            return null;
        }
        $name = 'cod-v' . APP_VERSION . '-' . date('Ymd-His') . ($label ? '-' . slugify($label) : '') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open(self::dir() . '/' . $name, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }
        $root = ROOT_PATH;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile()) {
                continue;
            }
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
            if (preg_match('#^(uploads|storage|dist|node_modules|\.git)/#', $rel) || $rel === 'app/config.php') {
                continue;
            }
            $zip->addFile($f->getPathname(), $rel);
        }
        $zip->close();
        self::prune('cod-', 5);
        return $name;
    }

    /** Exportă baza de date. MySQL → .sql, SQLite → copie a fișierului. */
    public static function database(string $label = ''): ?string
    {
        $base = 'baza-date-' . date('Ymd-His') . ($label ? '-' . slugify($label) : '');
        try {
            if (DB::driver() === 'sqlite') {
                $src = (string)config('db.path', STORAGE_PATH . '/database.sqlite');
                DB::pdo()->exec('PRAGMA wal_checkpoint(TRUNCATE)');
                $name = $base . '.sqlite';
                copy($src, self::dir() . '/' . $name);
            } else {
                $name = $base . '.sql';
                $fh = fopen(self::dir() . '/' . $name, 'w');
                fwrite($fh, "-- VITIM backup " . date('c') . " (v" . APP_VERSION . ")\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
                foreach (DB::all('SHOW TABLES') as $t) {
                    $table = (string)array_values($t)[0];
                    $create = DB::row("SHOW CREATE TABLE `$table`");
                    fwrite($fh, "DROP TABLE IF EXISTS `$table`;\n" . array_values($create)[1] . ";\n\n");
                    $st = DB::pdo()->query("SELECT * FROM `$table`");
                    $batch = [];
                    while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
                        $vals = array_map(fn($v) => $v === null ? 'NULL' : DB::pdo()->quote((string)$v), array_values($row));
                        $batch[] = '(' . implode(',', $vals) . ')';
                        if (count($batch) >= 200) {
                            fwrite($fh, "INSERT INTO `$table` VALUES " . implode(",\n", $batch) . ";\n");
                            $batch = [];
                        }
                    }
                    if ($batch) {
                        fwrite($fh, "INSERT INTO `$table` VALUES " . implode(",\n", $batch) . ";\n");
                    }
                    fwrite($fh, "\n");
                }
                fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
                fclose($fh);
            }
            if (class_exists(ZipArchive::class)) {
                $zip = new ZipArchive();
                $zname = $name . '.zip';
                if ($zip->open(self::dir() . '/' . $zname, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                    $zip->addFile(self::dir() . '/' . $name, $name);
                    $zip->close();
                    @unlink(self::dir() . '/' . $name);
                    $name = $zname;
                }
            }
            self::prune('baza-date-', 10);
            return $name;
        } catch (\Throwable $e) {
            log_error($e);
            return null;
        }
    }

    /**
     * Backup-ul zilnic: arhiva bazei de date, trimisă pe email (copie în afara serverului).
     * Rulează din cron sau, dacă cron-ul nu e configurat, după o vizită pe site (vezi dailyAfterResponse).
     * @return string|null mesaj pentru jurnal; null dacă nu era cazul
     */
    public static function daily(bool $force = false): ?string
    {
        if (Settings::get('backup_auto', '1') !== '1') {
            return null;
        }
        $last = strtotime((string)Settings::get('backup_auto_last', '')) ?: 0;
        if (!$force && $last > time() - 20 * 3600) {
            return null;
        }
        $lock = @fopen(STORAGE_PATH . '/backup.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return null;
        }
        try {
            Settings::set('backup_auto_last', DB::now()); // înainte de lucru: o singură încercare pe zi, chiar dacă eșuează
            $name = self::database('zilnic');
            if (!$name) {
                return 'Backup zilnic: arhiva nu a putut fi creată.';
            }
            $to = (string)(Settings::get('backup_email', '') ?: Settings::get('notify_email', ''));
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return "Backup zilnic: $name (fără email configurat, rămâne doar pe server).";
            }
            $path = self::dir() . '/' . $name;
            $size = (int)filesize($path);
            $site = parse_url(abs_url('/'), PHP_URL_HOST) ?: 'site';
            $body = '<p>Backup-ul zilnic al bazei de date pentru <strong>' . e($site) . '</strong> (' . date('d.m.Y H:i') . ').</p>'
                . '<p>Păstrează aceste emailuri: dacă baza de date se pierde, arhiva atașată se importă din cPanel → phpMyAdmin → Import.</p>';
            // limita obișnuită a serverelor de email este ~20–25 MB
            $attach = $size <= 15 * 1048576 ? [$path => $name] : [];
            if (!$attach) {
                $body .= '<p><strong>Arhiva are ' . round($size / 1048576, 1) . ' MB și e prea mare pentru email.</strong> Descarc-o din Panou → Sistem.</p>';
            }
            [$ok, $err] = Mailer::send($to, 'Backup zilnic ' . $site . ' – ' . date('d.m.Y'), Mailer::layout($body), ['kind' => 'backup', 'attachments' => $attach]);
            return $ok ? "Backup zilnic: $name trimis la $to." : "Backup zilnic: $name creat, dar emailul a eșuat: $err";
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Fără cron configurat: backup-ul zilnic rulează după ce vizitatorul și-a primit deja pagina. */
    public static function dailyAfterResponse(): void
    {
        if (Settings::get('backup_auto', '1') !== '1') {
            return;
        }
        $last = strtotime((string)Settings::get('backup_auto_last', '')) ?: 0;
        if ($last > time() - 20 * 3600 || !function_exists('fastcgi_finish_request')) {
            return;
        }
        fastcgi_finish_request();
        @set_time_limit(120);
        try {
            if ($msg = self::daily()) {
                log_error($msg);
            }
        } catch (\Throwable $e) {
            log_error($e);
        }
    }

    public static function uploads(): ?string
    {
        if (!class_exists(ZipArchive::class)) {
            return null;
        }
        $name = 'uploads-' . date('Ymd-His') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open(self::dir() . '/' . $name, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(UPLOADS_PATH, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile()) {
                $zip->addFile($f->getPathname(), 'uploads/' . str_replace('\\', '/', substr($f->getPathname(), strlen(UPLOADS_PATH) + 1)));
            }
        }
        $zip->close();
        self::prune('uploads-', 3);
        return $name;
    }

    public static function list(): array
    {
        $out = [];
        // fără GLOB_BRACE: nu există pe unele servere (musl/Alpine)
        foreach (array_merge(glob(self::dir() . '/*.zip') ?: [], glob(self::dir() . '/*.sql') ?: [], glob(self::dir() . '/*.sqlite') ?: []) as $f) {
            $out[] = ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f)];
        }
        usort($out, fn($a, $b) => $b['time'] <=> $a['time']);
        return $out;
    }

    public static function path(string $name): ?string
    {
        $name = basename($name);
        $p = self::dir() . '/' . $name;
        return preg_match('/^[A-Za-z0-9._-]+$/', $name) && is_file($p) ? $p : null;
    }

    private static function prune(string $prefix, int $keep): void
    {
        $files = glob(self::dir() . '/' . $prefix . '*') ?: [];
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($files, $keep) as $f) {
            @unlink($f);
        }
    }
}
