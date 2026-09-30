<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Backup-ul bazei de date, fără mysqldump (nu e garantat pe hosting partajat):
 * dump SQL comprimat în storage/app/backups, trimis pe email (copie în afara serverului).
 */
#[Signature('vitim:backup {--no-mail : Doar local, fără email}')]
#[Description('Backup al bazei de date, trimis pe email')]
final class Backup extends Command
{
    private const MAIL_LIMIT_BYTES = 15 * 1024 * 1024;

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir, 0750);
        $path = $dir.'/db-'.now()->format('Ymd-His').'.sql.gz';

        try {
            $this->dump($path);
        } catch (Throwable $e) {
            report($e);
            $this->error('Backup eșuat: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->prune($dir, max(1, (int) config('vitim.backup_keep_local', 7)));
        $size = (int) filesize($path);
        $this->info('Backup: '.basename($path).' ('.round($size / 1024).' KB)');

        $to = (string) config('vitim.backup_email');
        if ($this->option('no-mail') || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return self::SUCCESS;
        }
        $attach = $size <= self::MAIL_LIMIT_BYTES;
        Mail::raw(
            'Backup-ul zilnic al bazei de date VITIM AI ('.now()->format('d.m.Y H:i').").\n\n"
            .($attach ? 'Arhiva este atașată. Păstrează aceste emailuri.' : 'Arhiva are '.round($size / 1048576, 1).' MB și e prea mare pentru email: descarc-o din storage/app/backups.'),
            function ($message) use ($to, $path, $attach): void {
                $message->to($to)->subject('Backup VITIM AI – '.now()->format('d.m.Y'));
                if ($attach) {
                    $message->attach($path);
                }
            }
        );
        $this->info("Trimis la {$to}.");

        return self::SUCCESS;
    }

    private function dump(string $path): void
    {
        $gz = gzopen($path, 'wb6');
        if ($gz === false) {
            throw new \RuntimeException("Nu pot scrie {$path}");
        }
        $pdo = DB::connection()->getPdo();
        $driver = DB::connection()->getDriverName();
        gzwrite($gz, '-- VITIM AI backup '.now()->toIso8601String()."\n");

        if ($driver === 'sqlite') {
            $tables = array_column(DB::select("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"), 'sql', 'name');
        } else {
            gzwrite($gz, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
            $tables = [];
            foreach (DB::select('SHOW TABLES') as $row) {
                $name = (string) array_values((array) $row)[0];
                $tables[$name] = (string) array_values((array) DB::selectOne("SHOW CREATE TABLE `{$name}`"))[1];
            }
        }

        foreach ($tables as $table => $create) {
            gzwrite($gz, "\nDROP TABLE IF EXISTS `{$table}`;\n{$create};\n");
            $stmt = $pdo->query("SELECT * FROM `{$table}`");
            $batch = [];
            while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
                $batch[] = '('.implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row)).')';
                if (count($batch) === 200) {
                    gzwrite($gz, "INSERT INTO `{$table}` VALUES ".implode(",\n", $batch).";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                gzwrite($gz, "INSERT INTO `{$table}` VALUES ".implode(",\n", $batch).";\n");
            }
        }
        if ($driver !== 'sqlite') {
            gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        }
        gzclose($gz);
    }

    private function prune(string $dir, int $keep): void
    {
        $files = glob($dir.'/db-*.sql.gz') ?: [];
        rsort($files); // numele conțin data → ordine cronologică
        foreach (array_slice($files, $keep) as $old) {
            @unlink($old);
        }
    }
}
