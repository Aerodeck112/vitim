<?php
/**
 * Plugin Name:       VITIM Connector
 * Description:       Conectează site-ul la panoul VITIM: starea site-ului (versiuni, actualizări în așteptare) și jurnalul automat al actualizărilor.
 * Version:           1.0.0
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

define('VITIM_CONNECTOR_VERSION', '1.0.0');

final class Vitim_Connector
{
    const OPTION = 'vitim_connector';
    const QUEUE = 'vitim_connector_queue';
    const CRON = 'vitim_connector_heartbeat';
    const FLUSH = 'vitim_connector_flush';

    public static function boot()
    {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_vitim_connector_save', [__CLASS__, 'save']);
        add_action('admin_post_vitim_connector_send', [__CLASS__, 'sendNow']);
        add_action('admin_post_vitim_connector_disconnect', [__CLASS__, 'disconnect']);
        add_action(self::CRON, [__CLASS__, 'run']);
        add_action(self::FLUSH, [__CLASS__, 'flush']);
        add_action('upgrader_process_complete', [__CLASS__, 'onUpgrade'], 10, 2);
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
    }

    public static function deactivate()
    {
        wp_clear_scheduled_hook(self::CRON);
        wp_clear_scheduled_hook(self::FLUSH);
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
            echo '</table>';
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
        $result = self::post('heartbeat', self::health());
        self::remember($result);
        if ($result['ok']) {
            self::flush();
        }
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
        ];
    }

    /** @return array{ok: bool, message: string} */
    private static function post($endpoint, array $payload)
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
        if (! self::connected() || empty($extra['action']) || $extra['action'] !== 'update' || empty($extra['type'])) {
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

    private static function entry($ref, $title)
    {
        return ['ref' => substr($ref, 0, 64), 'category' => 'updates', 'title' => substr($title, 0, 190), 'performed_at' => gmdate('c')];
    }
}

register_activation_hook(__FILE__, ['Vitim_Connector', 'activate']);
register_deactivation_hook(__FILE__, ['Vitim_Connector', 'deactivate']);
Vitim_Connector::boot();
