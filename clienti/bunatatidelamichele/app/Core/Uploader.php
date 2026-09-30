<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Încărcare imagini: validare, re-encodare (elimină cod ascuns), conversie WebP și variante responsive.
 */
final class Uploader
{
    public const WIDTHS = [480, 960, 1600];
    private const MAX_BYTES = 15 * 1024 * 1024;

    /** @return array{0: ?array, 1: string} */
    public static function image(array $file, string $alt = ''): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return [null, 'Fișierul nu a putut fi încărcat (cod ' . ($file['error'] ?? '?') . ').'];
        }
        if ($file['size'] > self::MAX_BYTES) {
            return [null, 'Fișierul depășește 15 MB.'];
        }
        $tmp = $file['tmp_name'];
        $info = @getimagesize($tmp);
        $mime = $info['mime'] ?? '';
        $origName = (string)($file['name'] ?? 'imagine');
        if ($mime === '' && str_ends_with(strtolower($origName), '.svg')) {
            return [null, 'SVG nu este acceptat din motive de securitate. Folosește PNG, JPG sau WebP.'];
        }
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return [null, 'Tip de fișier neacceptat. Folosește JPG, PNG, WebP sau GIF.'];
        }
        return self::process($tmp, $mime, $origName, $alt);
    }

    /** Importă o imagine de pe disc (conținutul inițial al magazinului). */
    public static function fromFile(string $file, string $alt = '', ?string $name = null): ?array
    {
        $info = @getimagesize($file);
        $mime = $info['mime'] ?? '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return null;
        }
        [$m] = self::process($file, $mime, $name ?? basename($file), $alt);
        return $m;
    }

    /** @return array{0: ?array, 1: string} */
    private static function process(string $tmp, string $mime, string $origName, string $alt): array
    {
        $sub = date('Y/m');
        $dir = UPLOADS_PATH . '/' . $sub;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return [null, 'Nu pot crea directorul uploads/. Verifică permisiunile (755).'];
        }
        $base = slugify(pathinfo($origName, PATHINFO_FILENAME));
        $base = mb_substr($base, 0, 60);
        $name = $base;
        $i = 1;
        while (glob($dir . '/' . $name . '.*')) {
            $name = $base . '-' . ++$i;
        }

        $canWebp = function_exists('imagewebp');
        $src = self::load($tmp, $mime);
        if (!$src) {
            return [null, 'Imaginea nu a putut fi procesată.'];
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $ext = $canWebp ? 'webp' : ($mime === 'image/png' ? 'png' : 'jpg');

        // original, max 2400px
        $mainW = min($w, 2400);
        $main = $name . '.' . $ext;
        self::save(self::resize($src, $mainW), $dir . '/' . $main, $ext);
        $variants = [];
        foreach (self::WIDTHS as $vw) {
            if ($vw < $w) {
                $vn = $name . '-' . $vw . '.' . $ext;
                self::save(self::resize($src, $vw), $dir . '/' . $vn, $ext);
                $variants[$vw] = $sub . '/' . $vn;
            }
        }
        imagedestroy($src);
        $path = $sub . '/' . $main;
        $mainH = (int)round($h * $mainW / max(1, $w));
        $id = DB::insert('media', [
            'path' => $path,
            'original_name' => mb_substr($origName, 0, 250),
            'mime' => 'image/' . $ext,
            'width' => $mainW,
            'height' => $mainH,
            'size' => (int)@filesize($dir . '/' . $main),
            'alt' => mb_substr($alt, 0, 250),
            'variants' => json_encode($variants),
            'created_at' => DB::now(),
        ]);
        return [DB::row('SELECT * FROM media WHERE id = ?', [$id]), ''];
    }

    private static function load(string $file, string $mime): ?\GdImage
    {
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file),
            'image/png' => @imagecreatefrompng($file),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false,
            'image/gif' => @imagecreatefromgif($file),
            default => false,
        };
        if (!$img) {
            return null;
        }
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($file);
            $o = (int)($exif['Orientation'] ?? 1);
            $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
            if ($rot) {
                $r = imagerotate($img, $rot, 0);
                if ($r) {
                    imagedestroy($img);
                    $img = $r;
                }
            }
        }
        if (!imageistruecolor($img)) {
            imagepalettetotruecolor($img);
        }
        imagealphablending($img, true);
        imagesavealpha($img, true);
        return $img;
    }

    private static function resize(\GdImage $src, int $width): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        if ($width >= $w) {
            $width = $w;
        }
        $height = (int)max(1, round($h * $width / $w));
        $dst = imagecreatetruecolor($width, $height);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $width, $height, $transparent);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $height, $w, $h);
        return $dst;
    }

    private static function save(\GdImage $img, string $path, string $ext): void
    {
        match ($ext) {
            'webp' => imagewebp($img, $path, 80),
            'png' => imagepng($img, $path, 8),
            default => imagejpeg($img, $path, 82),
        };
        imagedestroy($img);
    }

    public static function delete(array $media): void
    {
        @unlink(UPLOADS_PATH . '/' . $media['path']);
        foreach (json_list($media['variants']) as $v) {
            @unlink(UPLOADS_PATH . '/' . $v);
        }
        DB::delete('media', 'id = ?', [$media['id']]);
    }

    /** srcset pentru o cale din uploads (dacă există variante). */
    public static function srcset(?string $path): string
    {
        if (!$path) {
            return '';
        }
        static $cache = [];
        if (!isset($cache[$path])) {
            $m = DB::row('SELECT width, variants FROM media WHERE path = ?', [$path]);
            $cache[$path] = $m;
        }
        $m = $cache[$path];
        if (!$m) {
            return '';
        }
        $parts = [];
        foreach (json_list($m['variants']) as $w => $p) {
            $parts[] = upload_url($p) . ' ' . $w . 'w';
        }
        $parts[] = upload_url($path) . ' ' . $m['width'] . 'w';
        return implode(', ', $parts);
    }
}
