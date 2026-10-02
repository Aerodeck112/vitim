@extends('layouts.app')
@section('title', 'Canale de trimitere')
@section('content')
<div class="head"><div><h1>Canale de trimitere</h1><p>Campaniile pleacă din conturile firmei tale: emailul tău, contul tău SMSLink, numărul tău de WhatsApp Business.</p></div>
  <a class="btn" href="{{ route('portal.campaigns.index', $organization->slug) }}">Campanii</a></div>
@php($titles = ['email' => ['Email (SMTP)', 'Folosește o adresă de email a firmei, de exemplu din cPanel → Email Accounts → Connect Devices (serverul, portul și utilizatorul sunt acolo).'],
  'sms' => ['SMS (SMSLink.ro)', 'Ai nevoie de un cont pe smslink.ro cu credit. În SMSLink: SMS Gateway → Configurare → creează o conexiune: primești Connection ID și parola conexiunii.'],
  'whatsapp' => ['WhatsApp (Meta, oficial)', 'Ai nevoie de Meta Business verificat, un număr adăugat în WhatsApp Manager și un token permanent (Business Settings → System Users). Campaniile folosesc doar șabloane aprobate de Meta.']])
@foreach ($fields as $channel => $list)
  @php($account = $accounts[$channel] ?? null)
  <div class="card" id="{{ $channel }}">
    <h2 style="display:flex;justify-content:space-between;gap:10px;align-items:center">{{ $titles[$channel][0] }}
      @if ($account)<span class="badge {{ ['ok' => 'ok', 'error' => 'err'][$account->status] ?? 'warn' }}">{{ ['ok' => 'funcționează', 'error' => 'eroare', 'untested' => 'netestat'][$account->status] ?? $account->status }}</span>@else<span class="badge">neconectat</span>@endif</h2>
    <p class="small muted" style="margin-top:-6px">{{ $titles[$channel][1] }}</p>
    @if ($account?->status === 'error' && $account->last_error)<div class="alert alert-err small">Ultimul test: {{ $account->last_error }}</div>@endif
    <form method="post" action="{{ route('portal.channels.save', [$organization->slug, $channel]) }}">
      @csrf @method('put')
      <div class="grid">
      @foreach ($list as $key => [$label, $secret, $required])
        <div class="fl"><label for="{{ $channel }}-{{ $key }}">{{ $label }}</label>
          @if ($key === 'encryption')
            <select id="{{ $channel }}-{{ $key }}" name="{{ $key }}"><option value="ssl" @selected(($account?->setting('encryption') ?? 'ssl') === 'ssl')>SSL (port 465)</option><option value="tls" @selected($account?->setting('encryption') === 'tls')>TLS / STARTTLS (port 587)</option></select>
          @elseif ($secret)
            <input id="{{ $channel }}-{{ $key }}" type="password" name="{{ $key }}" autocomplete="new-password" placeholder="{{ $account && $account->setting($key) ? '•••••••• salvat (gol = rămâne)' : '' }}">
          @else
            <input id="{{ $channel }}-{{ $key }}" type="text" name="{{ $key }}" value="{{ old($key, $account?->setting($key, $key === 'port' ? '465' : '')) }}">
          @endif
          @error($key)<div class="err">{{ $message }}</div>@enderror</div>
      @endforeach
      @if ($channel === 'email')
        <div class="fl"><label for="email-limit">Maximum emailuri pe oră</label><input id="email-limit" type="number" name="hourly_limit" min="10" max="5000" value="{{ $account?->hourly_limit ?? 100 }}"><div class="hint">Hostingul limitează trimiterile (de obicei 100–500 pe oră, vezi cPanel sau întreabă furnizorul). Campaniile mari se împart automat pe ore.</div></div>
      @endif
      </div>
      @if ($channel === 'sms')<label class="chk"><input type="checkbox" name="ascii" value="1" @checked($account?->setting('ascii', true) ?? true)> Fără diacritice în SMS (160 de caractere pe SMS, nu 70)</label>@endif
      <button class="btn btn-p" type="submit" style="margin-top:10px">Salvează</button>
    </form>
    @if ($account)
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-top:14px;border-top:1px solid var(--border);padding-top:14px">
        <form method="post" action="{{ route('portal.channels.test', [$organization->slug, $channel]) }}" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">@csrf
          @if ($channel !== 'whatsapp')<div class="fl" style="margin:0"><label for="{{ $channel }}-test">{{ $channel === 'email' ? 'Trimite un email de test la' : 'Trimite un SMS de test la' }}</label>
            <input id="{{ $channel }}-test" type="text" name="test_to" required placeholder="{{ $channel === 'email' ? auth()->user()->email : '07xx xxx xxx' }}" value="{{ $channel === 'email' ? auth()->user()->email : '' }}"></div>@endif
          <button class="btn" type="submit">{{ $channel === 'whatsapp' ? 'Verifică conexiunea' : 'Trimite testul' }}</button></form>
        <form method="post" action="{{ route('portal.channels.destroy', [$organization->slug, $channel]) }}" onsubmit="return confirm('Deconectezi contul? Campaniile în curs pe acest canal se opresc.')">@csrf @method('delete')<button class="btn btn-d" type="submit">Deconectează</button></form>
      </div>
      @if ($channel === 'whatsapp')
        <div class="fl" style="margin-top:14px"><label>Webhook (Meta → WhatsApp → Configuration): statusurile de livrare și dezabonările prin „STOP”</label>
          <div class="small">Callback URL: <span class="mono">{{ route('webhooks.whatsapp', $account->webhook_token) }}</span><br>Verify token: <span class="mono">{{ $account->setting('verify_token') }}</span><br>Abonează câmpul <span class="mono">messages</span>. Fără App Secret, webhook-ul refuză cererile.</div></div>
      @endif
    @endif
  </div>
@endforeach
@endsection
