<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Crm;
use App\Core\DB;
use App\Core\Mailer;
use App\Core\Newsletter;
use App\Core\Sanitizer;

final class EmailController extends AdminController
{
    protected string $area = 'email';

    public function index(): string
    {
        $rows = DB::all('SELECT * FROM campaigns ORDER BY id DESC');
        $subs = (int)DB::val("SELECT COUNT(*) FROM contacts WHERE newsletter = 'subscribed'");
        return $this->render('email/index', ['rows' => $rows, 'subs' => $subs, 'title' => 'Campanii email']);
    }

    private function audienceFromPost(): array
    {
        return [
            'segment' => in_array(str_input('segment'), ['subscribers', 'clients', 'all_contacts'], true) ? str_input('segment') : 'subscribers',
            'statuses' => array_values(array_intersect((array)($_POST['statuses'] ?? []), array_keys(Crm::STATUSES))),
            'counties' => array_values(array_filter(array_map(fn($c) => Sanitizer::text((string)$c, 60), (array)($_POST['counties'] ?? [])))),
            'tags' => array_values(array_filter(array_map('trim', explode(',', Sanitizer::text(str_input('tags'), 300))))),
        ];
    }

    public function edit(?string $id = null): string
    {
        $c = $id ? DB::row('SELECT * FROM campaigns WHERE id = ?', [(int)$id]) : null;
        if ($c && !in_array($c['status'], ['draft', 'scheduled'], true)) {
            redirect('/admin/email/' . $c['id'] . '/raport');
        }
        $errors = [];
        if ($this->isPost()) {
            $kind = array_key_exists(str_input('kind'), Newsletter::KINDS) ? str_input('kind') : 'newsletter';
            $data = [
                'name' => Sanitizer::text(str_input('name'), 200) ?: 'Campanie ' . date('d.m.Y'),
                'kind' => $kind,
                'subject' => Sanitizer::text(str_input('subject'), 200),
                'preheader' => Sanitizer::text(str_input('preheader'), 200),
                'body' => Sanitizer::html((string)($_POST['body'] ?? ''), true),
                'audience' => json_encode($this->audienceFromPost(), JSON_UNESCAPED_UNICODE),
                'updated_at' => DB::now(),
            ];
            if ($kind === 'newsletter') {
                $a = json_decode($data['audience'], true);
                $a['segment'] = 'subscribers';
                $data['audience'] = json_encode($a, JSON_UNESCAPED_UNICODE);
            }
            if ($data['subject'] === '') {
                $errors[] = 'Completează subiectul emailului.';
            }
            if (!$errors) {
                if ($c) {
                    DB::update('campaigns', $data, 'id = :id', ['id' => $c['id']]);
                    $cid = (int)$c['id'];
                } else {
                    $data += ['status' => 'draft', 'created_by' => Auth::user()['id'] ?? null, 'created_at' => DB::now()];
                    $cid = DB::insert('campaigns', $data);
                }
                flash('ok', 'Campania a fost salvată.');
                redirect('/admin/email/' . $cid);
            }
            $c = array_merge($c ?? [], $_POST, ['audience' => json_encode($this->audienceFromPost())]);
        }
        if (!$c && isset($_GET['post'])) {
            // campanie pornind de la un articol de blog
            $p = DB::row('SELECT * FROM posts WHERE id = ?', [(int)$_GET['post']]);
            if ($p) {
                $c = ['name' => 'Articol: ' . $p['title'], 'subject' => $p['title'], 'preheader' => $p['excerpt'], 'kind' => 'newsletter',
                    'body' => '<h2>' . e($p['title']) . '</h2><p>Salut {{prenume}},</p><p>' . e($p['excerpt']) . '</p><p><a href="' . e(abs_url('/blog/' . $p['slug'])) . '" style="background:#2f6bff;color:#ffffff;padding:12px 22px;border-radius:999px;text-decoration:none;font-weight:600;display:inline-block">Citește articolul</a></p>'];
            }
        }
        $c ??= ['kind' => 'newsletter', 'body' => '<p>Salut {{prenume}},</p><p></p><p>Cu drag,<br>VITIM</p>'];
        return $this->render('email/edit', [
            'c' => $c, 'errors' => $errors,
            'audience' => json_list($c['audience'] ?? '{}'),
            'counties' => array_column(DB::all("SELECT DISTINCT county FROM contacts WHERE county IS NOT NULL AND county <> '' ORDER BY county"), 'county'),
            'posts' => DB::all("SELECT id, title FROM posts WHERE status = 'published' ORDER BY published_at DESC LIMIT 10"),
            'title' => !empty($c['id']) ? 'Editează campania' : 'Campanie nouă',
        ]);
    }

    public function audienceCount(): never
    {
        $kind = array_key_exists(str_input('kind'), Newsletter::KINDS) ? str_input('kind') : 'newsletter';
        $a = $this->audienceFromPost();
        if ($kind === 'newsletter') {
            $a['segment'] = 'subscribers';
        }
        json_out(['ok' => true, 'count' => Newsletter::audienceCount($a, $kind)]);
    }

    public function preview(string $id): string
    {
        $c = DB::row('SELECT * FROM campaigns WHERE id = ?', [(int)$id]);
        if (!$c) {
            redirect('/admin/email');
        }
        $u = Auth::user();
        [, $html] = Newsletter::render($c, ['contact_id' => null, 'name' => $u['name'], 'email' => $u['email'], 'token' => 'preview'], false);
        header('Content-Security-Policy: script-src \'none\'');
        return $html;
    }

    public function test(string $id): never
    {
        $c = DB::row('SELECT * FROM campaigns WHERE id = ?', [(int)$id]);
        $to = str_input('to') ?: (string)Auth::user()['email'];
        if ($c) {
            [$subject, $html, $unsub] = Newsletter::render($c, ['contact_id' => null, 'name' => Auth::user()['name'], 'email' => $to, 'token' => 'test'], false);
            [$ok, $err] = Mailer::send($to, '[TEST] ' . $subject, $html, ['kind' => 'test']);
            flash($ok ? 'ok' : 'err', $ok ? "Email de test trimis către $to." : "Eroare: $err");
        }
        redirect('/admin/email/' . (int)$id);
    }

    public function launch(string $id): never
    {
        $c = DB::row('SELECT * FROM campaigns WHERE id = ?', [(int)$id]);
        if (!$c || !in_array($c['status'], ['draft', 'scheduled'], true)) {
            redirect('/admin/email');
        }
        if (trim(strip_tags((string)$c['body'])) === '') {
            flash('err', 'Emailul nu are conținut.');
            redirect('/admin/email/' . $c['id']);
        }
        $when = str_input('scheduled_at');
        $n = Newsletter::prepare($c);
        if ($n === 0) {
            flash('err', 'Audiența selectată nu conține niciun destinatar.');
            DB::delete('campaign_recipients', 'campaign_id = ?', [$c['id']]);
            redirect('/admin/email/' . $c['id']);
        }
        if ($when !== '' && ($ts = strtotime($when)) && $ts > time() + 60) {
            DB::update('campaigns', ['status' => 'scheduled', 'scheduled_at' => gmdate('Y-m-d H:i:s', $ts)], 'id = :id', ['id' => $c['id']]);
            flash('ok', "Campania este programată pentru " . date('d.m.Y H:i', $ts) . " către $n destinatari. Trimiterea pornește prin cron.");
        } else {
            DB::update('campaigns', ['status' => 'sending', 'started_at' => DB::now()], 'id = :id', ['id' => $c['id']]);
            flash('ok', "Trimiterea către $n destinatari a început. Poți lăsa pagina deschisă sau cron-ul continuă automat.");
        }
        redirect('/admin/email/' . $c['id'] . '/raport?start=1');
    }

    public function batch(string $id): never
    {
        @set_time_limit(120);
        json_out(Newsletter::sendBatch((int)$id));
    }

    public function pause(string $id): never
    {
        $c = DB::row('SELECT * FROM campaigns WHERE id = ?', [(int)$id]);
        if ($c && in_array($c['status'], ['sending', 'scheduled'], true)) {
            DB::update('campaigns', ['status' => 'paused'], 'id = :id', ['id' => $c['id']]);
            flash('ok', 'Campania a fost pusă pe pauză.');
        } elseif ($c && $c['status'] === 'paused') {
            DB::update('campaigns', ['status' => 'sending'], 'id = :id', ['id' => $c['id']]);
            flash('ok', 'Trimiterea a fost reluată.');
        }
        redirect('/admin/email/' . (int)$id . '/raport');
    }

    public function duplicate(string $id): never
    {
        $c = DB::row('SELECT * FROM campaigns WHERE id = ?', [(int)$id]);
        if ($c) {
            $nid = DB::insert('campaigns', [
                'name' => $c['name'] . ' (copie)', 'kind' => $c['kind'], 'subject' => $c['subject'], 'preheader' => $c['preheader'],
                'body' => $c['body'], 'audience' => $c['audience'], 'status' => 'draft', 'created_by' => Auth::user()['id'] ?? null,
                'created_at' => DB::now(), 'updated_at' => DB::now(),
            ]);
            redirect('/admin/email/' . $nid);
        }
        redirect('/admin/email');
    }

    public function delete(string $id): never
    {
        $c = DB::row('SELECT * FROM campaigns WHERE id = ?', [(int)$id]);
        if ($c && $c['status'] !== 'sending') {
            DB::delete('campaign_recipients', 'campaign_id = ?', [$c['id']]);
            DB::delete('campaign_links', 'campaign_id = ?', [$c['id']]);
            DB::delete('campaigns', 'id = ?', [$c['id']]);
            flash('ok', 'Campania a fost ștearsă.');
        }
        redirect('/admin/email');
    }

    public function report(string $id): string
    {
        $c = DB::row('SELECT * FROM campaigns WHERE id = ?', [(int)$id]);
        if (!$c) {
            redirect('/admin/email');
        }
        $filter = str_input('f');
        $w = 'campaign_id = ?';
        if ($filter === 'opened') {
            $w .= ' AND opened_at IS NOT NULL';
        } elseif ($filter === 'clicked') {
            $w .= ' AND clicked_at IS NOT NULL';
        } elseif ($filter === 'failed') {
            $w .= " AND status = 'failed'";
        }
        return $this->render('email/report', [
            'c' => $c,
            'links' => DB::all('SELECT * FROM campaign_links WHERE campaign_id = ? ORDER BY clicks DESC', [$c['id']]),
            'rcpts' => DB::all("SELECT * FROM campaign_recipients WHERE $w ORDER BY id LIMIT 500", [$c['id']]),
            'filter' => $filter,
            'title' => 'Raport: ' . $c['name'],
        ]);
    }

    public function subscribers(): string
    {
        $counts = [];
        foreach (Crm::NEWSLETTER as $k => $l) {
            $counts[$k] = (int)DB::val('SELECT COUNT(*) FROM contacts WHERE newsletter = ?', [$k]);
        }
        $recent = DB::all("SELECT * FROM contacts WHERE newsletter IN ('subscribed','pending','unsubscribed') ORDER BY COALESCE(newsletter_consent_at, unsubscribed_at, updated_at) DESC LIMIT 100");
        return $this->render('email/subscribers', ['counts' => $counts, 'recent' => $recent, 'title' => 'Abonați']);
    }

    public function log(): string
    {
        return $this->render('email/log', ['rows' => DB::all('SELECT * FROM email_log ORDER BY id DESC LIMIT 300'), 'title' => 'Jurnal emailuri']);
    }
}
