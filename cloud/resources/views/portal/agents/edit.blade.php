@extends('layouts.app')
@section('title', $agent->name)
@section('content')
@php($sys = $agent->system_configuration)
@php($engine = old('engine', $sys['engine'] ?? 'auto'))
@php($hasKey = app(\App\Ai\AiClient::class)->configured())
<div class="head"><div><h1>{{ $agent->name }}</h1><p>status {{ $agent->status->value }} · versiunea {{ $versions->first()?->version ?? 1 }} ·
  {{ $engine === 'local' || ($engine === 'auto' && ! $hasKey) || ($agent->site && ! $agent->site->ai_enabled) ? 'răspunde din informațiile firmei (fără cost AI)' : 'răspunde cu AI' }}@if ($agent->site && ! $agent->site->ai_enabled) <span class="badge warn">AI oprit pe acest site de echipa VITIM</span>@endif</p></div>
  @if ($canManage)<a class="btn btn-p" href="{{ route('portal.agents.test', [$organization->slug, $agent->id]) }}">Testează agentul</a>@endif</div>
@if ($errors->count() > 1)
  <div class="alert alert-err" role="alert">Nu am salvat. Corectează câmpurile marcate cu roșu:<ul style="margin:6px 0 0 18px;padding:0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif
<form method="post" action="{{ route('portal.agents.update', [$organization->slug, $agent->id]) }}" id="agent-form">
  @csrf @method('put')
  <fieldset @disabled(! $canManage) style="border:0;padding:0;margin:0">
  <div class="card">
    <h2>General</h2>
    <div class="row">
      <div class="fl"><label for="name">Nume</label><input id="name" type="text" name="name" value="{{ old('name', $agent->name) }}" required maxlength="160">@error('name')<div class="err">{{ $message }}</div>@enderror</div>
      <div class="fl"><label for="status">Status</label><select id="status" name="status">
        @foreach (['draft' => 'Ciornă', 'active' => 'Activ (răspunde pe site)', 'paused' => 'Oprit'] as $v => $l)<option value="{{ $v }}" @selected(old('status', $agent->status->value) === $v)>{{ $l }}</option>@endforeach</select>@error('status')<div class="err">{{ $message }}</div>@enderror</div>
    </div>
    <div class="row">
      <div class="fl"><label for="site_id">Site</label><select id="site_id" name="site_id"><option value="">—</option>@foreach ($sites as $s)<option value="{{ $s->id }}" @selected((string) old('site_id', $agent->site_id) === (string) $s->id)>{{ $s->domain }}</option>@endforeach</select>@error('site_id')<div class="err">{{ $message }}</div>@enderror</div>
      <div class="fl"><label for="default_language">Limba implicită</label><input id="default_language" type="text" name="default_language" value="{{ old('default_language', $agent->default_language) }}" maxlength="2" placeholder="ro">@error('default_language')<div class="err">{{ $message }}</div>@enderror</div>
    </div>
    <div class="fl"><label for="engine">Cum răspunde agentul</label><select id="engine" name="engine">
      <option value="auto" @selected($engine === 'auto')>Automat: AI dacă e disponibil, altfel din informațiile firmei</option>
      <option value="local" @selected($engine === 'local')>Doar din informațiile firmei (fără AI extern, fără cost)</option>
      <option value="claude" @selected($engine === 'claude')>Doar AI</option></select>
      <div class="hint">@if ($hasKey)Răspunsurile AI sunt disponibile.@else Răspunsurile AI nu sunt disponibile acum: în modul automat agentul caută răspunsul în textul de mai jos, adună datele de contact (cu acordul vizitatorului) și anunță echipa când e nevoie de un om.@endif</div>
      @error('engine')<div class="err">{{ $message }}</div>@enderror</div>
  </div>
  <div class="card">
    <h2>Informații despre firmă</h2>
    <p class="muted small" style="margin-top:-6px">Agentul răspunde doar din ce scrie aici. Scrie istoria firmei, serviciile, prețurile, programul, adresa, livrarea, garanția, întrebările frecvente. Fără AI, răspunsurile sunt găsite cel mai bine dacă scrii pe rânduri de forma <span class="mono">Program: L–V 8–17</span> sau întrebare + răspuns:</p>
    <pre class="code small" style="margin:0 0 12px">Prețuri: cabanele la roșu de la 375 EUR/mp, la cheie de la 690 EUR/mp.
Program: luni–vineri 8–17, sâmbătă 9–13.

Cât durează construcția?
O cabană de 60 mp e gata în 6–8 săptămâni.</pre>
    <div class="fl"><label for="business_facts">Informații</label><textarea id="business_facts" name="business_facts" maxlength="{{ \App\Services\AgentConfiguration::MAX_FACTS }}" rows="16">{{ old('business_facts', $sys['business_facts'] ?? '') }}</textarea>
      <div class="hint"><span id="facts-count">0</span> / {{ number_format(\App\Services\AgentConfiguration::MAX_FACTS, 0, ',', '.') }} caractere</div>@error('business_facts')<div class="err">{{ $message }}</div>@enderror</div>
    <div class="fl"><label for="contact_line">Date de contact afișate când agentul nu poate răspunde</label><input id="contact_line" type="text" name="contact_line" maxlength="300" value="{{ old('contact_line', $sys['contact_line'] ?? '') }}" placeholder="0740 000 000 sau office@firma.ro">@error('contact_line')<div class="err">{{ $message }}</div>@enderror</div>
  </div>
  <div class="card">
    <h2>Comportament</h2>
    <div class="row">
      <div class="fl"><label for="tone">Ton</label><select id="tone" name="tone">@foreach ($tones as $t)<option value="{{ $t }}" @selected(old('tone', $sys['tone'] ?? '') === $t)>{{ ['professional' => 'Profesionist', 'friendly' => 'Prietenos', 'formal' => 'Formal (dumneavoastră)', 'concise' => 'Concis'][$t] ?? $t }}</option>@endforeach</select>@error('tone')<div class="err">{{ $message }}</div>@enderror</div>
      <div class="fl"><label for="languages">Limbi (coduri, separate prin virgulă)</label><input id="languages" type="text" name="languages" value="{{ old('languages', implode(', ', $sys['languages'] ?? [])) }}" placeholder="ro, en">@error('languages')<div class="err">{{ $message }}</div>@enderror @error('languages.*')<div class="err">{{ $message }}</div>@enderror</div>
    </div>
    <div class="fl"><label for="greeting">Mesaj de întâmpinare</label><input id="greeting" type="text" name="greeting" value="{{ old('greeting', $sys['greeting'] ?? '') }}" maxlength="500">@error('greeting')<div class="err">{{ $message }}</div>@enderror</div>
    <div class="fl"><label for="instructions">Instrucțiuni suplimentare (doar pentru răspunsurile AI)</label><textarea id="instructions" name="instructions" maxlength="4000">{{ old('instructions', $sys['instructions'] ?? '') }}</textarea>@error('instructions')<div class="err">{{ $message }}</div>@enderror</div>
    <div class="fl"><label for="fallback_behavior">Când nu știe răspunsul</label><select id="fallback_behavior" name="fallback_behavior">
      @foreach ($fallbacks as $f)<option value="{{ $f }}" @selected(old('fallback_behavior', $sys['fallback_behavior'] ?? '') === $f)>{{ ['collect_contact' => 'Cere datele de contact', 'handoff' => 'Anunță un om din echipă', 'apologize' => 'Spune că nu știe și dă datele de contact'][$f] }}</option>@endforeach</select></div>
  </div>
  <div class="card">
    <h2>Reguli</h2>
    @php($actionsOld = old('allowed_actions', $errors->any() ? [] : ($sys['allowed_actions'] ?? [])))
    @php($fieldsOld = old('required_fields', $errors->any() ? [] : ($sys['lead_rules']['required_fields'] ?? [])))
    <div class="grid">
      <div><div class="lbl">Anunță un om</div>
        @foreach (['on_request' => 'când vizitatorul cere', 'on_complaint' => 'la reclamații', 'on_low_confidence' => 'când nu e sigur'] as $k => $l)
          <label class="chk"><input type="checkbox" name="handoff_{{ $k }}" value="1" @checked($errors->any() ? old("handoff_{$k}") : ($sys['handoff_rules'][$k] ?? false))> {{ $l }}</label>
        @endforeach</div>
      <div><div class="lbl">Acțiuni permise</div>
        @foreach ($actions as $a)<label class="chk"><input type="checkbox" name="allowed_actions[]" value="{{ $a }}" @checked(in_array($a, $actionsOld, true))> {{ ['create_lead' => 'salvează cereri (lead-uri)', 'request_human' => 'anunță echipa', 'search_knowledge' => 'caută în documente'][$a] ?? $a }}</label>@endforeach</div>
      <div><div class="lbl">Date cerute pentru lead</div>
        @foreach ($leadFields as $f)<label class="chk"><input type="checkbox" name="required_fields[]" value="{{ $f }}" @checked(in_array($f, $fieldsOld, true))> {{ ['name' => 'nume', 'phone' => 'telefon', 'email' => 'email', 'company' => 'firmă', 'requested_service' => 'serviciul dorit', 'product' => 'produs', 'budget' => 'buget', 'preferred_date' => 'data preferată', 'notes' => 'detalii'][$f] ?? $f }}</label>@endforeach
        <p class="tag-note">Acordul vizitatorului înainte de salvarea lead-ului e mereu obligatoriu.</p></div>
    </div>
  </div>
  <div class="card" id="widget">
    <h2>Agentul pe site (widget)</h2>
    @if (! $agent->site)
      <p class="muted" style="margin:0">Alege mai sus site-ul pe care răspunde agentul, salvează, apoi aici apar setările chatului.</p>
    @else
      <input type="hidden" name="widget_form" value="1">
      @php($w = \App\Services\WidgetSettings::for($agent->site))
      @php($v = fn (string $k) => old($k, $w[$k]))
      @php($chk = fn (string $k) => $errors->any() ? (bool) old($k) : (bool) $w[$k])
      <p class="small muted" style="margin-top:-6px">Pe <strong>{{ $agent->site->domain }}</strong> chatul apare când agentul e <strong>Activ</strong> și opțiunea de mai jos e bifată. În program, un coleg poate prelua oricând conversația din <a href="{{ route('portal.conversations.index', $organization->slug) }}">Conversații</a>.
        @if ($agent->status->value !== 'active')<span class="badge warn">agentul nu e activ</span>@endif</p>
      <label class="chk"><input type="checkbox" name="enabled" value="1" @checked($chk('enabled'))> Afișează chatul pe site</label>
      <div class="row" style="margin-top:10px">
        <div class="fl"><label for="w-title">Titlul ferestrei</label><input id="w-title" type="text" name="title" maxlength="60" value="{{ $v('title') }}" placeholder="{{ $agent->name }}">@error('title')<div class="err">{{ $message }}</div>@enderror</div>
        <div class="fl"><label for="w-launcher">Textul butonului (la trecerea mouse-ului)</label><input id="w-launcher" type="text" name="launcher" maxlength="40" value="{{ $v('launcher') }}">@error('launcher')<div class="err">{{ $message }}</div>@enderror</div>
      </div>
      <div class="row">
        <div class="fl"><label for="w-color">Culoare</label><input id="w-color" type="color" name="color" value="{{ $v('color') }}" style="height:42px;width:80px;padding:2px"></div>
        <div class="fl"><label for="w-position">Poziție</label><select id="w-position" name="position"><option value="right" @selected($v('position') === 'right')>Dreapta jos</option><option value="left" @selected($v('position') === 'left')>Stânga jos</option></select></div>
      </div>
      <div class="row">
        <div class="fl"><label for="w-wt">Salutul de pe prima pagină</label><input id="w-wt" type="text" name="welcome_title" maxlength="40" value="{{ $v('welcome_title') }}">@error('welcome_title')<div class="err">{{ $message }}</div>@enderror</div>
        <div class="fl"><label for="w-wx">Textul de sub salut</label><input id="w-wx" type="text" name="welcome_text" maxlength="120" value="{{ $v('welcome_text') }}">@error('welcome_text')<div class="err">{{ $message }}</div>@enderror</div>
      </div>
      <div class="fl"><label for="w-qr">Butoane cu întrebări rapide (câte una pe rând, max. 4)</label><textarea id="w-qr" name="quick_replies" rows="4" maxlength="400">{{ old('quick_replies', implode("\n", $w['quick_replies'])) }}</textarea>@error('quick_replies')<div class="err">{{ $message }}</div>@enderror</div>
      <div class="row">
        <div class="fl"><label for="w-pd">Mesaj automat după (secunde, 0 = oprit)</label><input id="w-pd" type="number" name="proactive_delay" min="0" max="300" value="{{ $v('proactive_delay') }}">@error('proactive_delay')<div class="err">{{ $message }}</div>@enderror</div>
        <div class="fl"><label for="w-pt">Mesajul automat de lângă buton</label><input id="w-pt" type="text" name="proactive_text" maxlength="140" value="{{ $v('proactive_text') }}">@error('proactive_text')<div class="err">{{ $message }}</div>@enderror</div>
      </div>
      <div class="row">
        <div class="fl"><label for="w-hs">Echipa e online de la</label><input id="w-hs" type="time" name="hours_start" value="{{ $v('hours_start') }}">@error('hours_start')<div class="err">{{ $message }}</div>@enderror</div>
        <div class="fl"><label for="w-he">până la</label><input id="w-he" type="time" name="hours_end" value="{{ $v('hours_end') }}">@error('hours_end')<div class="err">{{ $message }}</div>@enderror</div>
      </div>
      <div class="fl"><label for="w-privacy">Link spre politica de confidențialitate a site-ului</label><input id="w-privacy" type="text" name="privacy_url" maxlength="255" value="{{ $v('privacy_url') }}" placeholder="https://{{ $agent->site->domain }}/politica-de-confidentialitate">@error('privacy_url')<div class="err">{{ $message }}</div>@enderror</div>
      <div class="fl"><label for="w-av">Poză pentru chat (link https, pătrată; gol = inițiala)</label><input id="w-av" type="text" name="avatar_url" maxlength="255" value="{{ $v('avatar_url') }}" placeholder="https://{{ $agent->site->domain }}/wp-content/uploads/logo.png">@error('avatar_url')<div class="err">{{ $message }}</div>@enderror</div>
      <label class="chk"><input type="checkbox" name="weekends" value="1" @checked($chk('weekends'))> Online și în weekend</label>
      <label class="chk"><input type="checkbox" name="email_capture" value="1" @checked($chk('email_capture'))> În afara programului, cere vizitatorului emailul ca să-i răspundeți</label>
      <label class="chk"><input type="checkbox" name="sound" value="1" @checked($chk('sound'))> Sunet la mesajele noi</label>
    @endif
  </div>
  @if ($canManage)
  <div class="savebar"><span class="small muted" id="dirty">Toate modificările de pe pagină se salvează cu acest buton.</span><button class="btn btn-p" type="submit">Salvează</button></div>
  @endif
  </fieldset>
</form>
@if ($agent->site && ($key = $agent->site->keys()->whereNull('revoked_at')->latest('id')->first()))
  <div class="card" style="margin-top:18px"><h2>Instalare pe site</h2>
    <div class="small muted">WordPress: pluginul VITIM Connector (1.3.0+) adaugă chatul automat. Alte site-uri: pune codul înainte de &lt;/body&gt;:</div>
    <pre class="code">&lt;script src="{{ url('/widget/v1/loader.js') }}" data-site="{{ $key->public_key }}" async&gt;&lt;/script&gt;</pre></div>
@endif
@if ($versions->isNotEmpty())
<div class="card" style="margin-top:18px">
  <h2>Istoric versiuni</h2>
  <table><thead><tr><th>Versiune</th><th>Salvată</th><th>De</th></tr></thead><tbody>
  @foreach ($versions as $ver)<tr><td>{{ $ver->version }}</td><td>{{ $ver->created_at?->format('d.m.Y H:i') }}</td><td>{{ $ver->created_by ? ($authors[$ver->created_by] ?? '—') : 'sistem' }}</td></tr>@endforeach
  </tbody></table>
</div>
@endif
<script>
(function () {
  var form = document.getElementById('agent-form'), facts = document.getElementById('business_facts'), count = document.getElementById('facts-count'), dirty = document.getElementById('dirty'), changed = false;
  function upd() { count.textContent = facts.value.replace(/\r?\n/g, '\r\n').length.toLocaleString('ro-RO'); }
  facts.addEventListener('input', upd); upd();
  form.addEventListener('input', function () { if (!changed && dirty) { changed = true; dirty.textContent = 'Ai modificări nesalvate.'; dirty.style.color = 'var(--warn)'; } });
  form.addEventListener('submit', function () { changed = false; });
  window.addEventListener('beforeunload', function (e) { if (changed) { e.preventDefault(); e.returnValue = ''; } });
  var err = document.querySelector('#agent-form .err'); if (err) err.scrollIntoView({ block: 'center' });
})();
</script>
@endsection
