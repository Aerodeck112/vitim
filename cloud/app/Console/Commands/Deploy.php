<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * Rulează la fiecare minut din cron (cPanel nu are terminal garantat):
 * - la prima rulare generează APP_KEY dacă lipsește;
 * - după urcarea unei versiuni noi (fișierul VERSION diferă) rulează migrările și golește cache-urile.
 * Când nu e nimic de făcut, doar compară două fișiere.
 */
#[Signature('vitim:deploy {--force : Rulează migrările chiar dacă versiunea nu s-a schimbat}')]
#[Description('Finalizează instalarea sau actualizarea (cheie, migrări, cache)')]
final class Deploy extends Command
{
    public function handle(): int
    {
        if ((string) config('app.key') === '') {
            if (! File::exists(base_path('.env'))) {
                $this->error('Lipsește fișierul .env (vezi INSTALL-CPANEL.md).');

                return self::FAILURE;
            }
            Artisan::call('key:generate', ['--force' => true]);
            $this->info('APP_KEY generat.');
        }

        $version = trim((string) @file_get_contents(base_path('VERSION'))) ?: '0.0.0';
        $marker = storage_path('app/deployed_version');
        $deployed = trim((string) @file_get_contents($marker));
        if ($deployed === $version && ! $this->option('force')) {
            return self::SUCCESS;
        }

        $this->call('migrate', ['--force' => true]);
        $this->call('optimize:clear');
        File::put($marker, $version);
        $this->info("Versiunea {$version} este activă.");

        return self::SUCCESS;
    }
}
