<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Assistant;
use App\Core\DB;
use App\Core\Settings;

final class AssistantController extends AdminController
{
    protected string $area = 'crm';

    public function index(): string
    {
        $rows = DB::all('SELECT c.*, ct.name AS cname FROM ai_chats c LEFT JOIN contacts ct ON ct.id = c.contact_id ORDER BY c.updated_at DESC LIMIT 200');
        $since = gmdate('Y-m-d H:i:s', strtotime('-30 days'));
        $stats = DB::row('SELECT COUNT(*) AS n, COALESCE(SUM(turns),0) AS turns, COALESCE(SUM(input_tokens),0) AS tin, COALESCE(SUM(output_tokens),0) AS tout, SUM(CASE WHEN deal_id IS NOT NULL THEN 1 ELSE 0 END) AS leads FROM ai_chats WHERE created_at >= ?', [$since]);
        return $this->render('assistant/index', [
            'rows' => $rows, 'stats' => $stats,
            'enabled' => Assistant::enabled(),
            'configured' => (string)Settings::get('ai_api_key') !== '',
            'title' => 'Asistent AI',
        ]);
    }

    public function show(string $id): string
    {
        $c = DB::row('SELECT * FROM ai_chats WHERE id = ?', [(int)$id]);
        if (!$c) {
            redirect('/admin/asistent');
        }
        return $this->render('assistant/show', ['c' => $c, 'msgs' => Assistant::transcript(json_list($c['messages'])), 'title' => 'Conversație #' . $c['id']]);
    }

    public function test(): never
    {
        if ((string)Settings::get('ai_api_key') === '') {
            flash('err', 'Adaugă întâi cheia API în Setări → Asistent AI.');
        } else {
            [$ok, $msg] = Assistant::testConnection();
            flash($ok ? 'ok' : 'err', $msg);
        }
        redirect('/admin/asistent');
    }

    public function delete(string $id): never
    {
        DB::delete('ai_chats', 'id = ?', [(int)$id]);
        flash('ok', 'Conversația a fost ștearsă.');
        redirect('/admin/asistent');
    }
}
