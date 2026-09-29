<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
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

    private function tabs(): array
    {
        $icons = \App\Core\Icons::names();
        return [
            'firma' => ['label' => 'Firmă', 'fields' => [
                ['company_name', 'Denumire legală', 'text'], ['brand_name', 'Nume brand', 'text'], ['brand_tagline', 'Slogan scurt', 'text'],
                ['company_cui', 'CUI / CIF', 'text', 'Apare în subsol, în schema Google și în paginile legale.'], ['company_reg', 'Nr. Registrul Comerțului', 'text', 'Ex: J26/1234/2020'],
                ['company_address', 'Adresă (stradă, număr)', 'text'], ['company_city', 'Oraș', 'text'], ['company_county', 'Județ', 'text'], ['company_postal', 'Cod poștal', 'text'],
                ['company_lat', 'Latitudine sediu', 'text', 'Din Google Maps: click dreapta pe locație → primul număr.'], ['company_lng', 'Longitudine sediu', 'text'],
                ['phone', 'Telefon', 'text'], ['whatsapp', 'WhatsApp (număr)', 'text', 'Lasă gol ca să ascunzi butonul WhatsApp.'], ['email', 'Email public', 'email'],
                ['hours', 'Program (text afișat)', 'text'], ['hours_schema', 'Program pentru Google (JSON)', 'textarea', 'Format: [{"days":["Monday",…],"opens":"08:00","closes":"18:00"}]'],
                ['support_note', 'Notă suport (pagina de contact)', 'text'], ['founded_year', 'Anul înființării', 'text'],
                ['social_facebook', 'Facebook', 'url'], ['social_instagram', 'Instagram', 'url'], ['social_linkedin', 'LinkedIn', 'url'], ['social_youtube', 'YouTube', 'url'], ['social_tiktok', 'TikTok', 'url'],
                ['google_business_url', 'Profil Google Business (link)', 'url', 'Foarte important pentru SEO local.'], ['google_maps_url', 'Link Google Maps sediu', 'url'],
            ]],
            'aspect' => ['label' => 'Aspect', 'fields' => [
                ['logo', 'Logo (temă luminoasă / implicit)', 'image', 'PNG sau WebP cu fundal transparent, ~280×68 px.'], ['logo_dark', 'Logo pentru tema întunecată (opțional)', 'image'],
                ['theme_default', 'Tema implicită a site-ului', 'select', '', ['dark' => 'Întunecată', 'light' => 'Luminoasă']],
                ['home_hero_image', 'Fotografie mare pe prima pagină', 'image', 'Ideal o poză reală: echipa, o intervenție, sediul. Format lat, minim 1600 px.'],
                ['about_image', 'Fotografie pagina Despre noi', 'image', 'Pune aici o poză cu echipa ta – crește mult încrederea.'],
                ['announcement', 'Bară de anunț (sus)', 'text', 'Ex: „Nou: audit de securitate gratuit în octombrie”. Gol = ascunsă.'], ['announcement_link', 'Link anunț', 'text'],
            ]],
            'prima' => ['label' => 'Prima pagină', 'fields' => [
                ['home_badge', 'Etichetă deasupra titlului', 'text'], ['home_title', 'Titlu principal (H1)', 'text', 'Poți folosi <span class="grad">text</span> pentru culoarea gradient.'],
                ['home_subtitle', 'Subtitlu', 'textarea'], ['home_cta_primary', 'Buton principal', 'text'], ['home_cta_secondary', 'Buton secundar', 'text'],
                ['home_points', 'Puncte sub butoane', 'list'], ['home_trust', 'Tehnologii (bandă derulantă)', 'list', 'Pune doar tehnologii cu care lucrezi efectiv.'],
                ['home_stats', 'Cifre (doar reale!)', 'repeater', '', [['key' => 'value', 'label' => 'Valoare'], ['key' => 'label', 'label' => 'Descriere']]],
                ['home_why', 'De ce noi', 'repeater', '', [['key' => 'icon', 'label' => 'Iconiță', 'type' => 'select', 'options' => $icons], ['key' => 'title', 'label' => 'Titlu'], ['key' => 'text', 'label' => 'Text', 'type' => 'textarea']]],
                ['home_process', 'Cum lucrăm (pași)', 'repeater', '', [['key' => 'title', 'label' => 'Pas'], ['key' => 'text', 'label' => 'Descriere', 'type' => 'textarea']]],
                ['home_faq', 'Întrebări frecvente', 'repeater', '', [['key' => 'q', 'label' => 'Întrebare'], ['key' => 'a', 'label' => 'Răspuns', 'type' => 'textarea']]],
                ['home_cta_title', 'Titlu secțiune contact', 'text'], ['home_cta_text', 'Text secțiune contact', 'textarea'],
            ]],
            'email' => ['label' => 'Email & SMTP', 'fields' => [
                ['mail_driver', 'Metodă de trimitere', 'select', 'SMTP este recomandat (emailurile ajung mai rar în spam).', ['smtp' => 'SMTP (recomandat)', 'mail' => 'PHP mail() – rezervă']],
                ['smtp_host', 'Server SMTP', 'text', 'Pe cPanel: mail.domeniultau.ro'], ['smtp_port', 'Port', 'text', '465 pentru SSL, 587 pentru TLS'],
                ['smtp_secure', 'Criptare', 'select', '', ['ssl' => 'SSL (465)', 'tls' => 'STARTTLS (587)', 'none' => 'Fără']],
                ['smtp_user', 'Utilizator SMTP', 'text', 'De obicei adresa completă de email.'], ['smtp_pass', 'Parolă SMTP', 'password', 'Lasă gol ca să păstrezi parola salvată.'],
                ['mail_from', 'Expeditor (email)', 'email'], ['mail_from_name', 'Expeditor (nume)', 'text'], ['mail_reply_to', 'Reply-To (opțional)', 'email'],
                ['notify_email', 'Notificări lead-uri noi către', 'text', 'Poți pune mai multe adrese separate prin virgulă.'],
                ['autoreply_enabled', 'Răspuns automat către client după formular', 'checkbox'], ['autoreply_subject', 'Subiect răspuns automat', 'text'], ['autoreply_body', 'Text răspuns automat', 'richtext', 'Variabile: {{prenume}}, {{nume}}, {{telefon}}'],
                ['newsletter_double_optin', 'Confirmare prin email la abonare (double opt-in, recomandat GDPR)', 'checkbox'],
                ['newsletter_hourly_limit', 'Limită emailuri campanie / oră', 'number', 'Verifică limita hostingului tău (de obicei 100–500/oră).'], ['newsletter_batch', 'Emailuri per lot', 'number'],
            ]],
            'asistent' => ['label' => 'Asistent AI', 'fields' => [
                ['ai_enabled', 'Activat pe site', 'checkbox', 'Afișează butonul de chat pe toate paginile.'],
                ['ai_api_key', 'Cheie API (Anthropic)', 'password', 'Din console.anthropic.com → API Keys. Se salvează criptat. Lasă gol ca să păstrezi cheia salvată.'],
                ['ai_model', 'Model', 'select', 'Opus 5.5 dă cele mai bune răspunsuri. Sonnet și Haiku sunt mai ieftine.', ['claude-opus-5-5' => 'Claude Opus 5.5 (recomandat)', 'claude-sonnet-5-5' => 'Claude Sonnet 5.5', 'claude-haiku-4-5' => 'Claude Haiku 4.5 (cel mai ieftin)']],
                ['ai_effort', 'Nivel de gândire', 'select', 'Scăzut = răspunsuri rapide și ieftine, potrivit pentru chat.', ['low' => 'Scăzut (recomandat pentru chat)', 'medium' => 'Mediu', 'high' => 'Ridicat']],
                ['ai_name', 'Numele asistentului', 'text'], ['ai_greeting', 'Mesaj de întâmpinare', 'textarea'],
                ['ai_suggestions', 'Întrebări sugerate (butoane rapide)', 'list'],
                ['ai_instructions', 'Instrucțiuni suplimentare', 'textarea', 'Ex: promoții curente, ce să nu promită, informații noi. Asistentul știe deja automat serviciile, zonele, FAQ-ul și articolele de pe site.'],
                ['ai_daily_limit', 'Limită mesaje pe zi (tot site-ul)', 'number', 'Protecție la costuri în caz de abuz.'], ['ai_max_turns', 'Mesaje maxime pe conversație', 'number'],
            ]],
            'integrari' => ['label' => 'Integrări & tracking', 'fields' => [
                ['ga4_id', 'Google Analytics 4 – Measurement ID', 'text', 'Format G-XXXXXXX. Se încarcă doar după acordul pentru cookies.'],
                ['gtm_id', 'Google Tag Manager – Container ID', 'text', 'Format GTM-XXXXXX. Folosește fie GTM, fie GA4 direct, nu dubla măsurarea.'],
                ['google_ads_id', 'Google Ads – Conversion ID', 'text', 'Format AW-XXXXXXXXX'], ['google_ads_lead_label', 'Google Ads – etichetă conversie lead', 'text', 'Se trimite automat la fiecare formular trimis.'],
                ['meta_pixel_id', 'Meta Pixel ID', 'text'], ['clarity_id', 'Microsoft Clarity ID', 'text'],
                ['turnstile_site_key', 'Cloudflare Turnstile – site key (anti-spam, opțional)', 'text'], ['turnstile_secret', 'Cloudflare Turnstile – secret', 'password'],
                ['custom_head', 'Cod personalizat în <head>', 'code', 'Atenție: codul de aici rulează pe toate paginile.'], ['custom_body', 'Cod personalizat după <body>', 'code'],
            ]],
            'formulare' => ['label' => 'Formulare & CRM', 'fields' => [
                ['crm_stages', 'Etapele pipeline-ului', 'repeater', 'Cheile „castigat” și „pierdut” au semnificație specială (închid oportunitatea).', [['key' => 'key', 'label' => 'Cheie (fără spații)'], ['key' => 'label', 'label' => 'Denumire'], ['key' => 'color', 'label' => 'Culoare (#hex)']]],
                ['form_budgets', 'Variante de buget în formular', 'list'],
                ['consent_text', 'Text acord GDPR (obligatoriu)', 'textarea'], ['newsletter_consent_text', 'Text acord newsletter', 'textarea'],
            ]],
            'avansat' => ['label' => 'Avansat', 'fields' => [
                ['page_cache', 'Cache de pagini (site foarte rapid)', 'checkbox', 'Se golește automat la orice modificare din panou.'], ['page_cache_ttl', 'Durata cache (secunde)', 'number'],
                ['cookie_banner', 'Banner de cookies (GDPR)', 'checkbox'],
                ['maintenance_mode', 'Mod mentenanță (site ascuns pentru vizitatori)', 'checkbox', 'Tu, fiind autentificat, vezi site-ul normal.'],
            ]],
        ];
    }

    public function index(string $tab = 'firma'): string
    {
        $tabs = $this->tabs();
        if (!isset($tabs[$tab])) {
            $tab = 'firma';
        }
        if ($tab !== 'prima' && !Auth::can('settings')) {
            redirect('/admin/setari/prima');
        }
        return $this->render('settings', ['tabs' => $tabs, 'tab' => $tab, 'title' => $tab === 'prima' ? 'Prima pagină' : 'Setări']);
    }

    public function save(string $tab): never
    {
        $tabs = $this->tabs();
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
                    $v = $raw;
                    break;
                case 'richtext':
                    $v = Sanitizer::html((string)$raw, true);
                    break;
                case 'code':
                    $v = (string)$raw; // doar administratorii ajung aici
                    break;
                case 'list':
                    $lines = array_values(array_filter(array_map('trim', explode("\n", (string)$raw))));
                    $v = json_encode($lines, JSON_UNESCAPED_UNICODE);
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
                    if ($key === 'crm_stages') {
                        foreach ($clean as &$st) {
                            $st['key'] = slugify($st['key'] ?: $st['label']);
                            $st['color'] = preg_match('/^#[0-9a-f]{3,8}$/i', $st['color']) ? $st['color'] : '#3b82f6';
                        }
                        unset($st);
                        if (!$clean) {
                            continue 2;
                        }
                    }
                    $v = json_encode($clean, JSON_UNESCAPED_UNICODE);
                    break;
                case 'image':
                    $v = is_string($raw) && $raw !== '' && DB::val('SELECT id FROM media WHERE path = ?', [$raw]) ? $raw : '';
                    break;
                case 'textarea':
                    $v = $key === 'hours_schema' ? (json_decode((string)$raw) !== null ? (string)$raw : (string)Settings::get('hours_schema')) : Sanitizer::text((string)$raw, 5000);
                    break;
                default:
                    $v = $key === 'home_title' ? strip_tags((string)$raw, '<span><br><em><strong>') : Sanitizer::text((string)$raw, 500);
            }
            Settings::set($key, $v);
        }
        if ($tab === 'email') {
            Settings::set('smtp_tested', '0');
        }
        Cache::clear();
        flash('ok', 'Setările au fost salvate.');
        redirect('/admin/setari/' . $tab);
    }

    public function testEmail(): never
    {
        $to = str_input('to') ?: (string)(Auth::user()['email'] ?? '');
        [$ok, $err] = Mailer::send($to, 'Test email – ' . Settings::get('brand_name'), Mailer::layout('<h2>Funcționează! ✅</h2><p>Emailurile din site (notificări lead-uri, răspunsuri automate, newsletter) sunt configurate corect.</p><p style="color:#6b7489;font-size:14px">Trimis la ' . date('d.m.Y H:i') . ' prin ' . e((string)(Settings::get('smtp_host') ?: 'PHP mail()')) . '.</p>'), ['kind' => 'test']);
        if ($ok) {
            Settings::set('smtp_tested', '1');
            flash('ok', "Email de test trimis către $to. Verifică și folderul Spam.");
        } else {
            flash('err', 'Trimiterea a eșuat: ' . $err);
        }
        redirect('/admin/setari/email');
    }

    public function clearCache(): never
    {
        $n = Cache::clear();
        flash('ok', "Cache golit ($n pagini).");
        redirect($_SERVER['HTTP_REFERER'] ?? '/admin/dashboard');
    }
}
