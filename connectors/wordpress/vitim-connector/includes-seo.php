<?php
/**
 * Remedieri SEO la buton (cerute din panoul VITIM). Fiecare remediere e un modul pornit într-o opțiune:
 * efectul vine din hook-uri WordPress, deci oprirea modulului (sau a pluginului) anulează schimbarea.
 * Excepții, cu copie de siguranță: robots.txt fizic (redenumit), textele alternative completate în bibliotecă,
 * blocul de compresie din .htaccess (între marcaje VITIM).
 */
if (! defined('ABSPATH')) {
    exit;
}

final class Vitim_Connector_Seo
{
    const OPTION = 'vitim_connector_seo';
    const DATA = 'vitim_connector_seo_data';

    /** Remedierile permise și ce scrie în panou / jurnal. */
    const FIXES = [
        'meta' => 'Descrieri, canonical și Open Graph',
        'title' => 'Titlul paginilor generat de WordPress',
        'robots' => 'robots.txt corect',
        'sitemap' => 'Sitemap pornit',
        'https' => 'Redirecționare spre HTTPS',
        'www' => 'O singură variantă de domeniu (www / fără www)',
        'lang' => 'Limba paginii (lang)',
        'viewport' => 'Afișare corectă pe telefon (viewport)',
        'alt' => 'Text alternativ pentru imagini',
        'schema' => 'Date structurate despre firmă',
        'headers' => 'Antete de securitate',
        'gzip' => 'Compresie (gzip)',
    ];

    public static function boot()
    {
        $on = self::modules();
        if (! $on) {
            return;
        }
        if (isset($on['meta']) || isset($on['schema'])) {
            add_action('wp_head', [__CLASS__, 'head'], 1);
        }
        if (isset($on['title'])) {
            add_action('after_setup_theme', function () {
                add_theme_support('title-tag');
            }, 99);
        }
        if (isset($on['robots'])) {
            add_filter('robots_txt', [__CLASS__, 'robots'], 99, 2);
        }
        if (isset($on['sitemap'])) {
            add_filter('wp_sitemaps_enabled', '__return_true', 99);
        }
        if (isset($on['https']) || isset($on['www'])) {
            add_action('template_redirect', [__CLASS__, 'redirect'], 0);
        }
        if (isset($on['lang']) || isset($on['viewport'])) {
            add_action('template_redirect', function () {
                ob_start([__CLASS__, 'buffer']);
            }, 1);
        }
        if (isset($on['alt'])) {
            add_filter('wp_get_attachment_image_attributes', [__CLASS__, 'imageAlt'], 20, 2);
            add_filter('the_content', [__CLASS__, 'contentAlt'], 20);
        }
        if (isset($on['headers'])) {
            add_action('send_headers', [__CLASS__, 'headers']);
        }
    }

    /** @return array<string, bool> */
    public static function modules()
    {
        $m = get_option(self::OPTION, []);

        return is_array($m) ? $m : [];
    }

    /** Pluginul SEO activ (Yoast, Rank Math...), care pune deja descrierile și Open Graph. */
    public static function seoPlugin()
    {
        foreach (['WPSEO_VERSION' => 'Yoast SEO', 'RANK_MATH_VERSION' => 'Rank Math', 'AIOSEO_VERSION' => 'All in One SEO', 'SEOPRESS_VERSION' => 'SEOPress', 'THE_SEO_FRAMEWORK_VERSION' => 'The SEO Framework'] as $const => $name) {
            if (defined($const)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Aplică remedierile cerute („meta.robots.alt”). @param array $data datele firmei trimise de panou (pentru schema)
     * @return array{0: bool, 1: string, 2: array} [reușit, mesaj, remedierile aplicate]
     */
    public static function apply($targets, $data = [])
    {
        $wanted = array_values(array_unique(array_filter(explode('.', (string) $targets))));
        if (! $wanted || array_diff($wanted, array_keys(self::FIXES))) {
            return [false, 'Remediere SEO necunoscută.', []];
        }
        $modules = self::modules();
        $done = [];
        $applied = [];
        $notes = [];
        foreach ($wanted as $fix) {
            list($ok, $note) = self::one($fix, $modules, is_array($data) ? $data : []);
            if ($ok) {
                $modules[$fix] = true;
                $done[] = self::FIXES[$fix];
                $applied[] = $fix;
            }
            if ($note) {
                $notes[] = $note;
            }
        }
        update_option(self::OPTION, $modules, false);
        if (array_intersect($wanted, ['robots', 'sitemap'])) {
            flush_rewrite_rules(false);
        }
        $message = $done ? 'Rezolvat: '.implode(', ', $done).'.' : 'Nimic de aplicat.';

        return [(bool) $done, trim($message.' '.implode(' ', $notes)), $applied];
    }

    /** @return array{0: bool, 1: ?string} */
    private static function one($fix, $modules, $data)
    {
        switch ($fix) {
            case 'meta':
                $plugin = self::seoPlugin();

                return $plugin
                    ? [false, "Site-ul folosește {$plugin}: descrierile și Open Graph se completează din {$plugin} (nu le dublez)."]
                    : [true, null];
            case 'robots':
                $file = ABSPATH.'robots.txt';
                if (file_exists($file)) {
                    // fișierul fizic are prioritate față de cel generat de WordPress: îl păstrăm ca copie
                    $backup = ABSPATH.'robots.txt.vitim-'.gmdate('Ymd-His').'.bak';
                    if (! @rename($file, $backup)) {
                        return [false, 'Nu am putut redenumi robots.txt (permisiuni).'];
                    }

                    return [true, 'Vechiul robots.txt e păstrat ca '.basename($backup).'.'];
                }

                return [true, null];
            case 'https':
                $host = (string) parse_url(home_url('/'), PHP_URL_HOST);
                $test = wp_remote_get('https://'.$host.'/', ['timeout' => 15, 'redirection' => 3, 'sslverify' => true]);
                if (is_wp_error($test) || (int) wp_remote_retrieve_response_code($test) >= 400) {
                    return [false, 'HTTPS nu funcționează încă pe '.$host.' (certificat lipsă sau invalid): activează întâi AutoSSL din cPanel.'];
                }
                foreach (['home', 'siteurl'] as $opt) {
                    $url = (string) get_option($opt);
                    if (strpos($url, 'http://') === 0) {
                        update_option($opt, 'https://'.substr($url, 7));
                    }
                }

                return [true, null];
            case 'alt':
                $filled = self::fillAltTexts();

                return [true, $filled ? "Am completat textul alternativ la {$filled} imagini din bibliotecă." : null];
            case 'schema':
                update_option(self::DATA, self::cleanData($data), false);

                return [true, null];
            case 'gzip':
                if (! function_exists('insert_with_markers')) {
                    require_once ABSPATH.'wp-admin/includes/misc.php';
                }
                $file = ABSPATH.'.htaccess';
                if (file_exists($file) && ! is_writable($file)) {
                    return [false, '.htaccess nu poate fi scris (permisiuni). Compresia se pornește din cPanel → Optimize Website.'];
                }
                if (file_exists($file)) {
                    @copy($file, ABSPATH.'.htaccess.vitim-'.gmdate('Ymd-His').'.bak');
                }
                $ok = insert_with_markers($file, 'VITIM Compresie', [
                    '<IfModule mod_deflate.c>',
                    'AddOutputFilterByType DEFLATE text/html text/plain text/css text/xml application/xml application/javascript application/json image/svg+xml',
                    '</IfModule>',
                ]);

                return $ok ? [true, null] : [false, 'Nu am putut scrie în .htaccess.'];
        }

        return [true, null]; // title, sitemap, www, lang, viewport, headers: doar pornesc modulul
    }

    /** Oprește toate remedierile (din pagina pluginului). */
    public static function reset()
    {
        delete_option(self::OPTION);
        if (! function_exists('insert_with_markers')) {
            require_once ABSPATH.'wp-admin/includes/misc.php';
        }
        if (file_exists(ABSPATH.'.htaccess') && strpos((string) file_get_contents(ABSPATH.'.htaccess'), 'VITIM Compresie') !== false) {
            insert_with_markers(ABSPATH.'.htaccess', 'VITIM Compresie', []);
        }
        flush_rewrite_rules(false);
    }

    // ---------- efectele pe site ----------

    public static function head()
    {
        $on = self::modules();
        if (isset($on['meta']) && ! self::seoPlugin()) {
            $title = wp_get_document_title();
            $description = self::description();
            $url = self::currentUrl();
            if ($description !== '') {
                echo '<meta name="description" content="'.esc_attr($description).'">'."\n";
            }
            if (! is_singular()) {
                // WordPress pune canonical doar pe articole / pagini
                echo '<link rel="canonical" href="'.esc_url($url).'">'."\n";
            }
            echo '<meta property="og:locale" content="'.esc_attr(str_replace('-', '_', get_bloginfo('language'))).'">'."\n";
            echo '<meta property="og:type" content="'.(is_singular('post') ? 'article' : 'website').'">'."\n";
            echo '<meta property="og:title" content="'.esc_attr($title).'">'."\n";
            if ($description !== '') {
                echo '<meta property="og:description" content="'.esc_attr($description).'">'."\n";
            }
            echo '<meta property="og:url" content="'.esc_url($url).'">'."\n";
            echo '<meta property="og:site_name" content="'.esc_attr(get_bloginfo('name')).'">'."\n";
            $image = self::image();
            if ($image) {
                echo '<meta property="og:image" content="'.esc_url($image).'">'."\n";
            }
            echo '<meta name="twitter:card" content="'.($image ? 'summary_large_image' : 'summary').'">'."\n";
        }
        if (isset($on['schema']) && (is_front_page() || is_home())) {
            echo '<script type="application/ld+json">'.wp_json_encode(self::schema(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).'</script>'."\n";
        }
    }

    private static function description()
    {
        if (is_singular()) {
            $post = get_queried_object();
            $text = has_excerpt($post) ? get_the_excerpt($post) : strip_shortcodes((string) $post->post_content);
        } elseif (is_category() || is_tag() || is_tax()) {
            $text = term_description();
        } else {
            $text = get_bloginfo('description') ?: get_bloginfo('name');
        }
        $text = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags((string) $text)));
        if (mb_strlen($text) > 155) {
            $text = rtrim(mb_substr($text, 0, 152), " ,.;:-").'…';
        }

        return $text;
    }

    private static function currentUrl()
    {
        if (is_singular()) {
            return (string) get_permalink();
        }
        if (is_front_page() || is_home()) {
            return home_url('/');
        }
        if (is_category() || is_tag() || is_tax()) {
            $link = get_term_link(get_queried_object());

            return is_wp_error($link) ? home_url('/') : $link;
        }

        return home_url(add_query_arg([], strtok((string) $_SERVER['REQUEST_URI'], '?')));
    }

    private static function image()
    {
        if (is_singular() && has_post_thumbnail()) {
            return (string) get_the_post_thumbnail_url(null, 'large');
        }
        $logo = get_theme_mod('custom_logo');
        if ($logo) {
            return (string) wp_get_attachment_image_url($logo, 'full');
        }

        return (string) get_site_icon_url(512);
    }

    private static function schema()
    {
        $d = get_option(self::DATA, []);
        $d = is_array($d) ? $d : [];
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => ! empty($d['address']) ? 'LocalBusiness' : 'Organization',
            'name' => ! empty($d['name']) ? $d['name'] : get_bloginfo('name'),
            'url' => home_url('/'),
        ];
        $logo = get_theme_mod('custom_logo') ? wp_get_attachment_image_url(get_theme_mod('custom_logo'), 'full') : get_site_icon_url(512);
        if ($logo) {
            $schema['logo'] = $logo;
            $schema['image'] = $logo;
        }
        foreach (['phone' => 'telephone', 'email' => 'email'] as $key => $prop) {
            if (! empty($d[$key])) {
                $schema[$prop] = $d[$key];
            }
        }
        if (! empty($d['address'])) {
            $schema['address'] = array_filter(['@type' => 'PostalAddress', 'streetAddress' => $d['address'], 'addressLocality' => isset($d['city']) ? $d['city'] : null, 'addressCountry' => 'RO']);
        }

        return $schema;
    }

    private static function cleanData($data)
    {
        $out = [];
        foreach (['name' => 160, 'phone' => 40, 'email' => 160, 'address' => 255, 'city' => 80] as $key => $max) {
            if (isset($data[$key]) && is_string($data[$key]) && trim($data[$key]) !== '') {
                $out[$key] = substr(sanitize_text_field($data[$key]), 0, $max);
            }
        }

        return $out;
    }

    public static function robots($output, $public)
    {
        if (! $public) {
            return $output; // site-ul e setat să nu fie indexat: decizia rămâne la „Permite indexarea”
        }
        $sitemap = defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') ? home_url('/sitemap_index.xml') : home_url('/wp-sitemap.xml');

        return "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n\nSitemap: {$sitemap}\n";
    }

    public static function redirect()
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST) || (defined('WP_CLI') && WP_CLI)) {
            return;
        }
        $on = self::modules();
        $home = wp_parse_url(home_url('/'));
        $host = strtolower((string) (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ''));
        $https = is_ssl()
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (isset($_SERVER['HTTP_CF_VISITOR']) && strpos((string) $_SERVER['HTTP_CF_VISITOR'], 'https') !== false);
        $bare = function ($h) {
            return preg_replace('/^www\./', '', strtolower((string) $h));
        };
        $wantHost = isset($on['www']) && $host !== '' && $bare($host) === $bare($home['host']) && $host !== strtolower($home['host'])
            ? strtolower($home['host']) : $host;
        $wantHttps = isset($on['https']) && ! $https && $home['scheme'] === 'https';
        if ($wantHost !== $host || $wantHttps) {
            $scheme = ($wantHttps || $https) ? 'https' : 'http';
            wp_safe_redirect($scheme.'://'.$wantHost.(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/'), 301, 'VITIM');
            exit;
        }
    }

    /** Completează în HTML ce lipsește din temă: lang pe <html>, viewport în <head>. */
    public static function buffer($html)
    {
        $on = self::modules();
        if (isset($on['lang']) && preg_match('/<html(?![^>]*\slang=)([^>]*)>/i', $html)) {
            $html = preg_replace('/<html(?![^>]*\slang=)([^>]*)>/i', '<html lang="'.esc_attr(get_bloginfo('language') ?: 'ro-RO').'"$1>', $html, 1);
        }
        if (isset($on['viewport']) && stripos($html, 'name="viewport"') === false && stripos($html, "name='viewport'") === false) {
            $html = preg_replace('/<\/head>/i', '<meta name="viewport" content="width=device-width, initial-scale=1">'."\n</head>", $html, 1);
        }

        return $html;
    }

    /** Imaginile din bibliotecă fără text alternativ primesc titlul imaginii (doar unde lipsește). @return int */
    private static function fillAltTexts()
    {
        $ids = get_posts(['post_type' => 'attachment', 'post_mime_type' => 'image', 'post_status' => 'inherit', 'numberposts' => 2000, 'fields' => 'ids',
            'meta_query' => ['relation' => 'OR', ['key' => '_wp_attachment_image_alt', 'compare' => 'NOT EXISTS'], ['key' => '_wp_attachment_image_alt', 'value' => '']]]);
        $n = 0;
        foreach ($ids as $id) {
            $alt = self::altFrom(get_the_title($id));
            if ($alt !== '') {
                update_post_meta($id, '_wp_attachment_image_alt', $alt);
                $n++;
            }
        }

        return $n;
    }

    private static function altFrom($name)
    {
        $name = preg_replace('/\.(jpe?g|png|gif|webp|avif|svg)$/i', '', (string) $name);
        $name = trim(preg_replace('/[-_]+|\s+/', ' ', preg_replace('/(-\d+x\d+|-scaled|-e\d{10,})$/', '', $name)));

        return preg_match('/^(img|dsc|image|photo|screenshot)?\s*\d*$/i', $name) ? get_bloginfo('name') : mb_substr($name, 0, 120);
    }

    public static function imageAlt($attr, $attachment)
    {
        if (! isset($attr['alt']) || trim($attr['alt']) === '') {
            $attr['alt'] = self::altFrom(get_the_title($attachment));
        }

        return $attr;
    }

    public static function contentAlt($content)
    {
        return preg_replace_callback('/<img\b(?![^>]*\balt=)([^>]*)>/i', function ($m) {
            $alt = '';
            if (preg_match('/wp-image-(\d+)/', $m[1], $id)) {
                $alt = (string) get_post_meta((int) $id[1], '_wp_attachment_image_alt', true) ?: self::altFrom(get_the_title((int) $id[1]));
            } elseif (preg_match('/src=["\']([^"\']+)["\']/i', $m[1], $src)) {
                $alt = self::altFrom(basename(parse_url($src[1], PHP_URL_PATH)));
            }

            return '<img alt="'.esc_attr($alt).'"'.$m[1].'>';
        }, (string) $content);
    }

    public static function headers()
    {
        $sent = implode("\n", array_map('strtolower', headers_list()));
        foreach (['X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => 'SAMEORIGIN', 'Referrer-Policy' => 'strict-origin-when-cross-origin'] as $name => $value) {
            if (strpos($sent, strtolower($name).':') === false) {
                header($name.': '.$value);
            }
        }
    }
}
