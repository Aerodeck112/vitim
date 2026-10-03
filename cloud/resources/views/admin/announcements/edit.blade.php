@extends('layouts.app')
@section('title', $a->exists ? $a->title : 'Anunț nou')
@section('content')
@php($aud = (array) ($a->audience ?? []))
@php($editable = ! $a->exists || $a->editable())
<div class="head"><div><h1>{{ $a->exists ? $a->title : 'Anunț nou' }}</h1>
  @if ($a->exists)<p>{{ \App\Models\Announcement::KINDS[$a->kind] }} · <span class="badge">{{ \App\Models\Announcement::STATUSES[$a->status] }}</span>
    @if ($a->status === 'scheduled' && $a->scheduled_at) · pleacă la {{ $a->scheduled_at->setTimezone('Europe/Bucharest')->format('d.m.Y H:i') }}@endif
    @if ($recipients !== null) · <strong>{{ $recipients }}</strong> destinatari @endif</p>@endif</div>
  <a class="btn" href="{{ route('admin.announcements.index') }}">Toate noutățile</a></div>
@error('send')<div class="alert alert-err">{{ $message }}</div>@enderror

<div class="grid" style="grid-template-columns:minmax(0,1fr) minmax(0,1fr);align-items:start">
<form method="post" action="{{ $a->exists ? route('admin.announcements.update', $a->id) : route('admin.announcements.store') }}" class="card">
  @csrf @if ($a->exists) @method('put') @endif
  <fieldset @disabled(! $editable) style="border:0;padding:0;margin:0">
  @if ($a->kind !== 'digest')
  <div class="fl"><label for="kind">Tip</label><select id="kind" name="kind"><option value="news" @selected($a->kind === 'news')>Anunț (ofertă, serviciu nou, sfat)</option><option value="update" @selected($a->kind === 'update')>Noutăți în platformă</option></select></div>
  @else<input type="hidden" name="kind" value="news">@endif
  <div class="fl"><label for="title">Titlu (în email și în panoul clientului)</label><input id="title" type="text" name="title" maxlength="160" required value="{{ old('title', $a->title) }}">@error('title')<div class="err">{{ $message }}</div>@enderror</div>
  <div class="fl"><label for="subject">Subiectul emailului</label><input id="subject" type="text" name="subject" maxlength="200" required value="{{ old('subject', $a->subject) }}" placeholder="ex. Nou în VITIM AI: cookie-uri pe site-ul tău">@error('subject')<div class="err">{{ $message }}</div>@enderror</div>
  <div class="fl"><label for="intro">Introducere</label><textarea id="intro" name="intro" rows="2" maxlength="1000">{{ old('intro', $a->intro) }}</textarea></div>
  @if ($a->kind !== 'digest')
  <div class="fl"><label for="body">Conținut</label><textarea id="body" name="body" rows="14" maxlength="20000" class="mono" style="font-size:13px">{{ old('body', $a->body) }}</textarea>
    <div class="hint">Paragrafele se despart printr-un rând gol. <span class="mono">- </span> la început de rând = listă, <span class="mono">**text**</span> = îngroșat, <span class="mono">## Titlu</span> = subtitlu, <span class="mono">[text](https://…)</span> = link. Variabile: <span class="mono">@{{firma}}</span>, <span class="mono">@{{prenume}}</span>.</div></div>
  @endif
  <div class="row">
    <div class="fl"><label for="cta_label">Buton</label><input id="cta_label" type="text" name="cta_label" maxlength="60" value="{{ old('cta_label', $a->cta_label) }}"></div>
    <div class="fl"><label for="cta_url">Link buton</label><input id="cta_url" type="url" name="cta_url" maxlength="300" value="{{ old('cta_url', $a->cta_url) }}">@error('cta_url')<div class="err">{{ $message }}</div>@enderror</div>
  </div>
  @if ($a->kind !== 'digest')<label class="chk"><input type="checkbox" name="include_work" value="1" @checked(old('include_work', $a->include_work))> Adaugă blocul personal „Ce am făcut pentru firma ta” (lucrările vizibile clientului de la ultimul email)</label>@else<input type="hidden" name="include_work" value="1">@endif
  <h2 style="margin-top:16px">Cui pleacă</h2>
  <div class="lbl">Firme cu serviciile (nimic bifat = toate firmele active)</div>
  @foreach ($services as $key => [$label])<label class="chk"><input type="checkbox" name="services[]" value="{{ $key }}" @checked(in_array($key, $aud['services'] ?? [], true))> {{ $label }}</label>@endforeach
  <div class="fl" style="margin-top:8px"><label for="roles">Utilizatori</label><select id="roles" name="roles"><option value="owners" @selected(($aud['roles'] ?? 'owners') === 'owners')>proprietarul și administratorii</option><option value="all" @selected(($aud['roles'] ?? '') === 'all')>toți utilizatorii firmei</option></select>
    <div class="hint">Cine a apăsat „Nu mai vreau aceste emailuri” nu primește.</div></div>
  @if ($editable)<button class="btn btn-p" type="submit">Salvează</button>@endif
  </fieldset>
</form>

<div>
  @if ($a->exists)
  <div class="card">
    <h2>Previzualizare</h2>
    <form method="get" class="toolbar" style="margin-bottom:8px" onsubmit="document.getElementById('pv').src='{{ route('admin.announcements.preview', $a->id) }}?org='+this.org.value;return false">
      <select name="org" aria-label="Firma pentru previzualizare" style="width:auto;min-width:200px">@foreach ($organizations as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach</select><button class="btn btn-s" type="submit">Vezi pentru firma aleasă</button></form>
    <iframe id="pv" title="Previzualizare" src="{{ route('admin.announcements.preview', $a->id) }}" sandbox style="width:100%;height:560px;border:1px solid var(--border);border-radius:10px;background:#f4f6fb"></iframe>
  </div>
  @if ($editable)
  <div class="card">
    <h2>1. Trimite-ți un test</h2>
    <form method="post" action="{{ route('admin.announcements.test', $a->id) }}">@csrf<button class="btn" type="submit">Trimite testul la {{ auth()->user()->email }}</button></form>
  </div>
  <div class="card" style="border-color:var(--brand)">
    <h2>2. Programează</h2>
    <form method="post" action="{{ route('admin.announcements.send', $a->id) }}">@csrf
      <div class="fl"><label for="when">Când (gol = acum)</label><input id="when" type="datetime-local" name="when" value="{{ $a->scheduled_at?->setTimezone('Europe/Bucharest')->format('Y-m-d\TH:i') }}"></div>
      <label class="chk"><input type="checkbox" name="confirm" value="1"> Am verificat testul. Trimite către {{ $recipients }} destinatari.</label>@error('confirm')<div class="err">{{ $message }}</div>@enderror
      <button class="btn btn-p" type="submit" @disabled(! $recipients)>{{ $a->status === 'scheduled' ? 'Reprogramează' : 'Programează trimiterea' }}</button>
    </form>
    @if ($a->status === 'scheduled')<form method="post" action="{{ route('admin.announcements.cancel', $a->id) }}" style="margin-top:8px">@csrf<button class="btn btn-s" type="submit">Oprește (înapoi la ciornă)</button></form>@endif
  </div>
  @if ($a->status === 'draft')<form method="post" action="{{ route('admin.announcements.destroy', $a->id) }}" onsubmit="return confirm('Ștergi ciorna?')">@csrf @method('delete')<button class="btn btn-d btn-s" type="submit">Șterge ciorna</button></form>@endif
  @elseif ($a->status === 'sending')
    <form method="post" action="{{ route('admin.announcements.cancel', $a->id) }}" class="card">@csrf<p class="small" style="margin-top:0">Se trimite în tranșe de câte {{ \App\Services\Announcements::PER_RUN }} la 5 minute.</p><button class="btn btn-d" type="submit">Oprește trimiterea</button></form>
  @endif
  @else
  <div class="card"><p class="small muted" style="margin:0">Salvează ciorna ca să vezi previzualizarea cu datele unui client, să-ți trimiți un test și să o programezi.</p></div>
  @endif
</div>
</div>

@if ($deliveries->isNotEmpty())
<div class="card">
  <h2>Livrări</h2>
  <div class="table-wrap"><table>
    <thead><tr><th>Firmă</th><th>Email</th><th>Stare</th><th>Trimis</th><th>Deschis</th></tr></thead>
    <tbody>@foreach ($deliveries as $d)
      <tr><td>{{ $d->organization?->name }}</td><td class="small mono">{{ $d->email }}</td>
        <td><span class="badge {{ $d->status === 'sent' ? 'ok' : 'err' }}">{{ $d->status === 'sent' ? 'trimis' : 'eșuat' }}</span> <span class="small muted">{{ $d->error }}</span></td>
        <td class="small muted">{{ $d->sent_at?->setTimezone('Europe/Bucharest')->format('d.m H:i') }}</td><td>{{ $d->opened_at ? '✓' : '' }}</td></tr>
    @endforeach</tbody>
  </table></div>
</div>
@endif
@endsection
