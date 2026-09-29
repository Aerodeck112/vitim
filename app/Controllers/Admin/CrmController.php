<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Crm;
use App\Core\DB;
use App\Core\Mailer;
use App\Core\Sanitizer;

final class CrmController extends AdminController
{
    protected string $area = 'crm';

    // ------------------------------------------------------------------ pipeline

    public function pipeline(): string
    {
        $q = str_input('q');
        $params = [];
        $w = '1=1';
        if ($q !== '') {
            $w = '(d.title LIKE :q OR c.name LIKE :q2 OR c.company LIKE :q3)';
            $params = ['q' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%"];
        }
        $showClosed = !empty($_GET['inchise']);
        if (!$showClosed) {
            $w .= " AND (d.stage NOT IN ('castigat','pierdut') OR d.closed_at >= :since)";
            $params['since'] = gmdate('Y-m-d H:i:s', strtotime('-30 days'));
        }
        $deals = DB::all("SELECT d.*, c.name AS cname, c.company, c.phone FROM deals d JOIN contacts c ON c.id = d.contact_id WHERE $w ORDER BY d.sort, d.id DESC", $params);
        $by = [];
        foreach ($deals as $d) {
            $by[$d['stage']][] = $d;
        }
        return $this->render('crm/pipeline', ['stages' => Crm::stages(), 'by' => $by, 'q' => $q, 'showClosed' => $showClosed, 'title' => 'Oportunități']);
    }

    public function dealStage(string $id): never
    {
        $stage = str_input('stage');
        $stages = Crm::stages();
        $deal = DB::row('SELECT * FROM deals WHERE id = ?', [(int)$id]);
        if (!$deal || !isset($stages[$stage])) {
            json_out(['ok' => false, 'message' => 'Etapă invalidă.'], 422);
        }
        $this->setStage($deal, $stage);
        json_out(['ok' => true]);
    }

    private function setStage(array $deal, string $stage): void
    {
        if ($deal['stage'] === $stage) {
            return;
        }
        $stages = Crm::stages();
        $closed = in_array($stage, ['castigat', 'pierdut'], true);
        DB::update('deals', ['stage' => $stage, 'closed_at' => $closed ? DB::now() : null, 'updated_at' => DB::now()], 'id = :id', ['id' => $deal['id']]);
        if ($stage === 'castigat') {
            DB::q("UPDATE contacts SET status = 'client', updated_at = ? WHERE id = ? AND status IN ('lead','prospect','fost_client')", [DB::now(), $deal['contact_id']]);
        } elseif ($stage !== 'nou') {
            DB::q("UPDATE contacts SET status = 'prospect', updated_at = ? WHERE id = ? AND status = 'lead'", [DB::now(), $deal['contact_id']]);
        }
        Crm::logActivity((int)$deal['contact_id'], (int)$deal['id'], 'stage', '„' . $deal['title'] . '” → ' . ($stages[$stage]['label'] ?? $stage));
    }

    public function dealEdit(?string $id = null): string
    {
        $deal = $id ? DB::row('SELECT * FROM deals WHERE id = ?', [(int)$id]) : null;
        if ($id && !$deal) {
            redirect('/admin/crm');
        }
        $errors = [];
        if ($this->isPost()) {
            $contactId = (int)str_input('contact_id');
            if (!$contactId && str_input('new_contact') !== '') {
                $contactId = Crm::upsertContact(['name' => Sanitizer::text(str_input('new_contact'), 120), 'email' => mb_strtolower(str_input('new_email')), 'phone' => str_input('new_phone')], 'manual');
            }
            $stage = str_input('stage', 'nou');
            $data = [
                'contact_id' => $contactId,
                'title' => Sanitizer::text(str_input('title'), 250),
                'service' => Sanitizer::text(str_input('service'), 250) ?: null,
                'value' => (float)str_replace([' ', '.', ','], ['', '', '.'], str_input('value', '0')),
                'expected_close' => str_input('expected_close') !== '' ? gmdate('Y-m-d H:i:s', (int)strtotime(str_input('expected_close'))) : null,
                'message' => Sanitizer::text(str_input('message'), 10000),
                'owner_id' => (int)str_input('owner_id') ?: null,
                'updated_at' => DB::now(),
            ];
            if (!$contactId || !DB::val('SELECT id FROM contacts WHERE id = ?', [$contactId])) {
                $errors[] = 'Alege un contact existent sau completează numele unui contact nou.';
            }
            if ($data['title'] === '') {
                $errors[] = 'Completează titlul oportunității.';
            }
            if (!$errors) {
                if ($deal) {
                    DB::update('deals', $data, 'id = :id', ['id' => $deal['id']]);
                    $this->setStage(DB::row('SELECT * FROM deals WHERE id = ?', [$deal['id']]), $stage);
                    $did = (int)$deal['id'];
                } else {
                    $data += ['stage' => $stage, 'source' => 'manual', 'utm' => '{}', 'created_at' => DB::now()];
                    $did = DB::insert('deals', $data);
                    Crm::logActivity($contactId, $did, 'note', 'Oportunitate creată manual: ' . $data['title']);
                }
                flash('ok', 'Oportunitate salvată.');
                redirect('/admin/crm/oportunitati/' . $did);
            }
            $deal = array_merge($deal ?? [], $_POST);
        }
        $contact = !empty($deal['contact_id']) ? DB::row('SELECT * FROM contacts WHERE id = ?', [$deal['contact_id']]) : (isset($_GET['contact']) ? DB::row('SELECT * FROM contacts WHERE id = ?', [(int)$_GET['contact']]) : null);
        $acts = !empty($deal['id']) ? DB::all('SELECT a.*, u.name AS uname FROM activities a LEFT JOIN users u ON u.id = a.user_id WHERE a.deal_id = ? ORDER BY a.id DESC', [$deal['id']]) : [];
        return $this->render('crm/deal', [
            'deal' => $deal, 'contact' => $contact, 'acts' => $acts, 'errors' => $errors,
            'stages' => Crm::stages(),
            'contacts' => DB::all('SELECT id, name, company FROM contacts ORDER BY name LIMIT 2000'),
            'users' => DB::all('SELECT id, name FROM users WHERE active = 1 ORDER BY name'),
            'services' => array_column(DB::all('SELECT title FROM services ORDER BY sort'), 'title'),
            'title' => $deal && !empty($deal['id']) ? 'Oportunitate' : 'Oportunitate nouă',
        ]);
    }

    public function dealDelete(string $id): never
    {
        $d = DB::row('SELECT * FROM deals WHERE id = ?', [(int)$id]);
        if ($d) {
            DB::delete('deals', 'id = ?', [$d['id']]);
            DB::q('UPDATE activities SET deal_id = NULL WHERE deal_id = ?', [$d['id']]);
            flash('ok', 'Oportunitatea a fost ștearsă.');
            redirect('/admin/crm/contacte/' . $d['contact_id']);
        }
        redirect('/admin/crm');
    }

    // ------------------------------------------------------------------ contacte

    private function contactFilters(): array
    {
        $w = ['1=1'];
        $p = [];
        $q = str_input('q');
        if ($q !== '') {
            $w[] = '(name LIKE :q1 OR email LIKE :q2 OR phone LIKE :q3 OR company LIKE :q4 OR tags LIKE :q5)';
            foreach (range(1, 5) as $i) {
                $p["q$i"] = "%$q%";
            }
        }
        foreach (['status', 'newsletter', 'county'] as $f) {
            $v = str_input($f);
            if ($v !== '') {
                $w[] = "$f = :$f";
                $p[$f] = $v;
            }
        }
        $tag = str_input('tag');
        if ($tag !== '') {
            $w[] = 'tags LIKE :tag';
            $p['tag'] = "%$tag%";
        }
        return [implode(' AND ', $w), $p];
    }

    public function contacts(): string
    {
        [$w, $p] = $this->contactFilters();
        $total = (int)DB::val("SELECT COUNT(*) FROM contacts WHERE $w", $p);
        $pg = $this->paginate($total, 50, (int)($_GET['p'] ?? 1));
        $rows = DB::all("SELECT c.*, (SELECT COUNT(*) FROM deals d WHERE d.contact_id = c.id AND d.stage NOT IN ('castigat','pierdut')) AS open_deals FROM contacts c WHERE $w ORDER BY COALESCE(c.last_contact_at, c.created_at) DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
        $counties = array_column(DB::all("SELECT DISTINCT county FROM contacts WHERE county IS NOT NULL AND county <> '' ORDER BY county"), 'county');
        return $this->render('crm/contacts', ['rows' => $rows, 'pg' => $pg, 'counties' => $counties, 'title' => 'Contacte']);
    }

    public function contactShow(string $id): string
    {
        $c = DB::row('SELECT * FROM contacts WHERE id = ?', [(int)$id]);
        if (!$c) {
            flash('err', 'Contactul nu există.');
            redirect('/admin/crm/contacte');
        }
        $deals = DB::all('SELECT * FROM deals WHERE contact_id = ? ORDER BY id DESC', [$c['id']]);
        $acts = DB::all('SELECT a.*, u.name AS uname FROM activities a LEFT JOIN users u ON u.id = a.user_id WHERE a.contact_id = ? ORDER BY a.id DESC LIMIT 200', [$c['id']]);
        $camps = DB::all('SELECT r.*, c.name AS cname FROM campaign_recipients r JOIN campaigns c ON c.id = r.campaign_id WHERE r.contact_id = ? ORDER BY r.id DESC LIMIT 20', [$c['id']]);
        return $this->render('crm/contact', ['c' => $c, 'deals' => $deals, 'acts' => $acts, 'camps' => $camps, 'stages' => Crm::stages(), 'title' => $c['name']]);
    }

    public function contactEdit(?string $id = null): string
    {
        $c = $id ? DB::row('SELECT * FROM contacts WHERE id = ?', [(int)$id]) : null;
        $errors = [];
        if ($this->isPost()) {
            $data = [];
            foreach (['name' => 120, 'phone' => 40, 'company' => 160, 'cui' => 40, 'position' => 120, 'city' => 80, 'county' => 80, 'address' => 250, 'website' => 250, 'tags' => 250] as $k => $max) {
                $data[$k] = Sanitizer::text(str_input($k), $max) ?: null;
            }
            $data['email'] = mb_strtolower(Sanitizer::text(str_input('email'), 160)) ?: null;
            $data['notes'] = Sanitizer::text(str_input('notes'), 20000);
            $data['status'] = array_key_exists(str_input('status'), Crm::STATUSES) ? str_input('status') : 'lead';
            $data['owner_id'] = (int)str_input('owner_id') ?: null;
            if ($data['tags']) {
                $data['tags'] = implode(', ', array_unique(array_filter(array_map(fn($t) => mb_strtolower(trim($t)), explode(',', $data['tags'])))));
            }
            if (!$data['name']) {
                $errors[] = 'Numele este obligatoriu.';
            }
            if ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Emailul nu este valid.';
            }
            if ($data['email'] && DB::val('SELECT id FROM contacts WHERE LOWER(email) = ? AND id <> ?', [$data['email'], (int)($c['id'] ?? 0)])) {
                $errors[] = 'Există deja un contact cu acest email.';
            }
            $nl = str_input('newsletter');
            if ($nl === 'subscribed' && ($c['newsletter'] ?? 'none') !== 'subscribed') {
                if (empty($_POST['consent_ok'])) {
                    $errors[] = 'Pentru a abona manual un contact trebuie să confirmi că ai acordul lui (GDPR).';
                } else {
                    $data['newsletter'] = 'subscribed';
                    $data['newsletter_consent_at'] = DB::now();
                    $data['newsletter_consent_ip'] = 'manual:' . (Auth::user()['id'] ?? '');
                    $data['unsubscribed_at'] = null;
                }
            } elseif (in_array($nl, ['none', 'unsubscribed'], true) && $nl !== ($c['newsletter'] ?? 'none')) {
                $data['newsletter'] = $nl;
                if ($nl === 'unsubscribed') {
                    $data['unsubscribed_at'] = DB::now();
                }
            }
            if (!$errors) {
                $data['updated_at'] = DB::now();
                if ($c) {
                    DB::update('contacts', $data, 'id = :id', ['id' => $c['id']]);
                    $cid = (int)$c['id'];
                } else {
                    $data += ['source' => 'manual', 'unsub_token' => random_token(18), 'created_at' => DB::now()];
                    $data['newsletter'] ??= 'none';
                    $cid = DB::insert('contacts', $data);
                    Crm::logActivity($cid, null, 'note', 'Contact adăugat manual.');
                }
                flash('ok', 'Contact salvat.');
                redirect('/admin/crm/contacte/' . $cid);
            }
            $c = array_merge($c ?? [], $_POST);
        }
        return $this->render('crm/contact_edit', ['c' => $c, 'errors' => $errors, 'users' => DB::all('SELECT id, name FROM users WHERE active = 1 ORDER BY name'), 'title' => $c && !empty($c['id']) ? 'Editează contact' : 'Contact nou']);
    }

    /** Ștergere completă (dreptul de a fi uitat – GDPR). */
    public function contactDelete(string $id): never
    {
        $cid = (int)$id;
        DB::transaction(function () use ($cid) {
            DB::delete('activities', 'contact_id = ?', [$cid]);
            DB::delete('deals', 'contact_id = ?', [$cid]);
            DB::delete('submissions', 'contact_id = ?', [$cid]);
            DB::q("UPDATE campaign_recipients SET email = 'sters@gdpr.invalid', name = NULL, contact_id = NULL WHERE contact_id = ?", [$cid]);
            DB::delete('contacts', 'id = ?', [$cid]);
        });
        flash('ok', 'Contactul și toate datele asociate au fost șterse definitiv.');
        redirect('/admin/crm/contacte');
    }

    public function export(): never
    {
        [$w, $p] = $this->contactFilters();
        $rows = DB::all("SELECT * FROM contacts WHERE $w ORDER BY name", $p);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="contacte-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM pentru Excel
        $cols = ['name' => 'nume', 'email' => 'email', 'phone' => 'telefon', 'company' => 'firma', 'cui' => 'cui', 'position' => 'functie', 'city' => 'oras', 'county' => 'judet', 'address' => 'adresa', 'website' => 'website', 'status' => 'status', 'source' => 'sursa', 'tags' => 'etichete', 'newsletter' => 'newsletter', 'created_at' => 'creat'];
        fputcsv($out, array_values($cols), ';');
        foreach ($rows as $r) {
            $line = [];
            foreach (array_keys($cols) as $k) {
                $v = (string)($r[$k] ?? '');
                if ($v !== '' && in_array($v[0], ['=', '+', '-', '@'], true)) {
                    $v = "'" . $v; // protecție injecție formule Excel
                }
                $line[] = $v;
            }
            fputcsv($out, $line, ';');
        }
        fclose($out);
        exit;
    }

    public function import(): string
    {
        $result = null;
        if ($this->isPost()) {
            $f = $_FILES['file'] ?? null;
            $tag = mb_strtolower(Sanitizer::text(str_input('tag'), 60));
            $status = array_key_exists(str_input('status'), Crm::STATUSES) ? str_input('status') : 'lead';
            $subscribe = !empty($_POST['subscribe']) && !empty($_POST['consent_ok']);
            if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
                flash('err', 'Încarcă un fișier CSV.');
                redirect('/admin/crm/import');
            }
            $fh = fopen($f['tmp_name'], 'r');
            $first = (string)fgets($fh);
            $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
            $delim = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
            $head = array_map(fn($h) => slugify(trim((string)$h)), str_getcsv($first, $delim));
            $alias = [
                'name' => ['nume', 'name', 'nume-si-prenume', 'contact', 'persoana'], 'email' => ['email', 'e-mail', 'mail', 'adresa-email'],
                'phone' => ['telefon', 'phone', 'tel', 'mobil'], 'company' => ['firma', 'companie', 'company', 'societate'], 'cui' => ['cui', 'cif'],
                'city' => ['oras', 'localitate', 'city'], 'county' => ['judet', 'county'], 'tags' => ['etichete', 'tags', 'tag'], 'position' => ['functie', 'position'],
            ];
            $map = [];
            foreach ($head as $i => $h) {
                foreach ($alias as $field => $names) {
                    if (in_array($h, $names, true)) {
                        $map[$field] = $i;
                    }
                }
            }
            $new = $upd = $skip = 0;
            if (!isset($map['email']) && !isset($map['phone'])) {
                flash('err', 'Fișierul trebuie să aibă cel puțin o coloană „email” sau „telefon”. Coloane găsite: ' . implode(', ', $head));
                redirect('/admin/crm/import');
            }
            DB::transaction(function () use ($fh, $delim, $map, $tag, $status, $subscribe, &$new, &$upd, &$skip) {
                while (($row = fgetcsv($fh, 0, $delim)) !== false) {
                    $g = fn($k) => isset($map[$k]) ? trim((string)($row[$map[$k]] ?? '')) : '';
                    $email = mb_strtolower($g('email'));
                    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $email = '';
                    }
                    if ($email === '' && $g('phone') === '') {
                        $skip++;
                        continue;
                    }
                    $existing = Crm::findContact($email, $g('phone'));
                    $tags = array_filter(array_map('trim', explode(',', $g('tags') . ($tag !== '' ? ',' . $tag : ''))));
                    if ($existing) {
                        $mergedTags = array_unique(array_merge(array_filter(array_map('trim', explode(',', (string)$existing['tags']))), $tags));
                        $data = ['tags' => implode(', ', $mergedTags), 'updated_at' => DB::now()];
                        foreach (['name', 'phone', 'company', 'cui', 'city', 'county', 'position'] as $k) {
                            if ($g($k) !== '' && empty($existing[$k])) {
                                $data[$k] = mb_substr($g($k), 0, 200);
                            }
                        }
                        if ($subscribe && $existing['newsletter'] !== 'unsubscribed') {
                            $data['newsletter'] = 'subscribed';
                            $data['newsletter_consent_at'] = DB::now();
                            $data['newsletter_consent_ip'] = 'import';
                        }
                        DB::update('contacts', $data, 'id = :id', ['id' => $existing['id']]);
                        $upd++;
                    } else {
                        DB::insert('contacts', [
                            'name' => mb_substr($g('name') ?: ($email ?: $g('phone')), 0, 200), 'email' => $email ?: null, 'phone' => $g('phone') ?: null,
                            'company' => $g('company') ?: null, 'cui' => $g('cui') ?: null, 'city' => $g('city') ?: null, 'county' => $g('county') ?: null,
                            'position' => $g('position') ?: null, 'tags' => implode(', ', array_unique($tags)) ?: null, 'status' => $status, 'source' => 'import',
                            'newsletter' => $subscribe ? 'subscribed' : 'none', 'newsletter_consent_at' => $subscribe ? DB::now() : null, 'newsletter_consent_ip' => $subscribe ? 'import' : null,
                            'unsub_token' => random_token(18), 'notes' => '', 'created_at' => DB::now(), 'updated_at' => DB::now(),
                        ]);
                        $new++;
                    }
                }
            });
            fclose($fh);
            $result = compact('new', 'upd', 'skip');
        }
        return $this->render('crm/import', ['result' => $result, 'title' => 'Import contacte']);
    }

    // ------------------------------------------------------------------ activități

    public function activityAdd(): never
    {
        $cid = (int)str_input('contact_id') ?: null;
        $did = (int)str_input('deal_id') ?: null;
        $type = array_key_exists(str_input('type'), Crm::ACTIVITY_TYPES) ? str_input('type') : 'note';
        $body = Sanitizer::text(str_input('body'), 20000);
        $due = str_input('due_at') !== '' ? gmdate('Y-m-d H:i:s', (int)strtotime(str_input('due_at'))) : null;
        if ($body === '') {
            flash('err', 'Scrie conținutul activității.');
        } else {
            if ($type === 'email' && !empty($_POST['send']) && $cid) {
                $c = DB::row('SELECT * FROM contacts WHERE id = ?', [$cid]);
                $subject = Sanitizer::text(str_input('subject'), 200) ?: 'Mesaj de la ' . setting('brand_name');
                if ($c && $c['email']) {
                    $u = Auth::user();
                    $html = '<p>' . nl2br(e($body)) . '</p><p style="margin-top:24px">Cu stimă,<br><strong>' . e($u['name']) . '</strong><br>' . e(setting('brand_name')) . ' · ' . e(setting('phone')) . '</p>';
                    [$ok, $err] = Mailer::send($c['email'], $subject, Mailer::layout($html), ['kind' => 'crm', 'reply_to' => $u['email'], 'reply_name' => $u['name'], 'to_name' => $c['name']]);
                    $body = ($ok ? "✉️ Trimis: $subject\n\n" : "⚠️ Netrimis ($err): $subject\n\n") . $body;
                    flash($ok ? 'ok' : 'err', $ok ? 'Email trimis către ' . $c['email'] . '.' : 'Emailul nu a putut fi trimis: ' . $err);
                }
            }
            Crm::logActivity($cid, $did, $type, $body, $type === 'task' ? $due : null);
            if ($cid) {
                DB::update('contacts', ['last_contact_at' => DB::now()], 'id = :id', ['id' => $cid]);
            }
        }
        redirect($_SERVER['HTTP_REFERER'] ?? '/admin/crm');
    }

    public function activityDone(string $id): never
    {
        DB::update('activities', ['done' => 1], 'id = :id', ['id' => (int)$id]);
        redirect($_SERVER['HTTP_REFERER'] ?? '/admin/crm/sarcini');
    }

    public function activityDelete(string $id): never
    {
        DB::delete('activities', 'id = ?', [(int)$id]);
        redirect($_SERVER['HTTP_REFERER'] ?? '/admin/crm');
    }

    public function tasks(): string
    {
        $open = DB::all("SELECT a.*, c.name AS cname, d.title AS dtitle FROM activities a LEFT JOIN contacts c ON c.id = a.contact_id LEFT JOIN deals d ON d.id = a.deal_id WHERE a.type = 'task' AND a.done = 0 ORDER BY CASE WHEN a.due_at IS NULL THEN 1 ELSE 0 END, a.due_at");
        $done = DB::all("SELECT a.*, c.name AS cname FROM activities a LEFT JOIN contacts c ON c.id = a.contact_id WHERE a.type = 'task' AND a.done = 1 ORDER BY a.id DESC LIMIT 30");
        return $this->render('crm/tasks', ['open' => $open, 'done' => $done, 'title' => 'Sarcini']);
    }

    // ------------------------------------------------------------------ formulare

    public function submissions(): string
    {
        $spam = !empty($_GET['spam']);
        $rows = DB::all('SELECT s.*, c.name AS cname FROM submissions s LEFT JOIN contacts c ON c.id = s.contact_id WHERE ' . ($spam ? 's.spam_score >= 5' : 's.spam_score < 5') . ' ORDER BY s.id DESC LIMIT 200');
        return $this->render('crm/submissions', ['rows' => $rows, 'spam' => $spam, 'title' => 'Formulare primite']);
    }

    public function submission(string $id): string
    {
        $s = DB::row('SELECT * FROM submissions WHERE id = ?', [(int)$id]);
        if (!$s) {
            redirect('/admin/formulare');
        }
        if (!$s['read_at']) {
            DB::update('submissions', ['read_at' => DB::now()], 'id = :id', ['id' => $s['id']]);
        }
        return $this->render('crm/submission', ['s' => $s, 'data' => json_list($s['data']), 'title' => 'Formular #' . $s['id']]);
    }
}
