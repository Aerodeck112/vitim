<?php
/**
 * Backup-ul site-ului pe hostingul clientului: baza de date (tabelele WordPress) + wp-content, wp-config.php, .htaccess.
 * Se păstrează în afara public_html când se poate; altfel într-un folder protejat din wp-content.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class Vitim_Connector_Backup
{
    const OPTION = 'vitim_connector_backup';
    const HOOK = 'vitim_connector_backup_run';

    /** Foldere din wp-content care nu intră în backup (cache, copii ale altor pluginuri de backup). */
    const SKIP = ['cache', 'upgrade', 'upgrade-temp-backup', 'et-cache', 'w3tc-config', 'updraft', 'ai1wm-backups', 'backups-dup-lite',
        'backup-db', 'wpvividbackups', 'backwpup', 'uploads/backwpup', 'node_modules', 'litespeed', 'wflogs'];

    /** @return array{schedule: string, keep: int, last?: array} */
    public static function settings()
    {
        $s = get_option(self::OPTION, []);
        $s = is_array($s) ? $s : [];

        return $s + ['schedule' => 'daily', 'keep' => 7];
    }

    public static function save(array $values)
    {
        $s = self::settings();
        $s['schedule'] = in_array($values['schedule'] ?? '', ['daily', 'weekly', 'off'], true) ? $values['schedule'] : 'daily';
        $s['keep'] = max(1, min(30, (int) ($values['keep'] ?? 7)));
        update_option(self::OPTION, $s, false);
        self::schedule();
    }

    public static function schedule()
    {
        $s = self::settings();
        wp_clear_scheduled_hook(self::HOOK);
        if ($s['schedule'] !== 'off') {
            // noaptea (ora 3, ora site-ului), ca să nu încarce serverul în timpul zilei
            $next = strtotime('tomorrow 03:'.str_pad((string) wp_rand(0, 59), 2, '0', STR_PAD_LEFT), current_time('timestamp')) - (int) (get_option('gmt_offset') * HOUR_IN_SECONDS);
            wp_schedule_event($next, $s['schedule'] === 'weekly' ? 'weekly' : 'daily', self::HOOK);
        }
    }

    /** Folderul de backup-uri: în afara public_html dacă se poate. @return array{0: string, 1: bool} [cale, protejat în site] */
    public static function directory()
    {
        $host = preg_replace('/[^a-z0-9.-]/', '', strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST)));
        // folderul principal al contului: deasupra lui public_html (și pentru WordPress instalat într-un subfolder)
        $abs = realpath(ABSPATH) ?: untrailingslashit(ABSPATH);
        $base = preg_match('#^(.*?)/(public_html|www|htdocs|httpdocs)(/|$)#', $abs, $m) ? $m[1] : dirname($abs);
        $outside = $base.'/vitim-backups/'.$host;
        if ((is_dir($outside) || @wp_mkdir_p($outside)) && is_writable($outside) && strpos(realpath($outside), realpath(ABSPATH)) !== 0) {
            return [$outside, false];
        }
        $key = get_option('vitim_connector_backup_key');
        if (! $key) {
            $key = wp_generate_password(20, false, false);
            update_option('vitim_connector_backup_key', $key, false);
        }
        $inside = WP_CONTENT_DIR.'/vitim-backups-'.$key;
        wp_mkdir_p($inside);
        @file_put_contents($inside.'/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
        @file_put_contents($inside.'/index.php', '<?php // liniște');

        return [$inside, true];
    }

    /** Face backup-ul, îl verifică, păstrează ultimele N copii. @return array raportul trimis la panou */
    public static function run()
    {
        @set_time_limit(0);
        @ignore_user_abort(true);
        $started = gmdate('c');
        $report = ['status' => 'failed', 'verified' => false, 'started_at' => $started];
        try {
            list($root) = self::directory();
            $dir = $root.'/'.gmdate('Ymd-His');
            if (! wp_mkdir_p($dir)) {
                throw new RuntimeException('Nu pot crea folderul de backup: '.$dir);
            }
            $db = $dir.'/baza-de-date.sql.gz';
            self::dumpDatabase($db);
            $zip = $dir.'/fisiere.zip';
            $count = self::zipFiles($zip, $root);
            $verified = self::verify($db, $zip);
            file_put_contents($dir.'/info.json', wp_json_encode(['site' => home_url('/'), 'created_at' => $started, 'wordpress' => get_bloginfo('version'), 'files' => $count]));
            $kept = self::prune($root, (int) self::settings()['keep']);
            $report = [
                'status' => 'ok', 'verified' => $verified, 'started_at' => $started, 'finished_at' => gmdate('c'),
                'db_bytes' => (int) filesize($db), 'files_bytes' => (int) filesize($zip), 'files_count' => $count,
                'location' => $dir, 'kept' => $kept, 'error' => $verified ? null : 'Arhiva nu a trecut verificarea.',
            ];
        } catch (Throwable $e) {
            $report['finished_at'] = gmdate('c');
            $report['error'] = substr($e->getMessage(), 0, 1000);
        }
        $s = self::settings();
        $s['last'] = $report;
        update_option(self::OPTION, $s, false);

        return $report;
    }

    private static function dumpDatabase($file)
    {
        global $wpdb;
        $gz = gzopen($file, 'wb6');
        if (! $gz) {
            throw new RuntimeException('Nu pot scrie '.$file);
        }
        gzwrite($gz, "-- VITIM backup ".gmdate('c')."\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
        $tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix).'%'));
        foreach ($tables as $table) {
            $create = $wpdb->get_row('SHOW CREATE TABLE `'.esc_sql($table).'`', ARRAY_N);
            gzwrite($gz, "\nDROP TABLE IF EXISTS `{$table}`;\n".$create[1].";\n");
            $offset = 0;
            do {
                $rows = $wpdb->get_results('SELECT * FROM `'.esc_sql($table).'` LIMIT '.$offset.', 500', ARRAY_N);
                if ($rows) {
                    $values = [];
                    foreach ($rows as $row) {
                        $values[] = '('.implode(',', array_map(function ($v) use ($wpdb) {
                            return $v === null ? 'NULL' : "'".$wpdb->_real_escape($v)."'";
                        }, $row)).')';
                    }
                    gzwrite($gz, "INSERT INTO `{$table}` VALUES ".implode(",\n", $values).";\n");
                }
                $offset += 500;
            } while (count($rows) === 500);
        }
        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n-- VITIM backup complet\n");
        gzclose($gz);
    }

    private static function zipFiles($file, $backupRoot)
    {
        if (! class_exists('ZipArchive')) {
            throw new RuntimeException('Extensia PHP zip lipsește (cPanel → Select PHP Version → zip).');
        }
        $zip = new ZipArchive();
        if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Nu pot crea arhiva '.$file);
        }
        $count = 0;
        foreach (['wp-config.php', '.htaccess'] as $f) {
            if (is_file(ABSPATH.$f)) {
                $zip->addFile(ABSPATH.$f, $f);
                $count++;
            }
        }
        $content = realpath(WP_CONTENT_DIR);
        $skip = array_map(function ($d) use ($content) { return $content.'/'.$d; }, self::SKIP);
        $skip[] = realpath($backupRoot) ?: $backupRoot;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($content, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $path => $info) {
            foreach ($skip as $s) {
                if (strpos($path, $s) === 0 || strpos($path, '/vitim-backups-') !== false) {
                    continue 2;
                }
            }
            if ($info->isFile() && $info->isReadable() && $info->getSize() < 300 * 1048576) {
                $zip->addFile($path, 'wp-content/'.substr($path, strlen($content) + 1));
                $count++;
            }
        }
        if (! $zip->close()) {
            throw new RuntimeException('Arhiva fișierelor nu a putut fi finalizată (spațiu pe disc?).');
        }

        return $count;
    }

    /** Arhivele se pot citi cap-coadă. */
    private static function verify($db, $zip)
    {
        $gz = gzopen($db, 'rb');
        if (! $gz) {
            return false;
        }
        $tail = '';
        while (! gzeof($gz)) {
            $tail = substr($tail.gzread($gz, 65536), -200);
        }
        gzclose($gz);
        if (strpos($tail, '-- VITIM backup complet') === false) {
            return false;
        }
        $z = new ZipArchive();
        $ok = $z->open($zip, ZipArchive::CHECKCONS) === true && $z->numFiles > 0;
        if ($ok) {
            $z->close();
        }

        return $ok;
    }

    private static function prune($root, $keep)
    {
        $dirs = glob($root.'/[0-9]*-[0-9]*', GLOB_ONLYDIR) ?: [];
        rsort($dirs);
        foreach (array_slice($dirs, $keep) as $old) {
            foreach (glob($old.'/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($old);
        }

        return min(count($dirs), $keep);
    }
}
