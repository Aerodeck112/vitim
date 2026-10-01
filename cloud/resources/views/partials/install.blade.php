{{-- Codul de instalare al unui site. $publicKey obligatoriu; $secret doar imediat după emitere. --}}
@isset($secret)
  <div class="alert alert-warn">
    <strong>Cod de conectare: se afișează o singură dată.</strong>
    <pre class="code">{{ \App\Services\ConnectionCode::encode(config('app.url'), $publicKey, $secret) }}</pre>
    <div class="small" style="margin:6px 0"><a href="{{ asset('downloads/vitim-connector.zip') }}">Descarcă pluginul WordPress</a> · <a href="{{ asset('downloads/vitim-connector.php.txt') }}" download="vitim-connector.php">Descarcă conectorul PHP</a></div>
    <div class="small muted">Lipește-l în pluginul <strong>VITIM Connector</strong> (WordPress → Setări → VITIM) sau în fișierul <span class="mono">vitim-connector.php</span> (site-uri PHP). Dacă se pierde, apasă „Schimbă cheile” la site și primești un cod nou.</div>
  </div>
@endisset
<div class="fl">
  <label>Agentul AI pe orice site (înainte de &lt;/body&gt;)</label>
  <pre class="code">&lt;script src="{{ url('/widget/v1/loader.js') }}" data-site="{{ $publicKey }}" async&gt;&lt;/script&gt;</pre>
  <div class="hint">Se activează din Agent AI → „Agentul pe site”. Pe WordPress îl adaugă pluginul.</div>
</div>
