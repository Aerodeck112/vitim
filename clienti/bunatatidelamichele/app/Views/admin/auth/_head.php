<!doctype html>
<html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title><?= e($t ?? 'Autentificare') ?> · <?= e(setting('brand_name')) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>"><link rel="icon" href="<?= e(url('/assets/img/favicon-32.png')) ?>" type="image/png"></head>
<body><div class="auth"><div class="box">
<div class="brand" style="flex-direction:column;text-align:center"><img src="<?= e(url('/assets/img/logo-light.png')) ?>" alt="" style="height:90px;width:auto"><span><?= e(setting('brand_name')) ?> <span style="font-weight:400;color:#b8a594">· panou</span></span></div>
<?php foreach (flashes() as $f): ?><div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>
