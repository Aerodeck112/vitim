<?php
use App\Core\View;

echo View::partial('site/partials/page_hero', [
    'crumbs' => [['Zone', '/zone']],
    'eyebrow' => icon('map') . ' Remote în toată țara · on-site în 3 județe',
    'title' => 'Servicii IT la sediu în Mureș, Bistrița-Năsăud și Alba',
    'lead' => 'Venim la tine pentru instalări, reparații, rețelistică și mentenanță. Restul – suport, marketing, SEO, automatizări și AI – îl livrăm remote, oriunde ai fi.',
    'actions' => '<a class="btn btn-primary btn-lg" href="' . e(url('/contact')) . '">Programează o intervenție ' . icon('arrow-right', 'ico ico-move') . '</a>',
]);
?>
<section class="section">
  <div class="container"><?= View::partial('site/partials/zones') ?></div>
</section>
<section class="section" style="padding-top:0">
  <div class="container"><?= View::partial('site/partials/cta_form', ['title' => 'Ai nevoie de noi la sediu?', 'text' => 'Spune-ne localitatea și problema. Îți confirmăm rapid când putem ajunge.']) ?></div>
</section>
