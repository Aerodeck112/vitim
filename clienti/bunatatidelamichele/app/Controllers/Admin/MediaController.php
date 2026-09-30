<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\DB;
use App\Core\Sanitizer;
use App\Core\Uploader;

final class MediaController extends AdminController
{
    protected string $area = 'media';

    public function index(): string
    {
        $items = DB::all('SELECT * FROM media ORDER BY id DESC LIMIT 500');
        $size = (int)DB::val('SELECT COALESCE(SUM(size),0) FROM media');
        return $this->render('media', ['items' => $items, 'size' => $size, 'title' => 'Media']);
    }

    public function upload(): never
    {
        $files = $_FILES['files'] ?? null;
        if (!$files || !is_array($files['name'])) {
            json_out(['ok' => false, 'message' => 'Niciun fișier primit. Verifică limita de upload a serverului (upload_max_filesize).']);
        }
        $ok = 0;
        $errs = [];
        foreach ($files['name'] as $i => $name) {
            [$m, $err] = Uploader::image([
                'name' => $name,
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ], pathinfo((string)$name, PATHINFO_FILENAME));
            $m ? $ok++ : $errs[] = $name . ': ' . $err;
        }
        json_out(['ok' => $ok > 0, 'message' => $ok . ' imagine(i) încărcate și optimizate (WebP).' . ($errs ? ' Erori: ' . implode('; ', $errs) : '')]);
    }

    public function update(string $id): never
    {
        DB::update('media', ['alt' => Sanitizer::text(str_input('alt'), 250)], 'id = :id', ['id' => (int)$id]);
        $this->contentChanged();
        flash('ok', 'Textul alternativ a fost salvat.');
        redirect('/admin/media');
    }

    public function delete(string $id): never
    {
        $m = DB::row('SELECT * FROM media WHERE id = ?', [(int)$id]);
        if ($m) {
            Uploader::delete($m);
            flash('ok', 'Imaginea a fost ștearsă.');
        }
        redirect('/admin/media');
    }

    public function json(): never
    {
        $out = [];
        foreach (DB::all('SELECT * FROM media ORDER BY id DESC LIMIT 300') as $m) {
            $v = json_list($m['variants']);
            $out[] = [
                'id' => (int)$m['id'],
                'path' => $m['path'],
                'url' => upload_url($m['path']),
                'thumb' => upload_url($v[480] ?? $v['480'] ?? $m['path']),
                'alt' => $m['alt'],
                'name' => $m['original_name'],
                'width' => (int)$m['width'],
                'height' => (int)$m['height'],
            ];
        }
        json_out($out);
    }
}
