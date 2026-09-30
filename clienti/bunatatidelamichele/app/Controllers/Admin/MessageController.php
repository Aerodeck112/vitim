<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\DB;

final class MessageController extends AdminController
{
    protected string $area = 'messages';

    public function index(): string
    {
        $spam = str_input('spam') === '1';
        $rows = DB::all('SELECT * FROM messages WHERE spam_score ' . ($spam ? '>=' : '<') . ' 6 ORDER BY id DESC LIMIT 300');
        return $this->render('messages/index', ['rows' => $rows, 'spam' => $spam, 'spamCount' => (int)DB::val('SELECT COUNT(*) FROM messages WHERE spam_score >= 6'), 'title' => 'Mesaje']);
    }

    public function show(string $id): string
    {
        $m = DB::row('SELECT * FROM messages WHERE id = ?', [(int)$id]);
        if (!$m) {
            redirect('/admin/mesaje');
        }
        if (!$m['read_at']) {
            DB::update('messages', ['read_at' => DB::now()], 'id = :id', ['id' => $m['id']]);
        }
        $orders = DB::all('SELECT id, number, total, status, created_at FROM orders WHERE LOWER(email) = ? ORDER BY id DESC LIMIT 10', [mb_strtolower($m['email'])]);
        return $this->render('messages/show', ['m' => $m, 'orders' => $orders, 'title' => 'Mesaj de la ' . $m['name']]);
    }

    public function delete(string $id): never
    {
        DB::delete('messages', 'id = ?', [(int)$id]);
        flash('ok', 'Mesajul a fost șters.');
        redirect('/admin/mesaje');
    }
}
