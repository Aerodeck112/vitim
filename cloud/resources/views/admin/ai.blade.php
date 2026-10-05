@extends('layouts.app')
@section('title', 'Agent AI')
@section('content')
@php
  $reasons = \App\Http\Controllers\Admin\AiController::REASONS;
  $aiOn = $sites->where('ai_enabled', true)->count();
  $chatOn = $chat->filter()->count();
  $totalCost = $cost->sum() / 1_000_000;
@endphp
<div class="head"><div><h1>Agent AI</h1><p>Cheia AI a platformei și, pe fiecare site, dacă agentul răspunde cu AI și dacă chatul apare.</p></div></div>
@if (session('status'))<div class="alert alert-ok">{{ session('status') }}</div>@endif

<div class="ai-kpis">
  <div class="kpi"><small>Cheie AI</small><b>@if ($source === 'none')<span class="badge err ai-big">lipsește</span>@else<span class="badge ok ai-big">setată</span>@endif</b><span class="muted small">{{ ['panel' => 'salvată în panou', 'env' => 'din fișierul .env', 'none' => 'agenții răspund doar din informațiile firmei'][$source] }}</span></div>
  <div class="kpi"><small>Site-uri cu AI pornit</small><b>{{ $aiOn }} <span class="muted ai-of">/ {{ $sites->count() }}</span></b></div>
  <div class="kpi"><small>Site-uri cu chat afișat</small><b>{{ $chatOn }} <span class="muted ai-of">/ {{ $sites->count() }}</span></b></div>
  <div class="kpi"><small>Cost AI luna aceasta</small><b>${{ number_format($totalCost, 2) }}</b><span class="muted small">toate firmele, estimat</span></div>
</div>

<div class="ai-grid">
  <section class="card ai-key" aria-labelledby="k-h">
    <h2 id="k-h">Cheia AI</h2>
    @if ($check)
      <div class="ai-check {{ $check['ok'] ? 'ok' : 'err' }}" role="status">
        <strong>{{ $check['ok'] ? '✓ Conexiune reușită' : '✕ Nu funcționează' }}</strong>
        <span>{{ $reasons[$check['reason']] ?? $reasons['unavailable'] }}</span>
        @if (! $check['ok'] && $check['detail'] !== '')<details><summary>Detalii tehnice</summary><code class="mono small">{{ \Illuminate\Support\Str::limit($check['detail'], 300) }}</code></details>@endif
      </div>
    @endif
    <dl class="kv">
      <dt>Cheia activă</dt><dd>@if ($masked !== '')<span class="mono">{{ $masked }}</span>@else<span class="muted">niciuna</span>@endif</dd>
      <dt>Sursa</dt><dd>{{ ['panel' => 'Panou (criptată în baza de date)', 'env' => 'Fișierul .env (ANTHROPIC_API_KEY)', 'none' => '—'][$source] }}</dd>
      <dt>Model</dt><dd class="mono">{{ $model }}</dd>
    </dl>
    <form method="post" action="{{ route('admin.ai.test') }}" class="ai-inline">@csrf<button class="btn" type="submit" @disabled($source === 'none')>Testează cheia</button><span class="muted small">Verifică cheia și modelul, fără cost.</span></form>

    <form method="post" action="{{ route('admin.ai.key') }}" class="ai-form" autocomplete="off">
      @csrf @method('put')
      <h3>{{ $source === 'panel' ? 'Înlocuiește cheia' : 'Adaugă cheia' }}</h3>
      <div class="fl"><label for="key">Cheia API (începe cu sk-ant-)</label>
        <div class="ai-reveal"><input id="key" type="password" name="key" required autocomplete="off" spellcheck="false" placeholder="sk-ant-…" class="mono"><button type="button" class="btn btn-s" data-reveal-key aria-controls="key">Arată</button></div>
        @error('key')<div class="err">{{ $message }}</div>@enderror</div>
      <div class="fl"><label for="password">Parola contului tău (confirmare)</label><input id="password" type="password" name="password" required autocomplete="current-password">
        @error('password')<div class="err">{{ $message }}</div>@enderror</div>
      <button class="btn btn-p" type="submit">Salvează și testează</button>
      <p class="hint small muted">Cheia se salvează criptat și nu se mai afișează niciodată întreagă. Cheia din panou are prioritate față de cea din <span class="mono">.env</span>.</p>
    </form>
    @if ($source === 'panel')
    <details class="ai-danger"><summary>Șterge cheia din panou</summary>
      <form method="post" action="{{ route('admin.ai.key.clear') }}">@csrf @method('delete')
        <div class="fl"><label for="password2">Parola contului tău</label><input id="password2" type="password" name="password" required autocomplete="current-password"></div>
        <button class="btn btn-danger" type="submit">Șterge cheia</button>
        <p class="hint small muted">După ștergere se folosește cheia din <span class="mono">.env</span>, dacă există; altfel agenții răspund doar din informațiile firmei.</p>
      </form>
    </details>
    @endif
  </section>

  <section class="card ai-help" aria-labelledby="h-h">
    <h2 id="h-h">Cum funcționează</h2>
    <ul class="ai-steps">
      <li><b>Răspunsuri AI pornite</b><span>Agentul site-ului răspunde cu AI, din informațiile firmei. Costul intră în plafonul firmei.</span></li>
      <li><b>Răspunsuri AI oprite</b><span>Agentul răspunde doar din informațiile firmei (întrebări și răspunsuri), fără cost AI.</span></li>
      <li><b>Chat pe site</b><span>Afișează sau ascunde fereastra de chat pe site. Agentul trebuie să fie activ în portalul firmei.</span></li>
    </ul>
  </section>
</div>

<section class="card" aria-labelledby="s-h">
  <div class="ai-sites-head">
    <h2 id="s-h">Site-uri</h2>
    <div class="ai-tools">
      <label class="sr-only" for="ai-q">Caută site sau firmă</label>
      <input id="ai-q" type="search" placeholder="Caută site sau firmă…" data-ai-filter>
      <form method="post" action="{{ route('admin.ai.bulk') }}" onsubmit="return confirm('Pornești răspunsurile AI pe toate site-urile?')">@csrf<input type="hidden" name="value" value="1"><button class="btn btn-s" type="submit">AI pornit peste tot</button></form>
      <form method="post" action="{{ route('admin.ai.bulk') }}" onsubmit="return confirm('Oprești răspunsurile AI pe toate site-urile?')">@csrf<input type="hidden" name="value" value="0"><button class="btn btn-s" type="submit">AI oprit peste tot</button></form>
    </div>
  </div>
  @if ($sites->isEmpty())
    <p class="muted">Niciun site încă. Adaugă un site din pagina unui client.</p>
  @else
  <ul class="ai-sites" role="list">
    @foreach ($sites as $site)
      @php
        $siteAgents = $agents->get($site->id, collect());
        $activeAgent = $siteAgents->first(fn ($a) => $a->isActive());
        $orgCost = ($cost[$site->organization_id] ?? 0) / 1_000_000;
      @endphp
      <li class="ai-site" data-ai-row="{{ mb_strtolower($site->domain.' '.($site->organization?->name ?? '')) }}">
        <div class="ai-site-id">
          <b>{{ $site->domain }}</b>
          <span class="muted small">{{ $site->organization?->name }} · {{ $site->platform?->value ?? $site->platform }}</span>
          <span class="ai-tags">
            @if ($activeAgent)<span class="badge ok">agent activ</span>@elseif ($siteAgents->isNotEmpty())<span class="badge warn">agent inactiv</span>@else<span class="badge">fără agent</span>@endif
            @if ($orgCost > 0)<span class="badge">${{ number_format($orgCost, 2) }} luna aceasta (firma)</span>@endif
            @if ($site->organization)<a class="small" href="{{ route('admin.organizations.show', $site->organization) }}">Firma →</a>@endif
          </span>
        </div>
        @foreach (['ai' => ['Răspunsuri AI', (bool) $site->ai_enabled], 'chat' => ['Chat pe site', (bool) $chat[$site->id]]] as $field => [$label, $on])
        <form method="post" action="{{ route('admin.ai.toggle', $site->id) }}" class="ai-toggle" data-ai-toggle>
          @csrf <input type="hidden" name="field" value="{{ $field }}"><input type="hidden" name="value" value="{{ $on ? 0 : 1 }}">
          <button type="submit" class="sw-btn{{ $on ? ' on' : '' }}" role="switch" aria-checked="{{ $on ? 'true' : 'false' }}" aria-label="{{ $label }} pe {{ $site->domain }}">
            <span class="sw-track" aria-hidden="true"><span class="sw-knob"></span></span>
            <span class="sw-text"><span class="sw-label">{{ $label }}</span><span class="sw-state">{{ $on ? 'pornit' : 'oprit' }}</span></span>
          </button>
        </form>
        @endforeach
      </li>
    @endforeach
  </ul>
  <p class="muted small" data-ai-empty hidden>Niciun site nu se potrivește căutării.</p>
  @endif
</section>

<script>
(function () {
  var q = document.querySelector('[data-ai-filter]');
  if (q) q.addEventListener('input', function () {
    var v = q.value.trim().toLowerCase(), shown = 0;
    document.querySelectorAll('[data-ai-row]').forEach(function (r) { var ok = !v || r.dataset.aiRow.indexOf(v) > -1; r.hidden = !ok; if (ok) shown++; });
    var e = document.querySelector('[data-ai-empty]'); if (e) e.hidden = shown > 0;
  });
  var rk = document.querySelector('[data-reveal-key]');
  if (rk) rk.addEventListener('click', function () { var i = document.getElementById('key'); var t = i.type === 'password'; i.type = t ? 'text' : 'password'; rk.textContent = t ? 'Ascunde' : 'Arată'; });
  // comutatoarele se salvează fără reîncărcarea paginii; fără JS, formularul se trimite normal
  document.querySelectorAll('[data-ai-toggle]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var b = f.querySelector('.sw-btn'), val = f.querySelector('[name=value]');
      b.disabled = true; b.classList.add('busy');
      fetch(f.action, { method: 'POST', body: new FormData(f), headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) throw r; return r.json(); })
        .then(function (j) {
          b.classList.toggle('on', j.on); b.setAttribute('aria-checked', j.on ? 'true' : 'false');
          b.querySelector('.sw-state').textContent = j.on ? 'pornit' : 'oprit'; val.value = j.on ? '0' : '1';
        })
        .catch(function () { f.submit(); })
        .finally(function () { b.disabled = false; b.classList.remove('busy'); });
    });
  });
})();
</script>
@endsection
