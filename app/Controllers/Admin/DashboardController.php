<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Crm;
use App\Core\DB;
use App\Core\Settings;

final class DashboardController extends AdminController
{
    protected string $area = 'dashboard';

    public function index(): string
    {
        $d30 = gmdate('Y-m-d H:i:s', strtotime('-30 days'));
        $d7 = gmdate('Y-m-d H:i:s', strtotime('-7 days'));
        $monthStart = gmdate('Y-m-01 00:00:00');
        $kpi = [
            'leads7' => (int)DB::val("SELECT COUNT(*) FROM deals WHERE created_at >= ? AND stage <> 'pierdut'", [$d7]),
            'leads30' => (int)DB::val("SELECT COUNT(*) FROM deals WHERE created_at >= ? AND stage <> 'pierdut'", [$d30]),
            'open' => (int)DB::val("SELECT COUNT(*) FROM deals WHERE stage NOT IN ('castigat','pierdut')"),
            'openValue' => (float)DB::val("SELECT COALESCE(SUM(value),0) FROM deals WHERE stage NOT IN ('castigat','pierdut')"),
            'wonMonth' => (float)DB::val("SELECT COALESCE(SUM(value),0) FROM deals WHERE stage = 'castigat' AND closed_at >= ?", [$monthStart]),
            'wonCount' => (int)DB::val("SELECT COUNT(*) FROM deals WHERE stage = 'castigat' AND closed_at >= ?", [$monthStart]),
            'subs' => (int)DB::val("SELECT COUNT(*) FROM contacts WHERE newsletter = 'subscribed'"),
            'contacts' => (int)DB::val('SELECT COUNT(*) FROM contacts'),
            'clients' => (int)DB::val("SELECT COUNT(*) FROM contacts WHERE status = 'client'"),
        ];
        // lead-uri pe zi, ultimele 30 de zile
        $rows = DB::all("SELECT created_at FROM deals WHERE created_at >= ? AND stage <> 'pierdut'", [$d30]);
        $days = [];
        for ($i = 29; $i >= 0; $i--) {
            $days[date('Y-m-d', strtotime("-$i days"))] = 0;
        }
        foreach ($rows as $r) {
            $k = date('Y-m-d', (int)strtotime($r['created_at'] . ' UTC'));
            if (isset($days[$k])) {
                $days[$k]++;
            }
        }
        $sources = DB::all("SELECT COALESCE(source,'direct') AS s, COUNT(*) AS n FROM deals WHERE created_at >= ? GROUP BY COALESCE(source,'direct') ORDER BY n DESC", [$d30]);
        $services = DB::all("SELECT COALESCE(service,'General') AS s, COUNT(*) AS n FROM deals WHERE created_at >= ? GROUP BY COALESCE(service,'General') ORDER BY n DESC LIMIT 6", [$d30]);
        $recent = DB::all('SELECT d.*, c.name AS cname, c.company, c.phone FROM deals d JOIN contacts c ON c.id = d.contact_id ORDER BY d.id DESC LIMIT 8');
        $tasks = DB::all("SELECT a.*, c.name AS cname FROM activities a LEFT JOIN contacts c ON c.id = a.contact_id WHERE a.type = 'task' AND a.done = 0 ORDER BY CASE WHEN a.due_at IS NULL THEN 1 ELSE 0 END, a.due_at LIMIT 6");

        $u = Auth::user();
        $setup = [
            ['Date firmă (CUI, Reg. Com., adresă)', Settings::get('company_cui') !== '' && Settings::get('company_address') !== '', '/admin/setari/firma'],
            ['Email SMTP configurat și testat', Settings::get('smtp_host') !== '' && Settings::get('smtp_tested') === '1', '/admin/setari/email'],
            ['Autentificare în doi pași activată', !empty($u['totp_secret']), '/admin/cont/2fa'],
            ['Cron configurat în cPanel', Settings::get('cron_last_run') !== '' && strtotime(Settings::get('cron_last_run') . ' UTC') > time() - 86400, '/admin/sistem'],
            ['Google Analytics 4 / Tag Manager', Settings::get('ga4_id') !== '' || Settings::get('gtm_id') !== '', '/admin/setari/integrari'],
            ['Google Search Console verificat', Settings::get('seo_google_verification') !== '', '/admin/seo'],
            ['Logo încărcat', Settings::get('logo') !== '', '/admin/setari/aspect'],
            ['Primul testimonial adăugat', (int)DB::val('SELECT COUNT(*) FROM testimonials') > 0, '/admin/c/testimoniale'],
        ];
        return $this->render('dashboard', compact('kpi', 'days', 'sources', 'services', 'recent', 'tasks', 'setup') + ['title' => 'Tablou de bord', 'stages' => Crm::stages()]);
    }
}
