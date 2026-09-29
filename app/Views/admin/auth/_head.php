<!doctype html>
<html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title><?= e($t ?? 'Autentificare') ?> · <?= e(setting('brand_name')) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>"><link rel="icon" href="<?= e(url('/assets/img/favicon.svg')) ?>" type="image/svg+xml"></head>
<body><div class="auth"><div class="box">
<div class="brand"><?= \App\Core\Site::markSvg('mk') ?><span><?= e(setting('brand_name')) ?> <span style="font-weight:400;color:#8f9ab0">· panou</span></span></div>
<?php foreach (flashes() as $f): ?><div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>
