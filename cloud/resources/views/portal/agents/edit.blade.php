@extends('layouts.app')
@section('title', $agent->name)
@section('content')
@php($sys = $agent->system_configuration)
<div class="head"><div><h1>{{ $agent->name }}</h1><p>Model {{ $agent->model_configuration['model'] ?? '—' }} · status {{ $agent->status->value }} · versiunea {{ $versions->first()?->version ?? 1 }}</p></div>
  @if ($canManage)<a class="btn btn-p" href="{{ route('portal.agents.test', [$organization->slug, $agent->id]) }}">Testează agentul</a>@endif</div>
<form method="post" action="{{ route('portal.agents.update', [$organization->slug, $agent->id]) }}">
  @csrf @method('put')
  <fieldset @disabled(! $canManage) style="border:0;padding:0;margin:0">
  <div class="card">
    <h2>General</h2>
    <div class="row">
      <div class="fl"><label for="name">Nume</label><input id="name" type="text" name="name" value="{{ old('name', $agent->name) }}" required></div>
      <div class="fl"><label for="status">Status</label><select id="status" name="status">
        @foreach (['draft' => 'Ciornă', 'active' => 'Activ', 'paused' => 'Oprit'] as $v => $l)<option value="{{ $v }}" @selected($agent->status->value === $v)>{{ $l }}</option>@endforeach</select></div>
    </div>
    <div class="row">
      <div class="fl"><label for="site_id">Site</label><select id="site_id" name="site_id"><option value="">—</option>@foreach ($sites as $s)<option value="{{ $s->id }}" @selected($agent->site_id === $s->id)>{{ $s->domain }}</option>@endforeach</select></div>
      <div class="fl"><label for="default_language">Limba implicită</label><input id="default_language" type="text" name="default_language" value="{{ $agent->default_language }}" maxlength="2"></div>
    </div>
  </div>
  <div class="card">
    <h2>Informații despre firmă</h2>
    <p class="muted small" style="margin-top:-6px">Agentul răspunde doar din ce scrie aici: servicii, prețuri, program, zone, politici, întrebări frecvente. Ce nu apare aici, nu inventează.</p>
    <div class="fl"><label for="business_facts">Informații</label><textarea id="business_facts" name="business_facts" maxlength="20000" rows="14" placeholder="Ex: Servicii: schimb distribuție (de la 900 lei manopera), ITP, diagnoză computerizată…&#10;Program: L–V 8–17, sâmbătă 9–13.&#10;Adresă: …">{{ old('business_facts', $sys['business_facts'] ?? '') }}</textarea></div>
    <div class="fl"><label for="contact_line">Date de contact afișate când agentul nu poate răspunde</label><input id="contact_line" type="text" name="contact_line" maxlength="300" value="{{ old('contact_line', $sys['contact_line'] ?? '') }}" placeholder="0740 000 000 sau office@firma.ro"></div>
  </div>
  <div class="card">
    <h2>Comportament</h2>
    <div class="row">
      <div class="fl"><label for="tone">Ton</label><select id="tone" name="tone">@foreach ($tones as $t)<option value="{{ $t }}" @selected(($sys['tone'] ?? '') === $t)>{{ $t }}</option>@endforeach</select></div>
      <div class="fl"><label for="languages">Limbi (coduri, separate prin virgulă)</label><input id="languages" type="text" name="languages" value="{{ implode(', ', $sys['languages'] ?? []) }}"></div>
    </div>
    <div class="fl"><label for="greeting">Mesaj de întâmpinare</label><input id="greeting" type="text" name="greeting" value="{{ $sys['greeting'] ?? '' }}" maxlength="500"></div>
    <div class="fl"><label for="instructions">Instrucțiuni suplimentare</label><textarea id="instructions" name="instructions" maxlength="4000">{{ $sys['instructions'] ?? '' }}</textarea></div>
    <div class="fl"><label for="fallback_behavior">Când nu știe răspunsul</label><select id="fallback_behavior" name="fallback_behavior">
      @foreach ($fallbacks as $f)<option value="{{ $f }}" @selected(($sys['fallback_behavior'] ?? '') === $f)>{{ ['collect_contact' => 'Cere datele de contact', 'handoff' => 'Transferă la un om', 'apologize' => 'Spune că nu știe'][$f] }}</option>@endforeach</select></div>
  </div>
  <div class="card">
    <h2>Reguli</h2>
    <div class="grid">
      <div><div class="lbl">Transfer la om</div>
        @foreach (['on_request' => 'la cererea vizitatorului', 'on_complaint' => 'la reclamații', 'on_low_confidence' => 'când nu e sigur'] as $k => $l)
          <label class="chk"><input type="checkbox" name="handoff_{{ $k }}" value="1" @checked($sys['handoff_rules'][$k] ?? false)> {{ $l }}</label>
        @endforeach</div>
      <div><div class="lbl">Acțiuni permise</div>
        @foreach ($actions as $a)<label class="chk"><input type="checkbox" name="allowed_actions[]" value="{{ $a }}" @checked(in_array($a, $sys['allowed_actions'] ?? [], true))> <span class="mono">{{ $a }}</span></label>@endforeach</div>
      <div><div class="lbl">Date cerute pentru lead</div>
        @foreach ($leadFields as $f)<label class="chk"><input type="checkbox" name="required_fields[]" value="{{ $f }}" @checked(in_array($f, $sys['lead_rules']['required_fields'] ?? [], true))> {{ $f }}</label>@endforeach
        <p class="tag-note">Acordul vizitatorului înainte de salvarea lead-ului e mereu obligatoriu.</p></div>
    </div>
  </div>
  @if ($canManage)<button class="btn btn-p" type="submit">Salvează</button>@endif
  </fieldset>
</form>
<div class="card" style="margin-top:18px" id="widget">
  <h2>Agentul pe site (widget)</h2>
  @if (! $agent->site)
    <p class="muted" style="margin:0">Alege mai sus site-ul pe care răspunde agentul, apoi revino aici.</p>
  @else
    @php($w = \App\Services\WidgetSettings::for($agent->site))
    @php($key = $agent->site->keys()->whereNull('revoked_at')->latest('id')->first())
    <p class="small muted" style="margin-top:-6px">Pe <strong>{{ $agent->site->domain }}</strong> widgetul apare când agentul e <strong>Activ</strong> și opțiunea de mai jos e bifată. Asistentul AI răspunde non-stop; în program, un coleg poate prelua oricând conversația din <a href="{{ route('portal.conversations.index', $organization->slug) }}">Conversații</a>.
      @if ($agent->status->value !== 'active')<span class="badge warn">agentul nu e activ</span>@endif</p>
    <form method="post" action="{{ route('portal.agents.widget', [$organization->slug, $agent->id]) }}">
      @csrf @method('put')
      <fieldset @disabled(! $canManage) style="border:0;padding:0;margin:0">
      <label class="chk"><input type="checkbox" name="enabled" value="1" @checked($w['enabled'])> Afișează asistentul pe site</label>
      <div class="row" style="margin-top:10px">
        <div class="fl"><label for="w-title">Titlul ferestrei</label><input id="w-title" type="text" name="title" maxlength="60" value="{{ $w['title'] }}" placeholder="{{ $agent->name }}"></div>
        <div class="fl"><label for="w-launcher">Textul butonului</label><input id="w-launcher" type="text" name="launcher" maxlength="40" value="{{ $w['launcher'] }}"></div>
      </div>
      <div class="row">
        <div class="fl"><label for="w-color">Culoare</label><input id="w-color" type="color" name="color" value="{{ $w['color'] }}" style="height:42px;width:80px;padding:2px"></div>
        <div class="fl"><label for="w-position">Poziție</label><select id="w-position" name="position"><option value="right" @selected($w['position'] === 'right')>Dreapta jos</option><option value="left" @selected($w['position'] === 'left')>Stânga jos</option></select></div>
      </div>
      <div class="fl"><label for="w-privacy">Link spre politica de confidențialitate a site-ului</label><input id="w-privacy" type="text" name="privacy_url" maxlength="255" value="{{ $w['privacy_url'] }}" placeholder="https://{{ $agent->site->domain }}/politica-de-confidentialitate"></div>
      <div class="row">
        <div class="fl"><label for="w-wt">Salutul de pe prima pagină</label><input id="w-wt" type="text" name="welcome_title" maxlength="40" value="{{ $w['welcome_title'] }}"></div>
        <div class="fl"><label for="w-wx">Textul de sub salut</label><input id="w-wx" type="text" name="welcome_text" maxlength="120" value="{{ $w['welcome_text'] }}"></div>
      </div>
      <div class="fl"><label for="w-qr">Butoane cu întrebări rapide (câte una pe rând, max. 4)</label><textarea id="w-qr" name="quick_replies" rows="4" maxlength="400">{{ implode("\n", $w['quick_replies']) }}</textarea></div>
      <div class="row">
        <div class="fl"><label for="w-pd">Mesaj automat după (secunde, 0 = oprit)</label><input id="w-pd" type="number" name="proactive_delay" min="0" max="300" value="{{ $w['proactive_delay'] }}"></div>
        <div class="fl"><label for="w-pt">Mesajul automat de lângă buton</label><input id="w-pt" type="text" name="proactive_text" maxlength="140" value="{{ $w['proactive_text'] }}"></div>
      </div>
      <div class="row">
        <div class="fl"><label for="w-hs">Echipa e online de la</label><input id="w-hs" type="time" name="hours_start" value="{{ $w['hours_start'] }}"></div>
        <div class="fl"><label for="w-he">până la</label><input id="w-he" type="time" name="hours_end" value="{{ $w['hours_end'] }}"></div>
      </div>
      <div class="fl"><label for="w-av">Poză pentru chat (link https, pătrată; gol = inițiala)</label><input id="w-av" type="text" name="avatar_url" maxlength="255" value="{{ $w['avatar_url'] }}" placeholder="https://{{ $agent->site->domain }}/wp-content/uploads/logo.png"></div>
      <label class="chk"><input type="checkbox" name="weekends" value="1" @checked($w['weekends'])> Online și în weekend</label>
      <label class="chk"><input type="checkbox" name="email_capture" value="1" @checked($w['email_capture'])> În afara programului, cere vizitatorului emailul ca să-i răspundeți</label>
      <label class="chk" style="margin-bottom:12px"><input type="checkbox" name="sound" value="1" @checked($w['sound'])> Sunet la mesajele noi</label>
      @if ($canManage)<button class="btn btn-p" type="submit">Salvează widgetul</button>@endif
      </fieldset>
    </form>
    @if ($key)
      <div class="fl" style="margin-top:14px"><label>Instalare</label>
        <div class="small muted">WordPress: pluginul VITIM Connector (1.3.0+) îl adaugă automat. Alte site-uri: pune codul înainte de &lt;/body&gt;:</div>
        <pre class="code">&lt;script src="{{ url('/widget/v1/loader.js') }}" data-site="{{ $key->public_key }}" async&gt;&lt;/script&gt;</pre></div>
    @endif
  @endif
</div>
@if ($versions->isNotEmpty())
<div class="card" style="margin-top:18px">
  <h2>Istoric versiuni</h2>
  <table><thead><tr><th>Versiune</th><th>Salvată</th><th>De</th></tr></thead><tbody>
  @foreach ($versions as $v)<tr><td>{{ $v->version }}</td><td>{{ $v->created_at?->format('d.m.Y H:i') }}</td><td>{{ $v->created_by ? ($authors[$v->created_by] ?? '—') : 'sistem' }}</td></tr>@endforeach
  </tbody></table>
</div>
@endif
@endsection
