<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Backup;
use App\Core\DB;
use App\Core\Migrator;
use App\Core\Settings;
use App\Core\Updater;

final class SystemController extends AdminController
{
    protected string $area = 'settings';

    public function index(): string
    {
        $dirSize = function (string $dir): int {
            $s = 0;
            if (is_dir($dir)) {
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
                    $s += $f->isFile() ? $f->getSize() : 0;
                }
            }
            return $s;
        };
        $info = [
            'Versiune VITIM' => APP_VERSION,
            'PHP' => PHP_VERSION,
            'Bază de date' => DB::driver() === 'mysql' ? 'MySQL/MariaDB ' . DB::val('SELECT VERSION()') : 'SQLite ' . DB::val('SELECT sqlite_version()'),
            'Server' => (string)($_SERVER['SERVER_SOFTWARE'] ?? '—'),
            'Limită upload' => ini_get('upload_max_filesize') . ' / post ' . ini_get('post_max_size'),
            'Memorie PHP' => ini_get('memory_limit'),
            'WebP' => function_exists('imagewebp') ? 'da' : 'nu (imaginile rămân JPG/PNG)',
            'ZipArchive' => class_exists('ZipArchive') ? 'da' : 'NU – actualizările din panou nu vor funcționa',
            'Uploads' => round($dirSize(UPLOADS_PATH) / 1048576, 1) . ' MB',
            'Backup-uri' => round($dirSize(STORAGE_PATH . '/backups') / 1048576, 1) . ' MB',
            'Instalat la' => local_time((string)Settings::get('installed_at')),
        ];
        return $this->render('system', [
            'info' => $info,
            'backups' => Backup::list(),
            'pending' => Migrator::pending(),
            'cronUrl' => abs_url('/cron') . '?key=' . Settings::get('cron_key'),
            'cronLast' => (string)Settings::get('cron_last_run'),
            'changelog' => is_file(ROOT_PATH . '/CHANGELOG.md') ? (string)file_get_contents(ROOT_PATH . '/CHANGELOG.md') : '',
            'title' => 'Sistem & actualizări',
        ]);
    }

    public function update(): never
    {
        @set_time_limit(300);
        $f = $_FILES['package'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            $code = $f['error'] ?? -1;
            flash('err', 'Arhiva nu a fost primită' . ($code === UPLOAD_ERR_INI_SIZE ? ' – depășește limita de upload a serverului (' . ini_get('upload_max_filesize') . '). Mărește limita din cPanel → MultiPHP INI Editor.' : '.'));
            redirect('/admin/sistem');
        }
        if (strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION)) !== 'zip') {
            flash('err', 'Încarcă o arhivă .zip.');
            redirect('/admin/sistem');
        }
        [$ok, $msg] = Updater::apply($f['tmp_name'], !empty($_POST['downgrade']));
        flash($ok ? 'ok' : 'err', $msg);
        redirect('/admin/sistem');
    }

    public function backup(): never
    {
        @set_time_limit(300);
        $what = str_input('what', 'db');
        $made = [];
        if (in_array($what, ['db', 'all'], true) && ($n = Backup::database('manual'))) {
            $made[] = $n;
        }
        if (in_array($what, ['uploads', 'all'], true) && ($n = Backup::uploads())) {
            $made[] = $n;
        }
        if (in_array($what, ['code', 'all'], true) && ($n = Backup::code('manual'))) {
            $made[] = $n;
        }
        flash($made ? 'ok' : 'err', $made ? 'Backup creat: ' . implode(', ', $made) . '. Descarcă-l și păstrează-l și în afara serverului.' : 'Backup-ul nu a putut fi creat.');
        redirect('/admin/sistem');
    }

    public function download(string $file): never
    {
        $p = Backup::path($file);
        if (!$p) {
            http_response_code(404);
            exit('Fișier inexistent.');
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($p) . '"');
        header('Content-Length: ' . filesize($p));
        readfile($p);
        exit;
    }

    public function deleteBackup(string $file): never
    {
        if ($p = Backup::path($file)) {
            @unlink($p);
            flash('ok', 'Backup șters.');
        }
        redirect('/admin/sistem');
    }

    public function restoreCode(string $file): never
    {
        $p = Backup::path($file);
        if (!$p || !str_starts_with(basename($p), 'cod-')) {
            flash('err', 'Se pot restaura doar backup-uri de cod.');
            redirect('/admin/sistem');
        }
        [$ok, $msg] = Updater::apply($p, true);
        flash($ok ? 'ok' : 'err', $ok ? 'Codul a fost restaurat din ' . basename($p) . '.' : $msg);
        redirect('/admin/sistem');
    }

    public function logs(): string
    {
        $read = function (string $f): string {
            if (!is_file($f)) {
                return '';
            }
            $size = filesize($f);
            $fh = fopen($f, 'r');
            if ($size > 60000) {
                fseek($fh, -60000, SEEK_END);
            }
            $s = (string)stream_get_contents($fh);
            fclose($fh);
            return $s;
        };
        return $this->render('logs', [
            'app' => $read(STORAGE_PATH . '/logs/app.log'),
            'php' => $read(STORAGE_PATH . '/logs/php-error.log'),
            'title' => 'Jurnal erori',
        ]);
    }
}
