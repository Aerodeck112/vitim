<?php
/**
 * Textul politicii de confidențialitate PODREG.
 *
 * ATENȚIE: model pregătit de VITIM pe baza formularelor existente pe site.
 * Trebuie verificat de PODREG (și ideal de un jurist) înainte de a fi considerat final.
 */

defined('ABSPATH') || exit;

$denumire = podreg_cfg('firma.denumire') ?: 'PODREG';
$cui      = podreg_cfg('firma.cui');
$regcom   = podreg_cfg('firma.reg_com');
$adresa   = podreg_cfg('firma.adresa');
$email    = podreg_cfg('email');
$telefon  = podreg_cfg('telefon_afisat');
$gtm      = (string) podreg_cfg('gtm_id', '') !== '';
?>
<h1>Politica de confidențialitate</h1>
<p><em>Ultima actualizare: <?php echo esc_html(date_i18n('j F Y', filemtime(__FILE__))); ?></em></p>

<h2>1. Cine suntem</h2>
<p>Operatorul datelor tale personale este <strong><?php echo esc_html($denumire); ?></strong><?php
    echo $cui ? ', CUI ' . esc_html($cui) : '';
    echo $regcom ? ', ' . esc_html($regcom) : '';
?>, cu sediul în <?php echo esc_html($adresa); ?> („PODREG”, „noi”).
Ne poți contacta la <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a> sau la telefon <?php echo esc_html($telefon); ?>.</p>

<h2>2. Ce date colectăm</h2>
<ul>
    <li><strong>Formularul de contact:</strong> nume, prenume, adresa de email, telefon, tema discuției și mesajul tău.</li>
    <li><strong>Configuratorul de cabane:</strong> suprafața aleasă, nume, telefon, email, localitatea proiectului, stadiul proiectului și detaliile pe care ni le scrii.</li>
    <li><strong>Comunicări directe:</strong> datele pe care ni le transmiți prin telefon, email sau WhatsApp.</li>
    <li><strong>Date tehnice:</strong> adresa IP și informații despre browser, păstrate temporar pentru securitatea site-ului și prevenirea abuzurilor.</li>
<?php if ($gtm) : ?>
    <li><strong>Date de utilizare a site-ului</strong> (pagini vizitate, sursa vizitei, acțiuni precum trimiterea unui formular), doar dacă accepți cookie-urile de analiză și marketing.</li>
<?php endif; ?>
</ul>

<h2>3. De ce le folosim și pe ce temei</h2>
<ul>
    <li><strong>Pentru a-ți răspunde și a-ți trimite o estimare sau o ofertă</strong>, la cererea ta, înainte de încheierea unui contract (art. 6 alin. (1) lit. b) GDPR).</li>
    <li><strong>Pentru executarea contractului</strong>, dacă devii client (art. 6 alin. (1) lit. b) GDPR), și pentru obligațiile legale contabile și fiscale (art. 6 alin. (1) lit. c) GDPR).</li>
    <li><strong>Pentru a te recontacta</strong> în legătură cu solicitarea ta, pe baza consimțământului exprimat în formular (art. 6 alin. (1) lit. a) GDPR). Îl poți retrage oricând.</li>
    <li><strong>Pentru securitatea site-ului</strong> și prevenirea mesajelor abuzive, pe baza interesului nostru legitim (art. 6 alin. (1) lit. f) GDPR).</li>
<?php if ($gtm) : ?>
    <li><strong>Pentru măsurarea și îmbunătățirea site-ului și a campaniilor</strong>, doar cu acordul tău prin bannerul de cookie-uri (art. 6 alin. (1) lit. a) GDPR).</li>
<?php endif; ?>
</ul>
<p>Nu luăm decizii automate cu efecte juridice asupra ta. Estimarea din configurator este orientativă și nu reprezintă o ofertă contractuală.</p>

<h2>4. Cui transmitem datele</h2>
<ul>
    <li>Furnizorului de găzduire web și de email, care păstrează datele în numele nostru.</li>
    <li>Agenției care administrează tehnic site-ul, strict pentru mentenanță.</li>
<?php if ($gtm) : ?>
    <li>Google (Google Analytics / Google Ads) și, dacă e cazul, Meta, doar cu acordul tău. Acești furnizori pot transfera date în afara Spațiului Economic European, pe baza clauzelor contractuale standard sau a Cadrului UE-SUA privind confidențialitatea datelor.</li>
<?php endif; ?>
    <li>Autorităților, doar când legea ne obligă.</li>
</ul>
<p>Nu vindem și nu închiriem datele tale.</p>

<h2>5. Cât timp le păstrăm</h2>
<ul>
    <li>Solicitările care nu devin contracte: cel mult 24 de luni de la ultimul contact.</li>
    <li>Datele clienților și documentele contractuale: pe durata contractului și apoi cât impune legislația contabilă și fiscală.</li>
    <li>Datele tehnice de securitate: cel mult 30 de zile.</li>
</ul>

<h2>6. Drepturile tale</h2>
<p>Ai dreptul de acces, rectificare, ștergere, restricționare a prelucrării, portabilitate, opoziție și dreptul de a-ți retrage oricând consimțământul, fără a afecta prelucrarea făcută până atunci. Pentru oricare dintre ele, scrie-ne la <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>. Răspundem în cel mult o lună.</p>
<p>Poți depune plângere la Autoritatea Națională de Supraveghere a Prelucrării Datelor cu Caracter Personal (<a href="https://www.dataprotection.ro/" target="_blank" rel="noopener">www.dataprotection.ro</a>).</p>

<h2>7. Cookie-uri</h2>
<?php if ($gtm) : ?>
<p>Site-ul folosește cookie-uri strict necesare funcționării și, doar cu acordul tău, cookie-uri de analiză și marketing (Google Tag Manager, Google Analytics, eventual Google Ads și Meta). Îți poți schimba alegerea ștergând datele site-ului din browser; bannerul va apărea din nou.</p>
<?php else : ?>
<p>Site-ul folosește doar cookie-uri strict necesare funcționării. Nu folosim cookie-uri de analiză sau publicitate.</p>
<?php endif; ?>

<h2>8. Securitate</h2>
<p>Folosim conexiune criptată (HTTPS), acces restricționat la date și măsuri împotriva mesajelor automate și a abuzurilor.</p>

<h2>9. Modificări</h2>
<p>Putem actualiza această politică. Data ultimei actualizări apare la începutul paginii.</p>
