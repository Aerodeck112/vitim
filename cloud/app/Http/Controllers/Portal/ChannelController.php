<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\Channel;
use App\Models\ChannelAccount;
use App\Services\ChannelAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Setări → Canale de trimitere: contul propriu de email (SMTP), SMS (SMSLink) și WhatsApp (Meta) al firmei. */
final class ChannelController extends PortalController
{
    public function index(): View
    {
        return view('portal.channels', [
            'organization' => $this->organization(),
            'accounts' => ChannelAccount::query()->get()->keyBy('channel'),
            'fields' => ChannelAccountService::FIELDS,
        ]);
    }

    public function save(Request $request, ChannelAccountService $accounts, string $channel): RedirectResponse
    {
        $accounts->save($this->channel($channel), $request->except(['_token', '_method']));

        return $this->to('portal.channels', [], 'Contul a fost salvat. Trimite acum un test ca să verifici că funcționează.');
    }

    public function test(Request $request, ChannelAccountService $accounts, string $channel): RedirectResponse
    {
        $account = ChannelAccount::query()->where('channel', $this->channel($channel)->value)->firstOrFail();
        $ok = $accounts->test($account, (string) $request->input('test_to', ''));

        return $ok
            ? $this->to('portal.channels', [], $channel === 'whatsapp' ? 'Conexiunea cu WhatsApp funcționează.' : 'Mesajul de test a plecat. Verifică dacă a ajuns.')
            : $this->to('portal.channels', [])->withErrors(['test' => 'Testul a eșuat: '.$account->last_error]);
    }

    public function destroy(ChannelAccountService $accounts, string $channel): RedirectResponse
    {
        $accounts->delete(ChannelAccount::query()->where('channel', $this->channel($channel)->value)->firstOrFail());

        return $this->to('portal.channels', [], 'Contul a fost deconectat.');
    }

    private function channel(string $value): Channel
    {
        return in_array($value, ['email', 'sms', 'whatsapp'], true) ? Channel::from($value) : abort(404);
    }
}
