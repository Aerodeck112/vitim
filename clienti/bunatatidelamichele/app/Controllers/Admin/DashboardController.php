<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\BtIpay;
use App\Core\DB;
use App\Core\Settings;

final class DashboardController extends AdminController
{
    protected string $area = 'dashboard';

    /** Comenzi „valide” pentru statistici: fără anulate, returnate sau neplătite cu cardul. */
    public const VALID = "status NOT IN ('anulata','returnata','asteptare_plata')";

    public function index(): string
    {
        $v = self::VALID;
        $since = fn(string $rel) => gmdate('Y-m-d H:i:s', strtotime($rel));
        $today = gmdate('Y-m-d H:i:s', strtotime('today'));
        $sum = fn(string $from) => (float)DB::val("SELECT COALESCE(SUM(total),0) FROM orders WHERE $v AND created_at >= ?", [$from]);
        $cnt = fn(string $from) => (int)DB::val("SELECT COUNT(*) FROM orders WHERE $v AND created_at >= ?", [$from]);
        $kpi = [
            'today' => $sum($today), 'todayN' => $cnt($today),
            'd7' => $sum($since('-7 days')), 'd7N' => $cnt($since('-7 days')),
            'd30' => $sum($since('-30 days')), 'd30N' => $cnt($since('-30 days')),
            'prev30' => (float)DB::val("SELECT COALESCE(SUM(total),0) FROM orders WHERE $v AND created_at >= ? AND created_at < ?", [$since('-60 days'), $since('-30 days')]),
            'todo' => (int)DB::val("SELECT COUNT(*) FROM orders WHERE status IN ('noua','procesare')"),
            'waiting' => (int)DB::val("SELECT COUNT(*) FROM orders WHERE status = 'asteptare_plata'"),
            'toCapture' => (int)DB::val("SELECT COUNT(*) FROM orders WHERE payment_status = 'autorizata'"),
            'customers' => (int)DB::val("SELECT COUNT(DISTINCT LOWER(email)) FROM orders WHERE $v"),
        ];
        $kpi['aov'] = $kpi['d30N'] ? $kpi['d30'] / $kpi['d30N'] : 0;
        $days = [];
        for ($i = 29; $i >= 0; $i--) {
            $days[date('Y-m-d', strtotime("-$i days"))] = 0.0;
        }
        foreach (DB::all("SELECT created_at, total FROM orders WHERE $v AND created_at >= ?", [$since('-30 days')]) as $r) {
            $k = date('Y-m-d', (int)strtotime($r['created_at'] . ' UTC'));
            if (isset($days[$k])) {
                $days[$k] += (float)$r['total'];
            }
        }
        $top = DB::all("SELECT i.name, SUM(i.qty) AS q, SUM(i.total) AS t FROM order_items i JOIN orders o ON o.id = i.order_id WHERE o.$v AND o.created_at >= ? GROUP BY i.name ORDER BY t DESC LIMIT 6", [$since('-30 days')]);
        $recent = DB::all('SELECT * FROM orders ORDER BY id DESC LIMIT 8');
        $low = DB::all('SELECT id, name, stock FROM products WHERE published = 1 AND manage_stock = 1 AND stock <= ? ORDER BY stock LIMIT 8', [(int)Settings::get('low_stock_threshold', '5')]);
        $payMix = DB::all("SELECT payment_method AS m, COUNT(*) AS n FROM orders WHERE $v AND created_at >= ? GROUP BY payment_method", [$since('-30 days')]);

        $u = Auth::user();
        $s = fn($k) => (string)Settings::get($k);
        $setup = [
            ['Date firmă complete (CUI, Reg. Com., adresă, telefon) – obligatorii pentru BT iPay și ANPC', $s('company_cui') !== '' && $s('company_reg') !== '' && $s('company_address') !== '' && $s('phone') !== '', '/admin/setari/firma'],
            ['BT iPay configurat (utilizator și parolă API)', BtIpay::configured(), '/admin/setari/plati'],
            ['BT iPay în modul PRODUCȚIE (după aprobarea băncii)', BtIpay::mode() === 'live' && BtIpay::configured(), '/admin/setari/plati'],
            ['Email SMTP configurat și testat (confirmările de comandă)', $s('smtp_host') !== '' && $s('smtp_tested') === '1', '/admin/setari/email'],
            ['Cron configurat în cPanel (anulează plățile abandonate)', $s('cron_last_run') !== '' && strtotime($s('cron_last_run') . ' UTC') > time() - 86400, '/admin/sistem'],
            ['Autentificare în doi pași activată', !empty($u['totp_secret']), '/admin/cont/2fa'],
            ['Google Search Console verificat', $s('seo_google_verification') !== '', '/admin/seo'],
            ['Google Analytics 4 / Tag Manager', $s('ga4_id') !== '' || $s('gtm_id') !== '', '/admin/setari/integrari'],
        ];
        return $this->render('dashboard', compact('kpi', 'days', 'top', 'recent', 'low', 'setup', 'payMix') + ['title' => 'Tablou de bord']);
    }
}
