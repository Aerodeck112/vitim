<?php use App\Core\Orders; use App\Core\Shop; use App\Core\View; ?>
<section class="page-hero"><div class="container narrow"><?= View::partial('site/partials/crumbs', ['seo' => $seo]) ?><h1>Urmărire comandă</h1><p>Introdu numărul comenzii (îl găsești în emailul de confirmare) și adresa de email folosită la comandă.</p></div></section>
<div class="container narrow" style="padding:10px 16px 90px">
  <?php if ($lastOrder): ?>
  <div class="alert alert-info">Ultima ta comandă de pe acest dispozitiv: <a href="<?= e(url('/comanda/' . $lastOrder['token'])) ?>"><strong><?= e($lastOrder['number']) ?></strong></a> · <?= e(ro_date($lastOrder['created_at'])) ?> · <?= e(Shop::money($lastOrder['total'])) ?> · <?= e(Orders::statusLabel($lastOrder['status'])) ?></div>
  <?php endif; ?>
  <?php if ($error): ?><div class="alert alert-err"><?= e($error) ?></div><?php endif; ?>
  <form class="card" method="post" action="<?= e(url('/urmarire-comanda')) ?>" style="display:grid;gap:16px">
    <div class="row">
      <div class="field"><label for="t-n">Număr comandă</label><input id="t-n" name="number" placeholder="<?= e(setting('order_prefix', 'BDM')) ?>1001" required value="<?= e(str_input('number')) ?>"></div>
      <div class="field"><label for="t-e">Email</label><input id="t-e" name="email" type="email" required autocomplete="email" value="<?= e(str_input('email')) ?>"></div>
    </div>
    <div><button class="btn" type="submit"><?= icon('search') ?> Caută comanda</button></div>
  </form>
</div>
