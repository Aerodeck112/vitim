{{-- Codul de instalare al unui site. $publicKey obligatoriu; $secret doar imediat după emitere. --}}
<div class="fl">
  <label>Cod pentru orice site (înainte de &lt;/body&gt;)</label>
  <pre class="code">&lt;script src="{{ url('/widget/v1/loader.js') }}" data-site="{{ $publicKey }}" async&gt;&lt;/script&gt;</pre>
  <div class="hint">Widgetul devine activ în Faza 4. Codul și cheia rămân aceleași.</div>
</div>
@isset($secret)
  <div class="alert alert-warn">
    <strong>Cheia secretă pentru pluginul WordPress: se afișează o singură dată.</strong>
    <pre class="code">{{ $secret }}</pre>
    <div class="small muted">Copiaz-o acum în pluginul VITIM AI Connector. Dacă se pierde, schimbă cheile din pagina clientului.</div>
  </div>
@endisset
