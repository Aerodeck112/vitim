<?php
use App\Core\Settings;

$brand = e(Settings::get('brand_name', 'Bunătăți de la Michele'));
$logo = (string)Settings::get('logo');
$addr = trim(implode(', ', array_filter([(string)Settings::get('company_name'), (string)Settings::get('company_address'), (string)Settings::get('company_city')])), ', ');
?><!doctype html>
<html lang="ro" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title><?= $brand ?></title>
<style>
body{margin:0;padding:0;background:#f4ede4;-webkit-text-size-adjust:100%}
a{color:#b5602c}
.content p{margin:0 0 16px}
.content h2{font-size:22px;line-height:1.3;margin:0 0 14px;color:#2b1a12}
.content h3{font-size:18px;margin:22px 0 10px;color:#2b1a12}
.content ul,.content ol{padding-left:22px;margin:0 0 16px}
.content li{margin:0 0 6px}
.content img{max-width:100%;height:auto;border-radius:12px}
@media (max-width:620px){.wrap{width:100%!important}.pad{padding:24px 20px!important}}
</style>
</head>
<body style="margin:0;padding:0;background:#f4ede4">
<?php if (!empty($preheader)): ?><div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent"><?= e($preheader) ?>&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;</div><?php endif; ?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4ede4">
<tr><td align="center" style="padding:28px 12px">
  <table role="presentation" class="wrap" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px">
    <tr><td style="padding:0 8px 18px;text-align:center">
      <img src="<?= e($logo ? abs_url(upload_url($logo)) : abs_url('/assets/img/logo.png')) ?>" alt="<?= $brand ?>" height="64" style="height:64px;width:auto;display:block;margin:0 auto">
    </td></tr>
    <tr><td class="pad content" style="background:#ffffff;border-radius:20px;padding:36px 40px;font:16px/1.65 -apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#3d2a1f">
      <?= $body ?>
    </td></tr>
    <tr><td style="padding:22px 12px 8px;font:13px/1.6 Arial,sans-serif;color:#8a7663;text-align:center">
      <strong style="color:#3d2a1f"><?= $brand ?></strong> · <a href="<?= e(abs_url('/')) ?>" style="color:#8a7663"><?= e(parse_url(abs_url('/'), PHP_URL_HOST)) ?></a> <?= Settings::get('phone') ? '· ' . e(Settings::get('phone')) : '' ?> · <a href="mailto:<?= e(Settings::get('email')) ?>" style="color:#8a7663"><?= e(Settings::get('email')) ?></a><br>
      <?= e($addr) ?><?php if (Settings::get('company_cui')): ?> · CUI <?= e(Settings::get('company_cui')) ?><?php endif; ?>
      <?php if (!empty($reason)): ?><br><span style="font-size:12px"><?= e($reason) ?></span><?php endif; ?>
      <?php if (!empty($unsubscribe)): ?><br><a href="<?= e($unsubscribe) ?>" style="color:#8a7663;text-decoration:underline">Dezabonare</a><?php endif; ?>
    </td></tr>
  </table>
</td></tr>
</table>
<?php if (!empty($pixel)): ?><img src="<?= e($pixel) ?>" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0"><?php endif; ?>
</body>
</html>
