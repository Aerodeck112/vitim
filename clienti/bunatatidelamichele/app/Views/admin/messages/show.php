<?php use App\Core\Csrf; use App\Core\Orders; use App\Core\Shop; ?>
<div class="page-head"><div><h1><?= e($m['subject'] ?: 'Mesaj de la ' . $m['name']) ?></h1><p><a href="<?= e(url('/admin/mesaje')) ?>">← Mesaje</a> · <?= e(local_time($m['created_at'])) ?></p></div>
<div class="actions"><a class="btn btn-p" href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('Re: ' . ($m['subject'] ?: 'Mesajul tău')) ?>"><?= icon('mail') ?> Răspunde pe email</a>
<form method="post" action="<?= e(url('/admin/mesaje/' . $m['id'] . '/sterge')) ?>" data-confirm="Ștergi mesajul?"><?= Csrf::field() ?><button class="btn btn-d"><?= icon('trash') ?></button></form></div></div>
<div class="split">
  <div class="card"><div style="white-space:pre-wrap;font-size:15.5px;line-height:1.7"><?= e($m['message']) ?></div></div>
  <div class="card"><dl class="kv2"><dt>Nume</dt><dd><?= e($m['name']) ?></dd><dt>Email</dt><dd><a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a></dd><?php if ($m['phone']): ?><dt>Telefon</dt><dd><a href="<?= e(phone_href($m['phone'])) ?>"><?= e($m['phone']) ?></a></dd><?php endif; ?><dt>Pagina</dt><dd class="small"><?= e($m['page']) ?></dd><dt>IP</dt><dd class="small"><?= e($m['ip']) ?></dd><?php if ($m['spam_score'] >= 6): ?><dt>Spam</dt><dd><span class="badge b-err">scor <?= (int)$m['spam_score'] ?></span></dd><?php endif; ?></dl>
  <?php if ($orders): ?><h3 style="margin-top:16px">Comenzile clientului</h3><?php foreach ($orders as $o): ?><div class="small"><a href="<?= e(url('/admin/comenzi/' . $o['id'])) ?>"><?= e($o['number']) ?></a> · <?= e(Shop::money($o['total'])) ?> · <?= e(Orders::statusLabel($o['status'])) ?></div><?php endforeach; ?><?php endif; ?></div>
</div>
