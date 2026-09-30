<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\BtIpay;
use App\Core\Cache;
use App\Core\DB;
use App\Core\Mailer;
use App\Core\Sanitizer;
use App\Core\Settings;

final class SettingsController extends AdminController
{
    protected string $area = 'settings';

    public function __construct()
    {
        // „Prima pagină” poate fi editată și de editorii de conținut
        $tab = explode('/', trim(\App\Core\App::$path, '/'))[2] ?? '';
        if ($tab === 'prima') {
            $this->area = 'content';
        }
        parent::__construct();
    }

    public static function tabs(): array
    {
        return [
            'magazin' => ['label' => 'Magazin & livrare', 'fields' => [
                ['shipping_label', 'Denumire livrare', 'text'], ['shipping_cost', 'Cost livrare (lei)', 'money'],
                ['free_shipping_over', 'Livrare gratuită pentru comenzi peste (lei, 0 = niciodată)', 'money'], ['delivery_time', 'Termen de livrare (text)', 'text', 'Ex: 1–3 zile lucrătoare. Apare pe site, în pagina Livrare și în datele pentru Google.'],
                ['pickup_enabled', 'Permite ridicare personală', 'checkbox'], ['pickup_label', 'Denumire ridicare personală', 'text'], ['pickup_address', 'Adresa și programul pentru ridicare', 'text'],
                ['min_order_total', 'Valoare minimă comandă (lei, 0 = fără)', 'money'], ['return_days', 'Zile pentru retur', 'number'],
                ['order_prefix', 'Prefix număr comandă', 'text', 'Ex: BDM → comenzile vor fi BDM1001, BDM1002…'], ['low_stock_threshold', 'Alertă stoc scăzut sub (bucăți)', 'number'],
                ['hold_unpaid_minutes', 'Anulează comenzile cu card neplătite după (minute)', 'number', 'Stocul rezervat revine în magazin. Minim 15 minute.'],
                ['prices_note', 'Notă prețuri (la finalizarea comenzii)', 'text'], ['cart_note', 'Mesaj în coș (opțional)', 'text'], ['checkout_note', 'Mesaj la finalizarea comenzii (opțional)', 'text'],
                ['announcement', 'Bară de anunț (sus pe toate paginile)', 'text', 'Gol = ascunsă.'], ['announcement_link', 'Link bară de anunț', 'text'],
                ['reviews_enabled', 'Recenzii pe paginile produselor', 'checkbox'],
                ['exclusive_brands', 'Branduri importate exclusiv (separate prin virgulă)', 'text', 'Produsele acestor branduri primesc eticheta de mai jos și mesajul „unicul importator din țară”.'], ['exclusive_label', 'Etichetă produse exclusive', 'text'],
            ]],
            'plati' => ['label' => 'Plăți (BT iPay)', 'fields' => [
                ['pay_card_enabled', 'Plată online cu cardul (BT iPay) activă', 'checkbox'],
                ['bt_mode', 'Mod BT iPay', 'select', 'Folosește TEST până când banca îți confirmă că implementarea a fost aprobată și îți trimite datele de producție.', ['test' => 'TEST (sandbox – nu se încasează bani)', 'live' => 'PRODUCȚIE (plăți reale)']],
                ['bt_user_test', 'Utilizator API – TEST', 'text'], ['bt_pass_test', 'Parolă API – TEST', 'password', 'Se salvează criptat. Lasă gol ca să păstrezi parola salvată.'],
                ['bt_user', 'Utilizator API – PRODUCȚIE', 'text'], ['bt_pass', 'Parolă API – PRODUCȚIE', 'password', 'Se salvează criptat. Lasă gol ca să păstrezi parola salvată.'],
                ['bt_two_phase', 'Plată în două etape (preautorizare, încasare manuală din panou)', 'checkbox', 'Bifează doar dacă BT ți-a activat contul în modul „2 faze”. Implicit: plată într-o etapă (suma se încasează imediat).'],
                ['bt_description', 'Descriere tranzacție (apare în extrasul clientului)', 'text', 'Variabile: {{numar}}'],
                ['bt_callback_key', 'Cheie checksum callback (opțional, primită de la BT)', 'password'],
                ['bt_url_test', 'Server API – TEST (avansat)', 'text'], ['bt_url_live', 'Server API – PRODUCȚIE (avansat)', 'text'],
                ['pay_card_label', 'Denumire plată cu cardul', 'text'], ['pay_card_text', 'Descriere plată cu cardul', 'textarea'],
                ['pay_cod_enabled', 'Plată ramburs activă', 'checkbox'], ['pay_cod_label', 'Denumire ramburs', 'text'], ['pay_cod_text', 'Descriere ramburs', 'textarea'],
                ['pay_cod_fee', 'Taxă ramburs (lei, 0 = fără)', 'money'], ['pay_cod_max', 'Ramburs doar pentru comenzi până la (lei, 0 = oricât)', 'money'],
                ['pay_transfer_enabled', 'Transfer bancar (ordin de plată) activ', 'checkbox', 'Datele bancare se iau din Setări → Firmă (IBAN, banca).'], ['pay_transfer_label', 'Denumire transfer bancar', 'text'], ['pay_transfer_text', 'Descriere transfer bancar', 'textarea'],
            ]],
            'firma' => ['label' => 'Firmă', 'fields' => [
                ['company_name', 'Denumire legală', 'text'], ['brand_name', 'Nume magazin', 'text'], ['brand_tagline', 'Slogan scurt', 'text'],
                ['company_cui', 'CUI / CIF', 'text', 'Obligatoriu pe site pentru BT iPay și ANPC. Apare în subsol și în paginile legale.'], ['company_reg', 'Nr. Registrul Comerțului', 'text', 'Ex: J26/1234/2020'],
                ['company_address', 'Adresă sediu (stradă, număr)', 'text'], ['company_city', 'Localitate', 'text'], ['company_county', 'Județ', 'text'], ['company_postal', 'Cod poștal', 'text'],
                ['company_bank', 'Banca', 'text'], ['company_iban', 'IBAN', 'text', 'Folosit pentru plata prin transfer bancar.'],
                ['phone', 'Telefon', 'text', 'Recomandat: BT iPay cere date de contact complete pe site.'], ['email', 'Email public', 'email'], ['hours', 'Program (text)', 'text'],
                ['social_facebook', 'Facebook', 'url'], ['social_instagram', 'Instagram', 'url'], ['social_tiktok', 'TikTok', 'url'], ['social_youtube', 'YouTube', 'url'],
                ['google_business_url', 'Profil Google Business (link)', 'url'],
                ['logo', 'Logo (fundal deschis)', 'image', 'PNG sau WebP cu fundal transparent.'], ['logo_light', 'Logo pentru fundal închis (subsol)', 'image', 'Opțional – implicit se folosește varianta albă a logo-ului actual.'],
            ]],
            'email' => ['label' => 'Email & SMTP', 'fields' => [
                ['mail_driver', 'Metodă de trimitere', 'select', 'SMTP este recomandat (emailurile ajung mai rar în spam).', ['smtp' => 'SMTP (recomandat)', 'mail' => 'PHP mail() – rezervă']],
                ['smtp_host', 'Server SMTP', 'text', 'Pe cPanel: mail.bunatatidelamichele.ro'], ['smtp_port', 'Port', 'text', '465 pentru SSL, 587 pentru TLS'],
                ['smtp_secure', 'Criptare', 'select', '', ['ssl' => 'SSL (465)', 'tls' => 'STARTTLS (587)', 'none' => 'Fără']],
                ['smtp_user', 'Utilizator SMTP', 'text', 'De obicei adresa completă de email.'], ['smtp_pass', 'Parolă SMTP', 'password', 'Lasă gol ca să păstrezi parola salvată.'],
                ['mail_from', 'Expeditor (email)', 'email'], ['mail_from_name', 'Expeditor (nume)', 'text'], ['mail_reply_to', 'Reply-To (opțional)', 'email'],
                ['notify_email', 'Notificări comenzi și mesaje către', 'text', 'Poți pune mai multe adrese separate prin virgulă.'],
                ['email_order_footer', 'Text la finalul emailurilor de comandă', 'textarea'],
            ]],
            'integrari' => ['label' => 'Integrări & tracking', 'fields' => [
                ['ga4_id', 'Google Analytics 4 – Measurement ID', 'text', 'Format G-XXXXXXX. Se încarcă doar după acordul pentru cookies. Evenimentul „purchase” se trimite automat.'],
                ['gtm_id', 'Google Tag Manager – Container ID', 'text', 'Format GTM-XXXXXX. Folosește fie GTM, fie GA4 direct.'],
                ['google_ads_id', 'Google Ads – Conversion ID', 'text', 'Format AW-XXXXXXXXX'], ['google_ads_purchase_label', 'Google Ads – etichetă conversie „achiziție”', 'text'],
                ['meta_pixel_id', 'Meta (Facebook) Pixel ID', 'text', 'Trimite automat AddToCart și Purchase.'], ['clarity_id', 'Microsoft Clarity ID', 'text'],
                ['merchant_feed', 'Feed Google Merchant Center activ', 'checkbox'],
                ['turnstile_site_key', 'Cloudflare Turnstile – site key (anti-spam formular contact, opțional)', 'text'], ['turnstile_secret', 'Cloudflare Turnstile – secret', 'password'],
                ['custom_head', 'Cod personalizat în <head>', 'code', 'Atenție: codul de aici rulează pe toate paginile.'], ['custom_body', 'Cod personalizat după <body>', 'code'],
            ]],
            'avansat' => ['label' => 'Avansat', 'fields' => [
                ['page_cache', 'Cache de pagini (site foarte rapid)', 'checkbox', 'Se golește automat la orice modificare din panou. Coșul și comenzile nu sunt niciodată puse în cache.'], ['page_cache_ttl', 'Durata cache (secunde)', 'number'],
                ['cookie_banner', 'Banner de cookies (GDPR)', 'checkbox'],
                ['consent_text', 'Text acord termeni (la comandă)', 'textarea'], ['contact_consent_text', 'Text acord formular contact', 'textarea'],
                ['maintenance_mode', 'Mod mentenanță (magazin ascuns pentru vizitatori)', 'checkbox', 'Tu, fiind autentificat, vezi site-ul normal.'],
            ]],
            'prima' => ['label' => 'Prima pagină', 'fields' => [
                ['home_slides', 'Slide-uri (sus pe prima pagină)', 'repeater', 'Primul slide conține titlul principal (H1) al paginii. Imaginile arată cel mai bine cu fundal transparent (PNG/WebP).', [['key' => 'kicker', 'label' => 'Text mic deasupra'], ['key' => 'title', 'label' => 'Titlu'], ['key' => 'accent', 'label' => 'Text auriu, cursiv'], ['key' => 'text', 'label' => 'Text', 'type' => 'textarea'], ['key' => 'button', 'label' => 'Text buton'], ['key' => 'link', 'label' => 'Link buton (ex: /produse)'], ['key' => 'image', 'label' => 'Imagine', 'type' => 'image']]],
                ['home_excl_kicker', 'Exclusivitate – text mic', 'text'], ['home_excl_title', 'Exclusivitate – titlu', 'text'],
                ['home_excl', 'Exclusivitate – argumente (3 coloane)', 'repeater', '', [['key' => 'title', 'label' => 'Titlu'], ['key' => 'text', 'label' => 'Text', 'type' => 'textarea']]],
                ['home_intro_title', 'Colecția (categorii) – titlu', 'text'], ['home_intro_text', 'Colecția (categorii) – text', 'textarea'],
                ['home_featured_kicker', 'Produse recomandate – text mic', 'text'], ['home_featured_title', 'Produse recomandate – titlu', 'text'], ['home_featured_text', 'Produse recomandate – text', 'textarea'],
                ['home_brands_kicker', 'Branduri – text mic', 'text'], ['home_brands_title', 'Branduri – titlu', 'text'],
                ['home_brands', 'Branduri (cardurile Saka / Pareo)', 'repeater', '', [['key' => 'name', 'label' => 'Brand'], ['key' => 'tag', 'label' => 'Subtitlu'], ['key' => 'text', 'label' => 'Descriere', 'type' => 'textarea'], ['key' => 'link', 'label' => 'Link (ex: /categorie/cafea-boabe)'], ['key' => 'image', 'label' => 'Imagine', 'type' => 'image']]],
                ['home_quote', 'Citat (sub branduri)', 'textarea'], ['home_quote_cite', 'Semnătură citat', 'text'],
                ['home_stats_kicker', 'Cifre – text mic', 'text'], ['home_stats_title', 'Cifre – titlu', 'text'], ['home_stats_text', 'Cifre – text', 'textarea'],
                ['home_stats', 'Cifre (doar reale!)', 'repeater', '', [['key' => 'value', 'label' => 'Valoare'], ['key' => 'label', 'label' => 'Descriere']]],
                ['home_intro_image', 'Imagine secțiunea cifre', 'image'],
                ['home_process_kicker', 'Proces – text mic', 'text'], ['home_process_title', 'Proces – titlu', 'text'], ['home_process_text', 'Proces – text', 'textarea'],
                ['home_process', 'Pașii procesului', 'repeater', '', [['key' => 'title', 'label' => 'Pas'], ['key' => 'text', 'label' => 'Descriere', 'type' => 'textarea']]],
                ['about_image', 'Fotografie „Despre noi”', 'image'],
                ['faq', 'Întrebări frecvente (prima pagină și Contact)', 'repeater', '', [['key' => 'q', 'label' => 'Întrebare'], ['key' => 'a', 'label' => 'Răspuns', 'type' => 'textarea']]],
                ['home_footer_text', 'Text în subsol', 'textarea'],
            ]],
        ];
    }

    public function index(string $tab = 'magazin'): string
    {
        $tabs = self::tabs();
        if (!isset($tabs[$tab])) {
            $tab = 'magazin';
        }
        if ($tab !== 'prima' && !Auth::can('settings')) {
            redirect('/admin/setari/prima');
        }
        return $this->render('settings', ['tabs' => $tabs, 'tab' => $tab, 'title' => $tab === 'prima' ? 'Prima pagină' : 'Setări']);
    }

    public function save(string $tab): never
    {
        $tabs = self::tabs();
        if (!isset($tabs[$tab])) {
            redirect('/admin/setari');
        }
        foreach ($tabs[$tab]['fields'] as $f) {
            [$key, , $type] = $f;
            $raw = $_POST[$key] ?? null;
            switch ($type) {
                case 'checkbox':
                    $v = !empty($raw) ? '1' : '0';
                    break;
                case 'password':
                    if (!is_string($raw) || $raw === '') {
                        continue 2;
                    }
                    $v = trim($raw);
                    break;
                case 'money':
                    $v = (string)max(0, round((float)str_replace([' ', ','], ['', '.'], (string)$raw), 2));
                    break;
                case 'number':
                    $v = (string)max(0, (int)$raw);
                    break;
                case 'code':
                    $v = (string)$raw; // doar administratorii ajung aici
                    break;
                case 'repeater':
                    $list = json_decode((string)$raw, true);
                    $clean = [];
                    foreach (is_array($list) ? $list : [] as $it) {
                        if (is_array($it)) {
                            $o = [];
                            foreach ($f[4] as $sf) {
                                $o[$sf['key']] = Sanitizer::text((string)($it[$sf['key']] ?? ''), 2000);
                            }
                            if (implode('', $o) !== '') {
                                $clean[] = $o;
                            }
                        }
                    }
                    $v = json_encode($clean, JSON_UNESCAPED_UNICODE);
                    break;
                case 'image':
                    $v = is_string($raw) && $raw !== '' && DB::val('SELECT id FROM media WHERE path = ?', [$raw]) ? $raw : '';
                    break;
                case 'textarea':
                    $v = Sanitizer::text((string)$raw, 5000);
                    break;
                case 'select':
                    $v = is_string($raw) && array_key_exists($raw, $f[4]) ? $raw : (string)array_key_first($f[4]);
                    break;
                default:
                    $v = Sanitizer::text((string)$raw, 500);
            }
            if ($key === 'hold_unpaid_minutes') {
                $v = (string)max(15, (int)$v);
            }
            if ($key === 'order_prefix') {
                $v = strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '', $v)) ?: 'BDM';
            }
            if (in_array($key, ['bt_url_test', 'bt_url_live'], true) && !preg_match('#^https://#', $v)) {
                continue;
            }
            Settings::set($key, $v);
        }
        if ($tab === 'email') {
            Settings::set('smtp_tested', '0');
        }
        if ($tab === 'plati' && Settings::get('pay_card_enabled') === '1' && !BtIpay::configured()) {
            flash('info', 'Plata cu cardul este bifată, dar lipsesc datele API pentru modul ' . (BtIpay::mode() === 'live' ? 'PRODUCȚIE' : 'TEST') . ' – până le completezi, clienții nu văd opțiunea de plată cu cardul.');
        }
        Cache::clear();
        flash('ok', 'Setările au fost salvate.');
        redirect('/admin/setari/' . $tab);
    }

    public function testEmail(): never
    {
        $to = str_input('to') ?: (string)(Auth::user()['email'] ?? '');
        [$ok, $err] = Mailer::send($to, 'Test email – ' . Settings::get('brand_name'), Mailer::layout('<h2>Funcționează! ✅</h2><p>Emailurile magazinului (confirmări de comandă, notificări, AWB) sunt configurate corect.</p><p style="color:#8a7663;font-size:14px">Trimis la ' . date('d.m.Y H:i') . ' prin ' . e((string)(Settings::get('smtp_host') ?: 'PHP mail()')) . '.</p>'), ['kind' => 'test']);
        if ($ok) {
            Settings::set('smtp_tested', '1');
            flash('ok', "Email de test trimis către $to. Verifică și folderul Spam.");
        } else {
            flash('err', 'Trimiterea a eșuat: ' . $err);
        }
        redirect('/admin/setari/email');
    }

    /** Test conexiune BT iPay: interogăm o comandă inexistentă – un răspuns de tip „comandă negăsită” confirmă că serverul și datele de acces sunt corecte. */
    public function testBt(): never
    {
        [$r, $err] = BtIpay::call('getOrderStatusExtended.do', ['orderNumber' => 'TEST-' . time(), 'language' => 'ro']);
        if (!$r) {
            flash('err', 'Conexiunea la BT iPay a eșuat: ' . $err);
        } else {
            $code = (string)($r['errorCode'] ?? '');
            $msg = (string)($r['errorMessage'] ?? '');
            if (in_array($code, ['5'], true) && preg_match('/(access|acces|password|parol|user|autoriz|login)/i', $msg)) {
                flash('err', 'Serverul BT răspunde, dar utilizatorul sau parola API nu sunt acceptate: ' . $msg);
            } else {
                Settings::set('bt_tested_at', DB::now());
                flash('ok', 'Conexiune reușită la BT iPay (' . (BtIpay::mode() === 'live' ? 'PRODUCȚIE' : 'TEST') . '). Răspuns server: ' . ($msg ?: 'OK') . ($code !== '' ? " (cod $code)" : '') . '. Fă acum o comandă de test pe site.');
            }
        }
        redirect('/admin/setari/plati');
    }

    public function clearCache(): never
    {
        $n = Cache::clear();
        flash('ok', "Cache golit ($n pagini).");
        redirect($_SERVER['HTTP_REFERER'] ?? '/admin/dashboard');
    }
}
