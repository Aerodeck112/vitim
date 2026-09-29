<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Assistant;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\RateLimit;
use App\Core\Sanitizer;
use App\Core\Settings;

/**
 * API pentru chatul de pe site.
 */
final class ChatController
{
    private function guard(): void
    {
        header('X-Robots-Tag: noindex');
        header('Cache-Control: no-store');
        if (!Assistant::enabled()) {
            json_out(['ok' => false, 'message' => 'Asistentul nu este disponibil momentan.'], 503);
        }
    }

    /** Conversația existentă (după navigarea pe altă pagină). */
    public function history(string $token): never
    {
        $this->guard();
        $c = DB::row('SELECT messages FROM ai_chats WHERE token = ?', [$token]);
        json_out(['ok' => true, 'messages' => $c ? Assistant::transcript(json_list($c['messages'])) : []]);
    }

    public function send(): never
    {
        $this->guard();
        if (!Csrf::sameOrigin()) {
            json_out(['ok' => false, 'message' => 'Cerere invalidă.'], 403);
        }
        if (!Csrf::verifyFormToken($_POST['_t'] ?? null, 0)) {
            json_out(['ok' => false, 'message' => 'Reîncarcă pagina și încearcă din nou.'], 422);
        }
        $ip = client_ip();
        if (!RateLimit::hit('chat:' . $ip, 20, 600)) {
            json_out(['ok' => false, 'message' => 'Ai trimis multe mesaje într-un timp scurt. Revino peste câteva minute sau sună-ne la ' . Settings::get('phone') . '.'], 429);
        }
        if (!RateLimit::hit('chat:global:' . date('Ymd'), (int)Settings::get('ai_daily_limit', '300'), 90000)) {
            json_out(['ok' => false, 'message' => 'Asistentul a atins limita zilnică de conversații. Ne poți suna la ' . Settings::get('phone') . ' sau scrie la ' . Settings::get('email') . '.'], 429);
        }
        $text = Sanitizer::text(str_input('message'), 1500);
        if (mb_strlen($text) < 1) {
            json_out(['ok' => false, 'message' => 'Scrie un mesaj.'], 422);
        }
        $token = preg_replace('/[^A-Za-z0-9_-]/', '', str_input('token'));
        $chat = $token !== '' ? DB::row('SELECT * FROM ai_chats WHERE token = ?', [$token]) : null;
        if (!$chat) {
            $token = random_token(18);
            $id = DB::insert('ai_chats', [
                'token' => $token, 'messages' => '[]', 'page' => mb_substr(Sanitizer::text(str_input('page'), 250), 0, 250),
                'ip' => $ip, 'turns' => 0, 'status' => 'open', 'created_at' => DB::now(), 'updated_at' => DB::now(),
            ]);
            $chat = DB::row('SELECT * FROM ai_chats WHERE id = ?', [$id]);
        }
        if ((int)$chat['turns'] >= (int)Settings::get('ai_max_turns', '24')) {
            json_out(['ok' => true, 'token' => $token, 'reply' => 'Conversația a devenit destul de lungă pentru un chat. Ca să te ajutăm cum trebuie, sună-ne la ' . Settings::get('phone') . ' sau lasă-ne un mesaj pe pagina [Contact](/contact).', 'lead' => false]);
        }
        @set_time_limit(120);
        $r = Assistant::reply($chat, $text);
        if (empty($r['error'])) {
            DB::update('ai_chats', [
                'messages' => $chat['messages'], 'turns' => $chat['turns'], 'input_tokens' => $chat['input_tokens'], 'output_tokens' => $chat['output_tokens'],
                'contact_id' => $chat['contact_id'] ?? null, 'deal_id' => $chat['deal_id'] ?? null, 'status' => !empty($chat['deal_id']) ? 'lead' : 'open',
                'updated_at' => DB::now(),
            ], 'id = :id', ['id' => $chat['id']]);
        }
        json_out(['ok' => true, 'token' => $token, 'reply' => $r['reply'], 'lead' => $r['lead']]);
    }
}
