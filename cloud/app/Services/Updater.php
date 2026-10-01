<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Actualizare din panou cu arhiva unei versiuni noi: verificare → backup al bazei de date → fișiere
 * copiate peste instalare → migrări (vitim:deploy). `.env`, `storage/` și fișierele de server nu se ating.
 */
final class Updater
{
    /** Căi care nu se suprascriu niciodată. */
    private const PROTECTED = ['.env', 'storage/', 'bootstrap/cache/', 'public/.htaccess', 'public/.user.ini', 'public/php.ini', '.user.ini', 'php.ini', 'public/.well-known/'];

    /** Fișiere fără de care arhiva nu e o versiune VITIM AI. */
    private const REQUIRED = ['VERSION', 'artisan', 'bootstrap/app.php', 'public/index.php', 'vendor/autoload.php'];

    private readonly string $root;

    public function __construct(?string $root = null)
    {
        $this->root = rtrim($root ?? base_path(), '/');
    }

    /** Folderul în care se pot urca arhive prin File Manager (când limita de upload a hostingului e prea mică). */
    public static function inbox(): string
    {
        return storage_path('app/updates');
    }

    public function currentVersion(): string
    {
        return trim((string) @file_get_contents($this->root.'/VERSION')) ?: '0.0.0';
    }

    /**
     * Verifică arhiva fără să schimbe nimic.
     *
     * @return array{version: string, prefix: string, files: int}
     */
    public function inspect(string $zipPath): array
    {
        $zip = $this->open($zipPath);
        try {
            $prefix = $this->prefix($zip);
            foreach (self::REQUIRED as $required) {
                if ($zip->locateName($prefix.$required) === false) {
                    throw new RuntimeException("Arhiva nu e o versiune VITIM AI (lipsește {$required}).");
                }
            }
            $version = trim((string) $zip->getFromName($prefix.'VERSION'));
            if (! preg_match('/^\d+\.\d+\.\d+$/', $version)) {
                throw new RuntimeException('Fișierul VERSION din arhivă nu e valid.');
            }
            if (version_compare($version, $this->currentVersion(), '<=')) {
                throw new RuntimeException("Arhiva conține versiunea {$version}; instalată este {$this->currentVersion()}. Se acceptă doar o versiune mai nouă.");
            }

            return ['version' => $version, 'prefix' => $prefix, 'files' => $zip->numFiles];
        } finally {
            $zip->close();
        }
    }

    /**
     * Aplică actualizarea.
     *
     * @return array{version: string, files: int, backup: string, deploy: string}
     */
    public function apply(string $zipPath): array
    {
        $info = $this->inspect($zipPath);

        // backup al bazei de date înainte de migrări (local; cel zilnic pleacă oricum pe email)
        if (Artisan::call('vitim:backup', ['--no-mail' => true]) !== 0) {
            throw new RuntimeException('Backup-ul bazei de date a eșuat; actualizarea nu a pornit.');
        }
        $backup = trim(Artisan::output());

        $tmp = storage_path('app/update-'.bin2hex(random_bytes(4)));
        $files = [];
        $zip = $this->open($zipPath);
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (! str_starts_with($name, $info['prefix'])) {
                    continue;
                }
                $relative = substr($name, strlen($info['prefix']));
                if ($relative === '' || str_ends_with($relative, '/') || $this->protected($relative)) {
                    continue;
                }
                if (str_contains($relative, '..') || str_starts_with($relative, '/') || str_contains($relative, '\\') || preg_match('#^[a-zA-Z]:#', $relative)) {
                    throw new RuntimeException("Cale nepermisă în arhivă: {$relative}");
                }
                $target = $tmp.'/'.$relative;
                File::ensureDirectoryExists(dirname($target));
                $stream = $zip->getStream($name);
                if ($stream === false || file_put_contents($target, $stream) === false) {
                    throw new RuntimeException("Nu pot extrage {$relative}");
                }
                fclose($stream);
                $files[] = $relative;
            }
        } catch (Throwable $e) {
            File::deleteDirectory($tmp);
            throw $e;
        } finally {
            $zip->close();
        }

        // VERSION la final: dacă ceva eșuează la jumătate, cron-ul nu marchează versiunea ca instalată
        usort($files, fn (string $a, string $b) => ($a === 'VERSION') <=> ($b === 'VERSION'));
        $failed = [];
        foreach ($files as $relative) {
            $target = $this->root.'/'.$relative;
            File::ensureDirectoryExists(dirname($target));
            if (! @copy($tmp.'/'.$relative, $target)) {
                $failed[] = $relative;
            }
        }
        File::deleteDirectory($tmp);
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        if ($failed) {
            throw new RuntimeException('Fișiere care nu au putut fi scrise (permisiuni): '.implode(', ', array_slice($failed, 0, 10)));
        }

        // migrări + cache; dacă eșuează aici, cron-ul reîncearcă la fiecare minut (VERSION diferă de deployed_version)
        $deploy = '';
        try {
            Artisan::call('vitim:deploy');
            $deploy = trim(Artisan::output());
        } catch (Throwable $e) {
            report($e);
            $deploy = 'Migrările vor fi rulate de cron în cel mult un minut.';
        }

        return ['version' => $info['version'], 'files' => count($files), 'backup' => $backup, 'deploy' => $deploy];
    }

    private function open(string $zipPath): ZipArchive
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Extensia PHP zip lipsește (cPanel → Select PHP Version → Extensions).');
        }
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Arhiva nu poate fi deschisă. Verifică să fie un .zip complet.');
        }

        return $zip;
    }

    /** Arhiva poate avea fișierele în rădăcină sau într-un singur folder (ex. vitim-ai-0.4.0/). */
    private function prefix(ZipArchive $zip): string
    {
        if ($zip->locateName('VERSION') !== false) {
            return '';
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (preg_match('#^([^/]+/)VERSION$#', (string) $zip->getNameIndex($i), $m)) {
                return $m[1];
            }
        }

        return '';
    }

    private function protected(string $relative): bool
    {
        foreach (self::PROTECTED as $path) {
            if ($relative === $path || (str_ends_with($path, '/') && str_starts_with($relative, $path))) {
                return true;
            }
        }

        return false;
    }
}
