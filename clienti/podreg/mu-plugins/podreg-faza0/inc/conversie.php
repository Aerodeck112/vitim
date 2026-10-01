<?php
/**
 * Conversie: bară sticky (mobil), buton „Cere ofertă” (desktop), bloc CTA la
 * finalul fiecărui proiect, precompletarea formularelor și măsurare
 * (Google Tag Manager + Consent Mode v2, doar dacă e setat gtm_id).
 */

defined('ABSPATH') || exit;

/**
 * Unde trimitem cererea de ofertă pentru proiectul curent: cabanele merg în
 * configurator, scările și terasele în formularul de contact.
 */
function podreg_link_oferta(?array $proiect): string
{
    $configurator = in_array($proiect['categorie'] ?? 'cabane-case-lemn', ['cabane-case-lemn', 'casute-de-lemn'], true);
    $url = podreg_url($configurator ? podreg_cfg('url_oferta') : podreg_cfg('url_contact'));
    if ($proiect) {
        $url = add_query_arg('model', rawurlencode($proiect['titlu']), $url);
    }
    return $url;
}

function podreg_link_whatsapp(?array $proiect): string
{
    $mesaj = $proiect
        ? sprintf('Bună ziua, mă interesează o construcție similară cu „%s”.', wp_strip_all_tags($proiect['titlu']))
        : 'Bună ziua, aș dori informații despre o construcție din lemn.';
    return 'https://wa.me/' . preg_replace('/\D/', '', (string) podreg_cfg('whatsapp')) . '?text=' . rawurlencode($mesaj);
}

add_action('wp_enqueue_scripts', function () {
    if (!podreg_e_frontend()) {
        return;
    }
    wp_enqueue_style('podreg-f0', PODREG_F0_URL . '/assets/podreg.css', [], PODREG_F0_VERSION);
    wp_enqueue_script('podreg-f0', PODREG_F0_URL . '/assets/podreg.js', [], PODREG_F0_VERSION, ['in_footer' => true, 'strategy' => 'defer']);
});

// ---------- Bara sticky + butonul de desktop ----------
add_action('wp_footer', function () {
    if (!podreg_e_frontend() || is_404()) {
        return;
    }
    $en = podreg_e_engleza();
    $proiect = podreg_proiect_curent();
    $tel = (string) podreg_cfg('telefon');
    $txt = $en
        ? ['call' => 'Call', 'wa' => 'WhatsApp', 'quote' => 'Get a quote']
        : ['call' => 'Sună', 'wa' => 'WhatsApp', 'quote' => 'Cere ofertă'];
    ?>
<nav class="podreg-cta-bar" aria-label="<?php echo esc_attr($en ? 'Quick contact' : 'Contact rapid'); ?>">
    <a class="podreg-cta-bar__btn" href="tel:<?php echo esc_attr($tel); ?>" data-podreg-ev="click_tel">
        <svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1l-2.3 2.2z"/></svg>
        <span><?php echo esc_html($txt['call']); ?></span>
    </a>
    <a class="podreg-cta-bar__btn podreg-cta-bar__btn--wa" href="<?php echo esc_url(podreg_link_whatsapp($proiect)); ?>" target="_blank" rel="noopener" data-podreg-ev="click_whatsapp">
        <svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.5-4-4.7-4.2-.1-.2-1.1-1.5-1.1-2.9s.7-2 1-2.3c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .5l-.3.5-.4.4c-.1.1-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.3 2.4 1.5.3.1.5.1.6-.1l.9-1.1c.2-.3.4-.2.7-.1l1.9.9c.3.1.5.2.5.3.1.2.1.7-.1 1.2z"/></svg>
        <span><?php echo esc_html($txt['wa']); ?></span>
    </a>
    <a class="podreg-cta-bar__btn podreg-cta-bar__btn--primar" href="<?php echo esc_url(podreg_link_oferta($proiect)); ?>" data-podreg-ev="click_oferta">
        <span><?php echo esc_html($txt['quote']); ?></span>
    </a>
</nav>
<a class="podreg-cta-flot" href="<?php echo esc_url(podreg_link_oferta($proiect)); ?>" data-podreg-ev="click_oferta"><?php echo esc_html($txt['quote']); ?> →</a>
    <?php
}, 5);

// ---------- Bloc CTA la finalul paginilor de proiect ----------
add_filter('the_content', function ($continut) {
    static $adaugat = false;
    $proiect = podreg_proiect_curent();
    if ($adaugat || !$proiect || !podreg_e_frontend() || !in_the_loop() || !is_main_query() || get_the_ID() !== $proiect['id']) {
        return $continut;
    }
    $adaugat = true;
    $en = podreg_e_engleza();
    $titlu = esc_html(wp_strip_all_tags($proiect['titlu']));

    ob_start();
    ?>
<section class="podreg-cta-proiect" aria-labelledby="podreg-cta-proiect-titlu">
    <div class="podreg-cta-proiect__text">
        <p class="podreg-cta-proiect__eticheta"><?php echo $en ? 'Like this project?' : 'Îți place acest proiect?'; ?></p>
        <h2 id="podreg-cta-proiect-titlu"><?php
            echo $en
                ? 'We can build one like ' . $titlu . ' for you'
                : 'Construim pentru tine o variantă ca ' . $titlu;
        ?></h2>
        <p><?php echo $en
            ? 'Tell us the size, location and what you need. We reply with a first estimate and the next steps.'
            : 'Spune-ne suprafața, localitatea și ce îți dorești. Revenim cu o primă estimare și pașii următori.'; ?></p>
    </div>
    <div class="podreg-cta-proiect__butoane">
        <a class="podreg-btn podreg-btn--primar" href="<?php echo esc_url(podreg_link_oferta($proiect)); ?>" data-podreg-ev="click_oferta"><?php echo $en ? 'Request a quote' : 'Cere ofertă pentru acest model'; ?></a>
        <a class="podreg-btn podreg-btn--wa" href="<?php echo esc_url(podreg_link_whatsapp($proiect)); ?>" target="_blank" rel="noopener" data-podreg-ev="click_whatsapp">WhatsApp</a>
        <a class="podreg-btn podreg-btn--simplu" href="tel:<?php echo esc_attr((string) podreg_cfg('telefon')); ?>" data-podreg-ev="click_tel"><?php echo esc_html((string) podreg_cfg('telefon_afisat')); ?></a>
    </div>
</section>
    <?php
    return $continut . ob_get_clean();
}, 99);

// ---------- Google Tag Manager + Consent Mode v2 ----------
add_action('wp_head', function () {
    $gtm = (string) podreg_cfg('gtm_id', '');
    if ($gtm === '' || !preg_match('/^GTM-[A-Z0-9]+$/', $gtm) || !podreg_e_frontend()) {
        return;
    }
    ?>
<script>
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
(function(){var c=null;try{c=localStorage.getItem('podreg_consimtamant');}catch(e){}
var ok=c==='da'?'granted':'denied';
gtag('consent','default',{ad_storage:ok,ad_user_data:ok,ad_personalization:ok,analytics_storage:ok,functionality_storage:'granted',security_storage:'granted',wait_for_update:500});})();
(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?php echo esc_js($gtm); ?>');
</script>
    <?php
}, 2);

add_action('wp_footer', function () {
    if ((string) podreg_cfg('gtm_id', '') === '' || !podreg_e_frontend()) {
        return;
    }
    $en = podreg_e_engleza();
    ?>
<div class="podreg-cookies" role="dialog" aria-live="polite" aria-label="<?php echo $en ? 'Cookies' : 'Cookie-uri'; ?>" hidden>
    <p><?php echo $en
        ? 'We use cookies to measure visits and improve the site. You can accept or refuse them.'
        : 'Folosim cookie-uri pentru a măsura vizitele și a îmbunătăți site-ul. Le poți accepta sau refuza.'; ?>
        <a href="<?php echo esc_url(home_url('/politica-de-confidentialitate/')); ?>"><?php echo $en ? 'Details' : 'Detalii'; ?></a></p>
    <div class="podreg-cookies__butoane">
        <button type="button" class="podreg-btn podreg-btn--simplu" data-podreg-consimtamant="nu"><?php echo $en ? 'Refuse' : 'Refuz'; ?></button>
        <button type="button" class="podreg-btn podreg-btn--primar" data-podreg-consimtamant="da"><?php echo $en ? 'Accept' : 'Accept'; ?></button>
    </div>
</div>
    <?php
}, 6);
