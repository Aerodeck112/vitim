<?php
/**
 * Mockup VITIM AI: tabloul de bord de dimineață (date demonstrative, marcate ca atare).
 * $compact = variantă mai scurtă (prima pagină).
 */
$compact ??= false;
$kpis = [
    ['17', 'lead-uri noi', 'up'],
    ['4', 'necesită atenție', 'warn'],
    ['8', 'oferte trimise', ''],
    ['3', 'clienți așteaptă răspuns', 'warn'],
    ['12.450 RON', 'valoare oportunități', ''],
    ['24', 'conversații rezolvate automat', 'up'],
    ['6h 20m', 'timp economisit', 'up'],
];
$attention = [
    ['file', '2 lead-uri așteaptă ofertă', 'Vânzări'],
    ['clock', '3 clienți nu au răspuns de 4 zile', 'Follow-up'],
    ['alert', '1 ofertă expiră mâine', 'Ofertă'],
    ['calendar', '4 programări necesită confirmare', 'Programări'],
    ['message', '1 conversație are ton negativ', 'Escaladare'],
];
?>
<div class="ai-dash<?= $compact ? ' compact' : '' ?>" role="img" aria-label="Exemplu de tablou de bord VITIM AI, cu date demonstrative">
  <div class="ad-top">
    <span class="ad-brand"><?= \App\Core\Site::wordmarkSvg('ad-mark') ?><b>AI</b></span>
    <span class="ad-demo">Date demonstrative</span>
  </div>
  <div class="ad-body">
    <div class="ad-hello"><h3>Bună dimineața.</h3><p>Astăzi, în firma ta:</p></div>
    <ul class="ad-kpis">
      <?php foreach (array_slice($kpis, 0, $compact ? 4 : 7) as [$v, $l, $t]): ?>
      <li class="<?= $t ?>"><b><?= e($v) ?></b><span><?= e($l) ?></span></li>
      <?php endforeach; ?>
    </ul>
    <div class="ad-attn">
      <div class="ad-attn-head"><strong>Ce necesită atenția ta?</strong><span class="ad-count"><?= $compact ? 3 : 5 ?></span></div>
      <ul>
        <?php foreach (array_slice($attention, 0, $compact ? 3 : 5) as [$ic, $t, $tag]): ?>
        <li><?= icon($ic) ?><span><?= e($t) ?></span><small><?= e($tag) ?></small></li>
        <?php endforeach; ?>
      </ul>
      <span class="ad-btn"><?= icon('sparkles') ?> Rezolvă cu AI</span>
    </div>
  </div>
</div>
