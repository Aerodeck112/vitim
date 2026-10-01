<?php
declare(strict_types=1);

/**
 * Construiește arhiva de instalare / actualizare: dist/vitim-<versiune>.zip
 *
 *   php tools/build.php
 *
 * Aceeași arhivă se folosește și pentru prima instalare (se urcă în public_html),
 * și pentru actualizări (Panou → Sistem → Actualizare din arhivă .zip).
 */

$root = dirname(__DIR__);
$version = trim((string)file_get_contents($root . '/VERSION'));
if ($version === '') {
    fwrite(STDERR, "Lipsește VERSION\n");
    exit(1);
}
@mkdir($root . '/dist', 0755, true);
$out = $root . '/dist/vitim-' . $version . '.zip';
@unlink($out);

$exclude = [
    '#(^|/)\.git(/|$)#', '#(^|/)\.github/#', '#^dist/#', '#^tests/#', '#^node_modules/#', '#^\.claude/#', '#^\.gitignore$#',
    '#^app/config\.php$#', '#^storage/(?!\.htaccess$|cache/\.gitkeep$|logs/\.gitkeep$|backups/\.gitkeep$|tmp/\.gitkeep$)#',
    '#^uploads/(?!\.htaccess$|\.gitkeep$)#', '#^(cloud|docs|clienti)/#', '#^tools/dev-router\.php$#', '#^composer\.(json|lock)$#', '#\.DS_Store$#',
];

$zip = new ZipArchive();
if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Nu pot crea $out\n");
    exit(1);
}
$count = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile()) {
        continue;
    }
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
    foreach ($exclude as $re) {
        if (preg_match($re, $rel)) {
            continue 2;
        }
    }
    $zip->addFile($f->getPathname(), $rel);
    $count++;
}
$zip->close();
printf("✓ %s  (%d fișiere, %.1f MB)\n", $out, $count, filesize($out) / 1048576);
