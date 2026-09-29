<section class="section thanks">
  <div class="container">
    <div class="err-code grad"><?= $gone ? '410' : '404' ?></div>
    <h1 style="font-size:clamp(1.8rem,4vw,2.6rem)"><?= $gone ? 'Această pagină nu mai există' : 'Pagina nu a fost găsită' ?></h1>
    <p class="muted" style="max-width:560px;margin:0 auto 28px">Poate a fost mutată sau adresa conține o greșeală. Uite câteva locuri bune de unde poți continua:</p>
    <div class="hero-ctas" style="justify-content:center">
      <a class="btn btn-primary" href="<?= e(url('/')) ?>">Prima pagină</a>
      <a class="btn btn-ghost" href="<?= e(url('/servicii')) ?>">Servicii</a>
      <a class="btn btn-ghost" href="<?= e(url('/contact')) ?>">Contact</a>
    </div>
  </div>
</section>
