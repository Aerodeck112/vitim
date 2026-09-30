<?php use App\Core\View; ?>
<section class="section center"><div class="container narrow">
  <p class="script" style="font-size:72px;color:var(--caramel);margin:0"><?= $gone ? '410' : '404' ?></p>
  <h1 style="font-size:40px"><?= $gone ? 'Pagina nu mai există' : 'Pagina nu a fost găsită' ?></h1>
  <p class="muted" style="font-size:18px">Poate ai ajuns aici dintr-un link vechi. Cafeaua noastră e însă tot aici.</p>
  <form action="<?= e(url('/produse')) ?>" method="get" style="display:flex;gap:10px;max-width:520px;margin:26px auto" role="search"><label class="sr" for="q404">Caută</label><input class="input" id="q404" name="q" placeholder="Caută un produs…" style="flex:1"><button class="btn" type="submit"><?= icon('search') ?></button></form>
  <p><a class="btn btn-dark" href="<?= e(url('/')) ?>">Prima pagină</a> <a class="btn btn-ghost" href="<?= e(url('/produse')) ?>">Toate produsele</a></p>
</div></section>
<?php if ($products): ?><section class="section bg-paper"><div class="container"><h2 class="center" style="font-size:30px">Produse recomandate</h2><div class="grid-products" style="margin-top:30px"><?php foreach ($products as $p): ?><?= View::partial('site/partials/product_card', ['p' => $p]) ?><?php endforeach; ?></div></div></section><?php endif; ?>
