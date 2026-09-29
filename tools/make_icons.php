<?php
// Generează iconițele PNG din marca VITIM (rulează: php tools/make_icons.php)
$out = __DIR__ . '/../assets/img';
@mkdir($out, 0755, true);
function mark(int $size, bool $rounded = true): GdImage {
    $s = 4; $W = $size * $s; // supersampling
    $im = imagecreatetruecolor($W, $W);
    imagesavealpha($im, true); imagealphablending($im, false);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagealphablending($im, true);
    // gradient diagonal albastru → violet → turcoaz
    $stops = [[0, [47,140,255]], [0.6, [106,92,255]], [1, [25,211,197]]];
    $r = $rounded ? (int)($W * 0.25) : 0;
    for ($y = 0; $y < $W; $y++) for ($x = 0; $x < $W; $x++) {
        // colțuri rotunjite
        if ($r) {
            $cx = $x < $r ? $r : ($x >= $W - $r ? $W - $r - 1 : $x);
            $cy = $y < $r ? $r : ($y >= $W - $r ? $W - $r - 1 : $y);
            if (($x - $cx) ** 2 + ($y - $cy) ** 2 > $r * $r) continue;
        }
        $t = ($x + $y) / (2 * $W);
        for ($i = 0; $i < 2; $i++) if ($t >= $stops[$i][0] && $t <= $stops[$i+1][0]) {
            $k = ($t - $stops[$i][0]) / ($stops[$i+1][0] - $stops[$i][0]);
            $c = array_map(fn($a, $b) => (int)($a + ($b - $a) * $k), $stops[$i][1], $stops[$i+1][1]);
            imagesetpixel($im, $x, $y, imagecolorallocate($im, ...$c));
        }
    }
    $u = $W / 64;
    $white = imagecolorallocate($im, 255, 255, 255);
    imagefilledpolygon($im, [15*$u,17*$u, 24*$u,17*$u, 32*$u,37*$u, 40*$u,17*$u, 49*$u,17*$u, 37*$u,47*$u, 27*$u,47*$u], $white);
    $dst = imagecreatetruecolor($size, $size);
    imagesavealpha($dst, true); imagealphablending($dst, false);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $im, 0, 0, 0, 0, $size, $size, $W, $W);
    return $dst;
}
foreach (['favicon-32.png' => 32, 'apple-touch-icon.png' => 180, 'icon-192.png' => 192, 'icon-512.png' => 512, 'logo.png' => 512] as $f => $sz) {
    imagepng(mark($sz, $f !== 'apple-touch-icon.png'), "$out/$f", 9);
    echo "$f\n";
}
file_put_contents("$out/favicon.svg", '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#2f8cff"/><stop offset=".6" stop-color="#6a5cff"/><stop offset="1" stop-color="#19d3c5"/></linearGradient></defs><rect width="64" height="64" rx="16" fill="url(#g)"/><path d="M15 17h9l8 20 8-20h9L37 47h-10z" fill="#fff"/></svg>');
echo "favicon.svg\n";
