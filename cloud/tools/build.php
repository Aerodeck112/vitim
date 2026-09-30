<?php

declare(strict_types=1);

/**
 * Arhiva pentru cPanel: ../dist/vitim-ai-<versiune>.zip
 *
 *   php tools/build.php
 *
 * Conține codul + vendor fără pachetele de dezvoltare. Nu conține .env, date din storage sau teste.
 * Aceeași arhivă se folosește la instalare și la actualizare (se extrage peste versiunea veche;
 * cron-ul rulează apoi singur migrările: vitim:deploy).
 */
$root = dirname(__DIR__);
$version = trim((string) file_get_contents($root.'/VERSION'));
$out = dirname($root).'/dist/vitim-ai-'.$version.'.zip';
$tmp = sys_get_temp_dir().'/vitim-ai-build-'.bin2hex(random_bytes(4));

$exclude = [
    '#^\.env$#', '#^\.env\.(?!example$|cpanel\.example$)#', '#^vendor/#', '#^node_modules/#', '#^tests/#', '#^tools/#',
    '#^database/database\.sqlite$#', '#^\.phpunit#', '#^phpunit\.xml$#', '#^\.git#',
    // storage: doar structura (fișierele .gitignore păstrează directoarele)
    '#^storage/(?!.*\.gitignore$)#', '#^bootstrap/cache/(?!\.gitignore$)#', '#^public/storage$#',
];
$skip = function (string $rel) use ($exclude): bool {
    foreach ($exclude as $re) {
        if (preg_match($re, $rel)) {
            return true;
        }
    }

    return false;
};

// 1. copie curată
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if ($file->isDir() || $skip($rel)) {
        continue;
    }
    @mkdir(dirname($tmp.'/'.$rel), 0755, true);
    copy($file->getPathname(), $tmp.'/'.$rel);
}

// 2. dependențe de producție
passthru('cd '.escapeshellarg($tmp).' && composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts 2>&1', $code);
if ($code !== 0 || ! is_file($tmp.'/vendor/autoload.php')) {
    fwrite(STDERR, "composer install a eșuat\n");
    exit(1);
}

// 3. arhivă
@mkdir(dirname($out), 0755, true);
@unlink($out);
$zip = new ZipArchive;
$zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$count = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    // pachetele instalate din sursă aduc și istoricul git: nu are ce căuta pe server
    if ($file->isFile() && ! preg_match('#/\.git(hub)?/#', str_replace('\\', '/', $file->getPathname()))) {
        $zip->addFile($file->getPathname(), substr($file->getPathname(), strlen($tmp) + 1));
        $count++;
    }
}
$zip->close();
exec('rm -rf '.escapeshellarg($tmp));
printf("✓ %s (%d fișiere, %.1f MB)\n", $out, $count, filesize($out) / 1048576);
