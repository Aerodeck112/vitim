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
                fwrite($fh, "-- Bunatati de la Michele backup " . date('c') . " (v" . APP_VERSION . ")\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
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
