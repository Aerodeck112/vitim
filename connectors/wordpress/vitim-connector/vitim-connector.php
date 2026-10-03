<?php
/**
 * Plugin Name:       VITIM Connector
 * Description:       Conectează site-ul la panoul VITIM: asistentul AI pe site, formulare de abonare, magazinul WooCommerce (coș abandonat, comenzi), starea site-ului, scanarea problemelor, remedieri, backup și jurnalul automat al lucrărilor.
 * Version:           1.5.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            VITIM
 * Author URI:        https://vitim.ro
 * License:           GPL-2.0-or-later
 * Text Domain:       vitim-connector
 */

if (! defined('ABSPATH')) {
    exit;
}

define('VITIM_CONNECTOR_VERSION', '1.5.0');
require_once __DIR__.'/includes-backup.php';
require_once __DIR__.'/includes-seo.php';
require_once __DIR__.'/includes-shop.php';

final class Vitim_Connector
{
    const OPTION = 'vitim_connector';
    const QUEUE = 'vitim_connector_queue';
    const CRON = 'vitim_connector_heartbeat';
    const FLUSH = 'vitim_connector_flush';
    const HARDENING = 'vitim_connector_hardening';

    /** Reinstalarea nucleului e înregistrată de panou; hook-ul de actualizare nu o mai trece o dată în jurnal. */
    private static $reinstalling = false;

    /** Singurele acțiuni pe care panoul le poate cere (aceeași listă există în panou). */
    const ACTIONS = ['scan', 'backup', 'update_plugin', 'update_all_plugins', 'update_theme', 'update_core', 'reinstall_core',
        'delete_debug_log', 'delete_readme', 'disable_xmlrpc', 'disable_file_edit', 'block_php_uploads', 'allow_indexing', 'seo_fix'];

    public static function boot()
    {
        Vitim_Connector_Seo::boot();
        Vitim_Connector_Shop::boot();
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_vitim_connector_seo_reset', [__CLASS__, 'seoReset']);
        add_action('admin_post_vitim_connector_save', [__CLASS__, 'save']);
        add_action('admin_post_vitim_connector_send', [__CLASS__, 'sendNow']);
        add_action('admin_post_vitim_connector_disconnect', [__CLASS__, 'disconnect']);
        add_action(self::CRON, [__CLASS__, 'run']);
        add_action(self::FLUSH, [__CLASS__, 'flush']);
        add_action('upgrader_process_complete', [__CLASS__, 'onUpgrade'], 10, 2);
        add_action('rest_api_init', [__CLASS__, 'routes']);
        add_action('admin_post_vitim_connector_options', [__CLASS__, 'saveOptions']);
        add_filter('pre_set_site_transient_update_plugins', [__CLASS__, 'selfUpdate']);
        add_action(Vitim_Connector_Backup::HOOK, [__CLASS__, 'backup']);
        add_action('wp_footer', [__CLASS__, 'widget']);
        add_action('admin_post_vitim_connector_backup_settings', [__CLASS__, 'saveBackupSettings']);
        self::harden();
        add_filter('plugin_action_links_'.plugin_basename(__FILE__), function ($links) {
            array_unshift($links, '<a href="'.esc_url(admin_url('options-general.php?page=vitim-connector')).'">Setări</a>');

            return $links;
        });
    }

    public static function activate()
    {
        if (! wp_next_scheduled(self::CRON)) {
            wp_schedule_event(time() + 60, 'hourly', self::CRON);
        }
        if (! wp_next_scheduled(Vitim_Connector_Backup::HOOK)) {
            Vitim_Connector_Backup::schedule();
        }
    }

    public static function deactivate()
    {
        wp_clear_scheduled_hook(self::CRON);
        wp_clear_scheduled_hook(self::FLUSH);
        wp_clear_scheduled_hook(Vitim_Connector_Backup::HOOK);
        wp_clear_scheduled_hook(Vitim_Connector_Shop::HOOK);
    }

    /** @return array{url?: string, key?: string, secret?: string, last?: array} */
    private static function settings()
    {
        $s = get_option(self::OPTION, []);

        return is_array($s) ? $s : [];
    }

    private static function connected()
    {
        $s = self::settings();

        return ! empty($s['url']) && ! empty($s['key']) && ! empty($s['secret']);
    }

    // ---------- pagina de setări ----------

    public static function menu()
    {
        add_options_page('VITIM', 'VITIM', 'manage_options', 'vitim-connector', [__CLASS__, 'page']);
    }

    public static function page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $s = self::settings();
        $last = isset($s['last']) && is_array($s['last']) ? $s['last'] : [];
        $notice = isset($_GET['vitim']) ? sanitize_key(wp_unslash($_GET['vitim'])) : '';
        $messages = [
            'saved' => ['success', 'Site conectat. Starea a fost trimisă la VITIM.'],
            'invalid' => ['error', 'Codul de conectare nu e valid. Copiază-l din nou din panoul VITIM.'],
            'sent' => ['success', 'Datele au fost trimise.'],
            'failed' => ['error', 'Trimiterea a eșuat. Vezi detaliile de mai jos.'],
            'disconnected' => ['success', 'Site deconectat.'],
        ];
        echo '<div class="wrap"><h1>VITIM Connector</h1>';
        if (isset($messages[$notice])) {
            printf('<div class="notice notice-%s"><p>%s</p></div>', esc_attr($messages[$notice][0]), esc_html($messages[$notice][1]));
        }
        if (self::connected()) {
            echo '<table class="form-table" role="presentation">';
            printf('<tr><th>Panou</th><td>%s</td></tr>', esc_html($s['url']));
            printf('<tr><th>Cheie site</th><td><code>%s</code></td></tr>', esc_html($s['key']));
            if ($last) {
                printf('<tr><th>Ultima trimitere</th><td>%s — %s</td></tr>', esc_html(wp_date('d.m.Y H:i', (int) $last['time'])), $last['ok'] ? '<span style="color:#00a32a">reușită</span>' : '<span style="color:#d63638">eșuată: '.esc_html($last['message']).'</span>');
            }
            $queue = get_option(self::QUEUE, []);
            printf('<tr><th>Lucrări în așteptare</th><td>%d</td></tr>', is_array($queue) ? count($queue) : 0);
            if (Vitim_Connector_Shop::active()) {
                $p = Vitim_Connector_Shop::pending();
                printf('<tr><th>Magazin WooCommerce</th><td>conectat · %d evenimente și %d produse în așteptare</td></tr>', $p['events'], $p['products']);
            }
            echo '</table>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="margin:12px 0">';
            wp_nonce_field('vitim_connector_options');
            echo '<input type="hidden" name="action" value="vitim_connector_options">';
            printf('<label><input type="checkbox" name="remote_fixes" value="1" %s> Permite echipei VITIM să aplice remedieri din panou (actualizări, securizare). Scanarea rămâne activă oricum.</label><br>', checked(self::remoteFixes(), true, false));
            printf('<label><input type="checkbox" name="widget" value="1" %s> Afișează asistentul AI pe site (apare doar dacă agentul e activ în panoul VITIM)</label><br> ', checked(self::widgetEnabled(), true, false));
            if (Vitim_Connector_Shop::active()) {
                printf('<label><input type="checkbox" name="shop_consent" value="1" %s> WooCommerce: bifă „Vreau să primesc oferte pe email” la finalizarea comenzii (nebifată implicit)</label><br>', checked(Vitim_Connector_Shop::settings()['consent'], true, false));
            }
            submit_button('Salvează', 'secondary', 'submit', false);
            echo '</form>';
            $seo = array_intersect_key(Vitim_Connector_Seo::FIXES, Vitim_Connector_Seo::modules());
            if ($seo) {
                echo '<h2>Remedieri SEO aplicate de VITIM</h2><ul style="list-style:disc;margin-left:20px">';
                foreach ($seo as $label) {
                    echo '<li>'.esc_html($label).'</li>';
                }
                echo '</ul><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" onsubmit="return confirm(\'Oprești toate remedierile SEO aplicate de VITIM?\')">';
                wp_nonce_field('vitim_connector_seo_reset');
                echo '<input type="hidden" name="action" value="vitim_connector_seo_reset">';
                submit_button('Oprește remedierile SEO', 'secondary', 'submit', false);
                echo '</form>';
            }
            $b = Vitim_Connector_Backup::settings();
            list($bdir, $inside) = Vitim_Connector_Backup::directory();
            echo '<h2>Backup</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
            wp_nonce_field('vitim_connector_backup_settings');
            echo '<input type="hidden" name="action" value="vitim_connector_backup_settings"><table class="form-table" role="presentation">';
            echo '<tr><th>Backup automat</th><td><select name="schedule">';
            foreach (['daily' => 'Zilnic (noaptea)', 'weekly' => 'Săptămânal', 'off' => 'Oprit'] as $k => $l) {
                printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($b['schedule'], $k, false), esc_html($l));
            }
            echo '</select></td></tr>';
            printf('<tr><th>Păstrează</th><td><input type="number" name="keep" min="1" max="30" value="%d" class="small-text"> copii</td></tr>', (int) $b['keep']);
            printf('<tr><th>Unde</th><td><code>%s</code>%s</td></tr>', esc_html($bdir), $inside ? ' <em>(în site, protejat: folderul principal al contului nu permite scrierea)</em>' : '');
            if (! empty($b['last'])) {
                $last = $b['last'];
                printf('<tr><th>Ultimul backup</th><td>%s — %s</td></tr>', esc_html(wp_date('d.m.Y H:i', strtotime($last['started_at']))), $last['status'] === 'ok' && $last['verified'] ? '<span style="color:#00a32a">reușit și verificat ('.esc_html(size_format($last['db_bytes'] + $last['files_bytes'])).')</span>' : '<span style="color:#d63638">eșuat: '.esc_html((string) $last['error']).'</span>');
            }
            echo '</table>';
            submit_button('Salvează setările de backup', 'secondary', 'submit', false);
            echo ' ';
            submit_button('Fă backup acum', 'primary', 'backup_now', false);
            echo '</form>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin-right:8px">';
            wp_nonce_field('vitim_connector_send');
            echo '<input type="hidden" name="action" value="vitim_connector_send">';
            submit_button('Trimite acum', 'primary', 'submit', false);
            echo '</form>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block" onsubmit="return confirm(\'Deconectezi site-ul de la VITIM?\')">';
            wp_nonce_field('vitim_connector_disconnect');
            echo '<input type="hidden" name="action" value="vitim_connector_disconnect">';
            submit_button('Deconectează', 'secondary', 'submit', false);
            echo '</form>';
        } else {
            echo '<p>Lipește codul de conectare primit de la VITIM (începe cu <code>VITIM1-</code>).</p>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
            wp_nonce_field('vitim_connector_save');
            echo '<input type="hidden" name="action" value="vitim_connector_save">';
            echo '<p><textarea name="code" rows="4" class="large-text code" required autocomplete="off"></textarea></p>';
            submit_button('Conectează');
            echo '</form>';
        }
        echo '</div>';
    }

    public static function save()
    {
        if (! current_user_can('manage_options')) {
            wp_die('Acces interzis.');
        }
        check_admin_referer('vitim_connector_save');
        $code = isset($_POST['code']) ? trim(sanitize_text_field(wp_unslash($_POST['code']))) : '';
        $data = self::decode($code);
        if (! $data) {
            wp_safe_redirect(admin_url('options-general.php?page=vitim-connector&vitim=invalid'));
            exit;
        }
        update_option(self::OPTION, ['url' => $data['u'], 'key' => $data['k'], 'secret' => $data['s']], false);
        self::activate();
        self::run();
        $last = self::settings()['last'] ?? ['ok' => false];
        wp_safe_redirect(admin_url('options-general.php?page=vitim-connector&vitim='.($last['ok'] ? 'saved' : 'failed')));
        exit;
    }

    public static function saveOptions()
    {
        if (! current_user_can('manage_options')) {
            wp_die('Acces interzis.');
        }
        check_admin_referer('vitim_connector_options');
        $s = self::settings();
        $s['remote_fixes'] = ! empty($_POST['remote_fixes']);
        $s['widget'] = ! empty($_POST['widget']);
        $s['shop_consent'] = ! empty($_POST['shop_consent']);
        update_option(self::OPTION, $s, false);
        self::run();
        wp_safe_redirect(admin_url('options-general.php?page=vitim-connector&vitim=sent'));
        exit;
    }

    public static function seoReset()
    {
        if (! current_user_can('manage_options')) {
            wp_die('Acces interzis.');
        }
        check_admin_referer('vitim_connector_seo_reset');
        Vitim_Connector_Seo::reset();
        wp_safe_redirect(admin_url('options-general.php?page=vitim-connector&vitim=sent'));
        exit;
    }

    public static function saveBackupSettings()
    {
        if (! current_user_can('manage_options')) {
            wp_die('Acces interzis.');
        }
        check_admin_referer('vitim_connector_backup_settings');
        Vitim_Connector_Backup::save(['schedule' => isset($_POST['schedule']) ? sanitize_key(wp_unslash($_POST['schedule'])) : 'daily', 'keep' => isset($_POST['keep']) ? (int) $_POST['keep'] : 7]);
        if (isset($_POST['backup_now'])) {
            self::backup();
        } else {
            self::run();
        }
        wp_safe_redirect(admin_url('options-general.php?page=vitim-connector&vitim=sent'));
        exit;
    }

    /** Backup + raport la panou (din WP-Cron, din butonul local sau cerut din panou). */
    public static function backup()
    {
        $report = Vitim_Connector_Backup::run();
        if (self::connected()) {
            self::post('backup', $report);
        }

        return $report;
    }

    private static function widgetEnabled()
    {
        $s = self::settings();

        return ! isset($s['widget']) || (bool) $s['widget'];
    }

    /**
     * Scriptul VITIM în subsolul paginilor publice: chatul (doar dacă e bifat aici și agentul e activ în panou),
     * formularele de abonare publicate în panou și recunoașterea abonaților veniți din emailuri.
     */
    public static function widget()
    {
        if (is_admin() || ! self::connected()) {
            return;
        }
        $s = self::settings();
        printf('<script src="%s" data-site="%s"%s async></script>'."\n", esc_url(rtrim($s['url'], '/').'/widget/v1/loader.js'), esc_attr($s['key']), self::widgetEnabled() ? '' : ' data-chat="0"');
    }

    private static function remoteFixes()
    {
        $s = self::settings();

        return ! isset($s['remote_fixes']) || (bool) $s['remote_fixes'];
    }

    public static function sendNow()
    {
        if (! current_user_can('manage_options')) {
            wp_die('Acces interzis.');
        }
        check_admin_referer('vitim_connector_send');
        self::run();
        $last = self::settings()['last'] ?? ['ok' => false];
        wp_safe_redirect(admin_url('options-general.php?page=vitim-connector&vitim='.($last['ok'] ? 'sent' : 'failed')));
        exit;
    }

    public static function disconnect()
    {
        if (! current_user_can('manage_options')) {
            wp_die('Acces interzis.');
        }
        check_admin_referer('vitim_connector_disconnect');
        delete_option(self::OPTION);
        delete_option(self::QUEUE);
        self::deactivate();
        wp_safe_redirect(admin_url('options-general.php?page=vitim-connector&vitim=disconnected'));
        exit;
    }

    /** @return array{u: string, k: string, s: string}|null */
    public static function decode($code)
    {
        if (strpos($code, 'VITIM1-') !== 0) {
            return null;
        }
        $json = base64_decode(strtr(substr($code, 7), '-_', '+/'), true);
        $data = $json ? json_decode($json, true) : null;
        if (! is_array($data) || empty($data['u']) || empty($data['k']) || empty($data['s'])) {
            return null;
        }
        if (! preg_match('#^https?://#', $data['u']) || strpos($data['k'], 'pk_') !== 0 || strpos($data['s'], 'sk_') !== 0) {
            return null;
        }

        return $data;
    }

    // ---------- trimitere ----------

    /** Heartbeat + lucrările din coadă. Rulează la oră (WP-Cron) și la „Trimite acum”. */
    public static function run()
    {
        if (! self::connected()) {
            return;
        }
        // la actualizarea pluginului nu rulează hook-ul de activare: programarea backup-ului se reface aici
        if (Vitim_Connector_Backup::settings()['schedule'] !== 'off' && ! wp_next_scheduled(Vitim_Connector_Backup::HOOK)) {
            Vitim_Connector_Backup::schedule();
        }
        $result = self::post('heartbeat', self::health());
        self::remember($result);
        if ($result['ok']) {
            self::flush();
            $s = self::settings();
            if (empty($s['last_scan']) || $s['last_scan'] < time() - DAY_IN_SECONDS) {
                self::sendScan();
            }
            Vitim_Connector_Shop::sync();
        }
    }

    private static function sendScan()
    {
        $result = self::post('scan', ['issues' => self::scan()]);
        if ($result['ok']) {
            $s = self::settings();
            $s['last_scan'] = time();
            update_option(self::OPTION, $s, false);
        }

        return $result;
    }

    public static function flush()
    {
        $queue = get_option(self::QUEUE, []);
        if (! self::connected() || ! is_array($queue) || ! $queue) {
            return;
        }
        $batch = array_slice($queue, 0, 50);
        $result = self::post('worklog', ['entries' => array_values($batch)]);
        if ($result['ok']) {
            // trimise (sau deja existente) → scoase din coadă; restul pleacă la următoarea rulare
            update_option(self::QUEUE, array_slice($queue, count($batch)), false);
        } else {
            self::remember($result);
        }
    }

    /** @return array */
    private static function health()
    {
        require_once ABSPATH.'wp-admin/includes/plugin.php';
        require_once ABSPATH.'wp-admin/includes/update.php';
        global $wp_version;

        $updates = [];
        $transient = get_site_transient('update_plugins');
        $all = get_plugins();
        if (is_object($transient) && ! empty($transient->response)) {
            foreach ($transient->response as $file => $info) {
                $updates[] = [
                    'name' => isset($all[$file]['Name']) ? $all[$file]['Name'] : $file,
                    'from' => isset($all[$file]['Version']) ? $all[$file]['Version'] : null,
                    'to' => isset($info->new_version) ? (string) $info->new_version : null,
                ];
            }
        }
        $core = get_site_transient('update_core');
        $coreUpdate = null;
        if (is_object($core) && ! empty($core->updates)) {
            foreach ($core->updates as $u) {
                if (isset($u->response) && $u->response === 'upgrade') {
                    $coreUpdate = (string) $u->current;
                    break;
                }
            }
        }
        $themes = get_site_transient('update_themes');

        return [
            'platform' => 'wordpress',
            'site_url' => home_url('/'),
            'connector_version' => VITIM_CONNECTOR_VERSION,
            'php_version' => PHP_VERSION,
            'core_version' => (string) $wp_version,
            'core_update' => $coreUpdate,
            'theme' => wp_get_theme()->get('Name'),
            'plugins_total' => count($all),
            'plugin_updates' => array_slice($updates, 0, 200),
            'theme_updates' => is_object($themes) && ! empty($themes->response) ? count($themes->response) : 0,
            'https' => is_ssl() || strpos(home_url(), 'https://') === 0,
            'command_url' => rest_url('vitim/v1/command'),
            'remote_fixes' => self::remoteFixes(),
            'backup_schedule' => Vitim_Connector_Backup::settings()['schedule'],
            'backup_keep' => (int) Vitim_Connector_Backup::settings()['keep'],
        ];
    }

    /** @return array{ok: bool, message: string} */
    public static function post($endpoint, array $payload)
    {
        $s = self::settings();
        $body = wp_json_encode($payload);
        $ts = (string) time();
        $nonce = wp_generate_password(32, false, false);
        $response = wp_remote_post(rtrim($s['url'], '/').'/connector/v1/'.$endpoint, [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Vitim-Key' => $s['key'],
                'X-Vitim-Timestamp' => $ts,
                'X-Vitim-Nonce' => $nonce,
                'X-Vitim-Signature' => hash_hmac('sha256', $ts.'.'.$nonce.'.'.$body, $s['secret']),
            ],
            'body' => $body,
        ]);
        if (is_wp_error($response)) {
            return ['ok' => false, 'message' => $response->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $json = json_decode(wp_remote_retrieve_body($response), true);
            $message = isset($json['error']['message']) ? $json['error']['message'] : (isset($json['message']) ? $json['message'] : 'HTTP '.$code);

            return ['ok' => false, 'message' => $message];
        }

        return ['ok' => true, 'message' => 'OK'];
    }

    private static function remember(array $result)
    {
        $s = self::settings();
        $s['last'] = ['ok' => $result['ok'], 'message' => $result['message'], 'time' => time()];
        update_option(self::OPTION, $s, false);
    }

    // ---------- jurnal automat ----------

    /** Actualizările făcute din WordPress intră în jurnalul de lucrări VITIM. */
    public static function onUpgrade($upgrader, $extra)
    {
        if (self::$reinstalling || ! self::connected() || empty($extra['action']) || $extra['action'] !== 'update' || empty($extra['type'])) {
            return;
        }
        require_once ABSPATH.'wp-admin/includes/plugin.php';
        $entries = [];
        if ($extra['type'] === 'plugin') {
            $plugins = ! empty($extra['plugins']) ? (array) $extra['plugins'] : (! empty($extra['plugin']) ? [$extra['plugin']] : []);
            foreach ($plugins as $file) {
                $path = WP_PLUGIN_DIR.'/'.$file;
                if (! file_exists($path)) {
                    continue;
                }
                $data = get_plugin_data($path, false, false);
                $entries[] = self::entry('plugin:'.$file.':'.$data['Version'], 'Plugin actualizat: '.$data['Name'].' '.$data['Version']);
            }
        } elseif ($extra['type'] === 'theme') {
            $themes = ! empty($extra['themes']) ? (array) $extra['themes'] : (! empty($extra['theme']) ? [$extra['theme']] : []);
            foreach ($themes as $slug) {
                $theme = wp_get_theme($slug);
                if ($theme->exists()) {
                    $entries[] = self::entry('theme:'.$slug.':'.$theme->get('Version'), 'Temă actualizată: '.$theme->get('Name').' '.$theme->get('Version'));
                }
            }
        } elseif ($extra['type'] === 'core') {
            global $wp_version;
            include ABSPATH.WPINC.'/version.php'; // versiunea nouă, după actualizare
            $entries[] = self::entry('core:'.$wp_version, 'WordPress actualizat la '.$wp_version);
        } elseif ($extra['type'] === 'translation') {
            $entries[] = self::entry('translation:'.gmdate('Y-m-d-H'), 'Traduceri actualizate');
        }
        if (! $entries) {
            return;
        }
        $queue = get_option(self::QUEUE, []);
        $queue = array_slice(array_merge(is_array($queue) ? $queue : [], $entries), -200);
        update_option(self::QUEUE, $queue, false);
        if (! wp_next_scheduled(self::FLUSH)) {
            wp_schedule_single_event(time() + 30, self::FLUSH);
        }
    }

    private static function entry($ref, $title, $category = 'updates')
    {
        return ['ref' => substr($ref, 0, 64), 'category' => $category, 'title' => substr($title, 0, 190), 'performed_at' => gmdate('c')];
    }

    // ---------- securizare (aplicată de plugin, la cererea panoului) ----------

    private static function hardening()
    {
        $h = get_option(self::HARDENING, []);

        return is_array($h) ? $h : [];
    }

    public static function harden()
    {
        $h = self::hardening();
        if (! empty($h['xmlrpc'])) {
            // „xmlrpc_enabled” oprește doar metodele autentificate; fără metode, xmlrpc.php nu mai face nimic
            add_filter('xmlrpc_enabled', '__return_false');
            add_filter('xmlrpc_methods', '__return_empty_array');
            add_filter('wp_headers', function ($headers) {
                unset($headers['X-Pingback']);

                return $headers;
            });
        }
        if (! empty($h['file_edit'])) {
            add_filter('map_meta_cap', function ($caps, $cap) {
                return in_array($cap, ['edit_plugins', 'edit_themes', 'edit_files'], true) ? ['do_not_allow'] : $caps;
            }, 10, 2);
        }
    }

    // ---------- scanare ----------

    /** Problemele site-ului. Doar citire: scanarea nu schimbă nimic. @return array */
    public static function scan()
    {
        require_once ABSPATH.'wp-admin/includes/plugin.php';
        require_once ABSPATH.'wp-admin/includes/update.php';
        global $wp_version;
        $issues = [];
        $add = function ($code, $severity, $title, $details = null, $fix = null) use (&$issues) {
            $issues[] = ['code' => substr($code, 0, 120), 'severity' => $severity, 'title' => substr($title, 0, 255), 'details' => $details ? substr($details, 0, 4000) : null, 'fix' => $fix];
        };

        // actualizări
        wp_version_check();
        wp_update_plugins();
        wp_update_themes();
        $core = get_site_transient('update_core');
        if (is_object($core) && ! empty($core->updates)) {
            foreach ($core->updates as $u) {
                if (isset($u->response) && $u->response === 'upgrade') {
                    $add('core_update', 'warning', 'WordPress '.$u->current.' este disponibil (instalat: '.$wp_version.')', null, 'update_core');
                    break;
                }
            }
        }
        $all = get_plugins();
        $pluginUpdates = get_site_transient('update_plugins');
        if (is_object($pluginUpdates) && ! empty($pluginUpdates->response)) {
            foreach ($pluginUpdates->response as $file => $info) {
                $name = isset($all[$file]['Name']) ? $all[$file]['Name'] : $file;
                $from = isset($all[$file]['Version']) ? $all[$file]['Version'] : '?';
                $add('plugin_update:'.$file, 'warning', 'Plugin de actualizat: '.$name.' '.$from.' → '.$info->new_version, null, 'update_plugin:'.$file);
            }
        }
        $themeUpdates = get_site_transient('update_themes');
        if (is_object($themeUpdates) && ! empty($themeUpdates->response)) {
            foreach ($themeUpdates->response as $slug => $info) {
                $theme = wp_get_theme($slug);
                $add('theme_update:'.$slug, 'warning', 'Temă de actualizat: '.$theme->get('Name').' '.$theme->get('Version').' → '.$info['new_version'], null, 'update_theme:'.$slug);
            }
        }

        // server și configurare
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $add('php_old', 'critical', 'PHP '.PHP_VERSION.' nu mai primește actualizări de securitate', 'Se schimbă din cPanel → Select PHP Version (recomandat 8.2 sau mai nou).');
        } elseif (version_compare(PHP_VERSION, '8.1', '<')) {
            $add('php_old', 'warning', 'PHP '.PHP_VERSION.' e vechi', 'Se schimbă din cPanel → Select PHP Version (recomandat 8.2 sau mai nou).');
        }
        if (strpos(home_url(), 'https://') !== 0) {
            $add('no_https', 'critical', 'Site-ul nu folosește HTTPS', 'Adresa site-ului din Setări → General începe cu http://. Necesită certificat SSL (AutoSSL în cPanel) și schimbarea adresei.');
        }
        if (! get_option('blog_public')) {
            $add('noindex', 'critical', 'Site-ul cere motoarelor de căutare să nu-l indexeze', 'Setări → Citire → „Descurajează motoarele de căutare” este bifat. Site-ul nu apare în Google.', 'allow_indexing');
        }
        if (defined('WP_DEBUG') && WP_DEBUG && (! defined('WP_DEBUG_DISPLAY') || WP_DEBUG_DISPLAY)) {
            $add('debug_display', 'warning', 'Erorile PHP sunt afișate vizitatorilor (WP_DEBUG activ)', 'Se dezactivează din wp-config.php: define(\'WP_DEBUG\', false);');
        }
        if (file_exists(WP_CONTENT_DIR.'/debug.log')) {
            $add('debug_log', 'critical', 'Fișierul debug.log e public', 'wp-content/debug.log ('.size_format(filesize(WP_CONTENT_DIR.'/debug.log')).') poate conține căi și date interne și se poate descărca din browser.', 'delete_debug_log');
        }
        if (file_exists(ABSPATH.'readme.html')) {
            $add('readme', 'info', 'readme.html afișează versiunea WordPress', null, 'delete_readme');
        }
        $h = self::hardening();
        if (! (defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT) && empty($h['file_edit'])) {
            $add('file_edit', 'info', 'Editorul de fișiere din WordPress e activ', 'Un cont de administrator compromis poate modifica direct codul pluginurilor și temei.', 'disable_file_edit');
        }
        if (empty($h['xmlrpc'])) {
            $add('xmlrpc', 'info', 'XML-RPC e activ', 'Folosit rar (aplicații vechi), des țintă pentru încercări de ghicire a parolei.', 'disable_xmlrpc');
        }

        // utilizatori
        $admins = get_users(['role' => 'administrator', 'fields' => ['user_login']]);
        foreach ($admins as $a) {
            if (strtolower($a->user_login) === 'admin') {
                $add('admin_username', 'warning', 'Există un administrator cu numele „admin”', 'Primul nume încercat în atacuri. Creează un administrator nou cu alt nume și șterge-l pe acesta.');
            }
        }
        if (count($admins) > 3) {
            $add('many_admins', 'info', count($admins).' conturi de administrator', implode(', ', array_map(function ($a) { return $a->user_login; }, $admins)));
        }

        // pluginuri inactive
        $inactive = array_diff(array_keys($all), (array) get_option('active_plugins', []));
        $inactive = array_values(array_filter($inactive, function ($f) { return strpos($f, 'vitim-connector') === false; }));
        if ($inactive) {
            $add('inactive_plugins', 'info', count($inactive).' pluginuri inactive instalate', 'Pluginurile inactive pot avea vulnerabilități. Dacă nu sunt folosite, ar trebui șterse: '.implode(', ', array_map(function ($f) use ($all) { return $all[$f]['Name']; }, $inactive)));
        }

        // integritate: fișierele WordPress comparate cu cele oficiale
        $modified = self::coreChanges();
        if ($modified === null) {
            $add('core_checksums_unavailable', 'info', 'Nu am putut verifica integritatea fișierelor WordPress (api.wordpress.org indisponibil)');
        } elseif ($modified) {
            $add('core_modified', 'critical', count($modified).' fișiere WordPress modificate sau necunoscute', "Posibil cod malițios. Primele fișiere:\n".implode("\n", array_slice($modified, 0, 25)), 'reinstall_core');
        }
        $uploads = wp_get_upload_dir();
        $php = self::phpFiles($uploads['basedir'], 25);
        if ($php) {
            $blocked = file_exists($uploads['basedir'].'/.htaccess') && strpos((string) file_get_contents($uploads['basedir'].'/.htaccess'), 'VITIM') !== false;
            $add('php_in_uploads', $blocked ? 'warning' : 'critical', 'Fișiere PHP în folderul de imagini (uploads)'.($blocked ? ', blocate' : ''),
                "În uploads nu ar trebui să existe cod PHP. Verifică și șterge ce nu recunoști:\n".implode("\n", array_map(function ($f) use ($uploads) { return substr($f, strlen($uploads['basedir']) + 1); }, $php)),
                $blocked ? null : 'block_php_uploads');
        }

        return $issues;
    }

    /** Fișiere din nucleul WordPress care diferă de versiunea oficială sau nu fac parte din ea. @return array|null */
    private static function coreChanges()
    {
        global $wp_version;
        $locale = get_locale();
        $response = wp_remote_get('https://api.wordpress.org/core/checksums/1.0/?'.http_build_query(['version' => $wp_version, 'locale' => $locale]), ['timeout' => 20]);
        $json = is_wp_error($response) ? null : json_decode(wp_remote_retrieve_body($response), true);
        if (empty($json['checksums']) && $locale !== 'en_US') {
            $response = wp_remote_get('https://api.wordpress.org/core/checksums/1.0/?'.http_build_query(['version' => $wp_version, 'locale' => 'en_US']), ['timeout' => 20]);
            $json = is_wp_error($response) ? null : json_decode(wp_remote_retrieve_body($response), true);
        }
        if (empty($json['checksums']) || ! is_array($json['checksums'])) {
            return null;
        }
        $checksums = $json['checksums'];
        $changed = [];
        foreach ($checksums as $file => $md5) {
            if (strpos($file, 'wp-content/') === 0) {
                continue;
            }
            $path = ABSPATH.$file;
            if (file_exists($path) && md5_file($path) !== $md5) {
                $changed[] = $file.' (modificat)';
            }
        }
        // fișiere PHP în plus în wp-admin / wp-includes
        foreach (['wp-admin', 'wp-includes'] as $dir) {
            foreach (self::phpFiles(ABSPATH.$dir, 200) as $path) {
                $rel = substr($path, strlen(ABSPATH));
                if (! isset($checksums[$rel])) {
                    $changed[] = $rel.' (necunoscut)';
                }
            }
        }

        return $changed;
    }

    private static function phpFiles($dir, $limit)
    {
        $found = [];
        if (! is_dir($dir)) {
            return $found;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile() && preg_match('/\.(php\d?|phtml|phar)$/i', $file->getFilename())) {
                $found[] = $file->getPathname();
                if (count($found) >= $limit) {
                    break;
                }
            }
        }

        return $found;
    }

    // ---------- remedieri cerute din panou ----------

    public static function routes()
    {
        register_rest_route('vitim/v1', '/command', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'command'],
            // autorizarea e semnătura HMAC verificată în callback (cererea vine de la panou, nu de la un utilizator WordPress)
            'permission_callback' => '__return_true',
        ]);
    }

    public static function command(WP_REST_Request $request)
    {
        $s = self::settings();
        $body = $request->get_body();
        $ts = (string) $request->get_header('x_vitim_timestamp');
        $nonce = (string) $request->get_header('x_vitim_nonce');
        $signature = (string) $request->get_header('x_vitim_signature');
        $key = (string) $request->get_header('x_vitim_key');
        $valid = self::connected()
            && hash_equals((string) $s['key'], $key)
            && ctype_digit($ts) && abs(time() - (int) $ts) <= 300
            && strlen($nonce) >= 16 && strlen($nonce) <= 128
            && hash_equals(hash_hmac('sha256', $ts.'.'.$nonce.'.'.$body, (string) $s['secret']), $signature);
        if (! $valid) {
            return new WP_REST_Response(['ok' => false, 'message' => 'Semnătură invalidă.'], 401);
        }
        $nonceKey = 'vitim_nonce_'.md5($nonce);
        if (get_transient($nonceKey)) {
            return new WP_REST_Response(['ok' => false, 'message' => 'Cerere repetată.'], 401);
        }
        set_transient($nonceKey, 1, 900);

        $data = json_decode($body, true);
        $action = isset($data['action']) ? (string) $data['action'] : '';
        $target = isset($data['target']) ? (string) $data['target'] : '';
        if (! in_array($action, self::ACTIONS, true)) {
            return new WP_REST_Response(['ok' => false, 'message' => 'Acțiune nepermisă.'], 400);
        }
        if ($action !== 'scan' && ! self::remoteFixes()) {
            return new WP_REST_Response(['ok' => false, 'message' => 'Remedierile de la distanță sunt oprite din pluginul de pe site.'], 403);
        }

        @set_time_limit(300);
        try {
            $applied = null;
            if ($action === 'seo_fix') {
                list($ok, $message, $applied) = Vitim_Connector_Seo::apply($target, isset($data['data']) && is_array($data['data']) ? $data['data'] : []);
            } else {
                list($ok, $message) = self::execute($action, $target);
            }
        } catch (Throwable $e) {
            list($ok, $message) = [false, 'Eroare: '.$e->getMessage()];
        }
        self::flush(); // lucrările înregistrate de actualizări pleacă imediat
        $issues = self::scan();
        $s = self::settings();
        $s['last_scan'] = time();
        update_option(self::OPTION, $s, false);

        return new WP_REST_Response(['ok' => $ok, 'message' => $message, 'issues' => $issues] + ($applied !== null ? ['applied' => $applied] : []), 200);
    }

    /** @return array{0: bool, 1: string} */
    private static function execute($action, $target)
    {
        switch ($action) {
            case 'scan':
                return [true, 'Scanare făcută.'];
            case 'backup':
                // backup-ul poate dura minute: pornește imediat în fundal (WP-Cron), rezultatul vine separat
                wp_schedule_single_event(time(), Vitim_Connector_Backup::HOOK);
                spawn_cron();

                return [true, 'Backup pornit. Rezultatul apare la Backup-uri în câteva minute.'];
            case 'allow_indexing':
                update_option('blog_public', 1);

                return [true, 'Indexarea este permisă.'];
            case 'disable_xmlrpc':
            case 'disable_file_edit':
                $h = self::hardening();
                $h[$action === 'disable_xmlrpc' ? 'xmlrpc' : 'file_edit'] = true;
                update_option(self::HARDENING, $h, false);

                return [true, $action === 'disable_xmlrpc' ? 'XML-RPC dezactivat.' : 'Editorul de fișiere dezactivat.'];
            case 'delete_debug_log':
                $file = WP_CONTENT_DIR.'/debug.log';

                return file_exists($file) && ! @unlink($file) ? [false, 'Nu am putut șterge debug.log (permisiuni).'] : [true, 'debug.log șters.'];
            case 'delete_readme':
                $file = ABSPATH.'readme.html';

                return file_exists($file) && ! @unlink($file) ? [false, 'Nu am putut șterge readme.html (permisiuni).'] : [true, 'readme.html șters.'];
            case 'block_php_uploads':
                $dir = wp_get_upload_dir()['basedir'];
                $file = $dir.'/.htaccess';
                $current = file_exists($file) ? (string) file_get_contents($file) : '';
                if (strpos($current, 'VITIM') === false) {
                    $rules = "\n# BEGIN VITIM: fără execuție PHP în uploads\n<FilesMatch \"\\.(php\\d?|phtml|phar)$\">\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n</FilesMatch>\n# END VITIM\n";
                    if (@file_put_contents($file, $current.$rules) === false) {
                        return [false, 'Nu am putut scrie uploads/.htaccess (permisiuni).'];
                    }
                }

                return [true, 'Execuția PHP în uploads este blocată. Fișierele găsite trebuie verificate manual.'];
        }

        return self::upgrade($action, $target);
    }

    /** Actualizări prin mecanismul WordPress (aceleași ca din Panou → Actualizări). @return array{0: bool, 1: string} */
    private static function upgrade($action, $target)
    {
        require_once ABSPATH.'wp-admin/includes/admin.php';
        require_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php';
        global $wp_version;
        if (! WP_Filesystem()) {
            return [false, 'WordPress nu poate scrie direct fișierele (FS_METHOD). Actualizarea trebuie făcută din WordPress.'];
        }
        $skin = new Automatic_Upgrader_Skin();

        if ($action === 'update_plugin' || $action === 'update_all_plugins') {
            wp_update_plugins();
            $updates = get_site_transient('update_plugins');
            $available = is_object($updates) && ! empty($updates->response) ? array_keys($updates->response) : [];
            if ($action === 'update_plugin') {
                if (! array_key_exists($target, get_plugins())) {
                    return [false, 'Pluginul nu există pe site.'];
                }
                if (! in_array($target, $available, true)) {
                    return [true, 'Pluginul este deja la zi.'];
                }
                $available = [$target];
            }
            if (! $available) {
                return [true, 'Toate pluginurile sunt la zi.'];
            }
            $result = (new Plugin_Upgrader($skin))->bulk_upgrade($available);
            $failed = array_keys(array_filter((array) $result, function ($r) { return ! $r || is_wp_error($r); }));

            return $failed ? [false, 'Nu s-au actualizat: '.implode(', ', $failed).'. '.implode(' ', $skin->get_upgrade_messages())] : [true, count($available).' plugin(uri) actualizat(e).'];
        }
        if ($action === 'update_theme') {
            if (! wp_get_theme($target)->exists()) {
                return [false, 'Tema nu există pe site.'];
            }
            wp_update_themes();
            $result = (new Theme_Upgrader($skin))->upgrade($target);

            return $result && ! is_wp_error($result) ? [true, 'Tema a fost actualizată.'] : [false, 'Tema nu s-a actualizat. '.implode(' ', $skin->get_upgrade_messages())];
        }
        if ($action === 'update_core' || $action === 'reinstall_core') {
            wp_version_check([], true);
            $chosen = null;
            foreach ((array) get_core_updates(['dismissed' => true]) as $u) {
                if ($action === 'update_core' && isset($u->response) && $u->response === 'upgrade') {
                    $chosen = $u;
                    break;
                }
                if ($action === 'reinstall_core' && isset($u->current) && $u->current === $wp_version) {
                    $chosen = $u;
                    $chosen->response = 'reinstall';
                    break;
                }
            }
            if (! $chosen) {
                return [$action === 'update_core', $action === 'update_core' ? 'WordPress este deja la zi.' : 'Pachetul versiunii curente nu e disponibil.'];
            }
            self::$reinstalling = $action === 'reinstall_core';
            $result = (new Core_Upgrader($skin))->upgrade($chosen);
            self::$reinstalling = false;

            return is_wp_error($result) || ! $result ? [false, 'WordPress nu s-a actualizat: '.(is_wp_error($result) ? $result->get_error_message() : implode(' ', $skin->get_upgrade_messages()))] : [true, $action === 'update_core' ? 'WordPress actualizat la '.$result.'.' : 'Fișierele WordPress au fost reinstalate.'];
        }

        return [false, 'Acțiune necunoscută.'];
    }

    // ---------- actualizarea pluginului din panou ----------

    public static function selfUpdate($transient)
    {
        if (! is_object($transient) || ! self::connected()) {
            return $transient;
        }
        $info = get_transient('vitim_connector_latest');
        if ($info === false) {
            $s = self::settings();
            $response = wp_remote_get(rtrim($s['url'], '/').'/connector/v1/plugin', ['timeout' => 10]);
            $info = is_wp_error($response) ? [] : (array) json_decode(wp_remote_retrieve_body($response), true);
            set_transient('vitim_connector_latest', $info, 6 * HOUR_IN_SECONDS);
        }
        $file = plugin_basename(__FILE__);
        if (! empty($info['version']) && ! empty($info['download_url']) && version_compare($info['version'], VITIM_CONNECTOR_VERSION, '>')
            && strpos($info['download_url'], rtrim(self::settings()['url'], '/').'/') === 0) {
            $transient->response[$file] = (object) [
                'slug' => 'vitim-connector', 'plugin' => $file, 'new_version' => $info['version'],
                'package' => $info['download_url'], 'url' => 'https://vitim.ro',
            ];
        }

        return $transient;
    }
}


register_activation_hook(__FILE__, ['Vitim_Connector', 'activate']);
register_deactivation_hook(__FILE__, ['Vitim_Connector', 'deactivate']);
Vitim_Connector::boot();
