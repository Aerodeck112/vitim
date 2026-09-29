<section class="section thanks">
  <div class="container" style="max-width:640px">
    <div class="icon-tile"><?= icon($icon ?? 'check-circle') ?></div>
    <h1 style="font-size:clamp(1.9rem,4vw,2.8rem)"><?= e($title) ?></h1>
    <p class="muted" style="font-size:1.1rem"><?= e($text) ?></p>
    <div class="hero-ctas" style="justify-content:center;margin-top:28px">
      <a class="btn btn-primary" href="<?= e(url('/')) ?>">Înapoi la site</a>
      <a class="btn btn-ghost" href="<?= e(url('/blog')) ?>">Citește blogul</a>
    </div>
  </div>
</section>
