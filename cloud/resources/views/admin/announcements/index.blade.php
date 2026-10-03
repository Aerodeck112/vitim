@extends('layouts.app')
@section('title', 'Noutăți către clienți')
@section('content')
<div class="head"><div><h1>Noutăți către clienți</h1><p>Emailuri de la VITIM către firmele din platformă: ce e nou în VITIM AI și ce am lucrat pentru fiecare. Fiecare email include automat lucrările făcute pentru firma care îl primește.</p></div>
  <a class="btn btn-p" href="{{ route('admin.announcements.create') }}">Anunț nou</a></div>

<form method="post" action="{{ route('admin.announcements.settings') }}" class="card">
  @csrf @method('put')
  <h2>Automat</h2>
  <label class="chk"><input type="checkbox" name="auto_updates" value="1" @checked($settings['auto_updates'])> <span><strong>Trimite automat noutățile fiecărei versiuni noi</strong>: după o actualizare a platformei, emailul „Nou în VITIM AI” se face din lista de noutăți a versiunii și pleacă a doua zi la 10:00 (îl poți citi și modifica până atunci). Nebifat: rămâne ciornă și îl trimiți tu.</span></label>
  <label class="chk"><input type="checkbox" name="digest" value="1" @checked($settings['digest'])> <span><strong>Rezumatul lunar automat</strong>, personalizat pe firmă: conversații pe site, cereri noi, abonați noi, mesaje trimise, lucrările VITIM și linkul spre raportul lunar. Firmele fără activitate în luna respectivă nu îl primesc.</span></label>
  <div class="row" style="margin-top:8px">
    <div class="fl"><label for="digest_day">Rezumatul pleacă în ziua</label><input id="digest_day" type="number" name="digest_day" min="1" max="28" value="{{ $settings['digest_day'] }}" style="width:90px"> <span class="small muted">a lunii, la ora 10</span></div>
    <div class="fl"><label for="roles">Primesc</label><select id="roles" name="roles"><option value="owners" @selected($settings['roles'] === 'owners')>proprietarul și administratorii firmei</option><option value="all" @selected($settings['roles'] === 'all')>toți utilizatorii firmei</option></select></div>
  </div>
  <button class="btn" type="submit">Salvează</button>
</form>

<div class="table-wrap"><table>
  <thead><tr><th>Anunț</th><th>Tip</th><th>Stare</th><th>Trimise</th><th>Deschise</th><th>Data</th></tr></thead>
  <tbody>
  @forelse ($items as $a)
    @php($s = $stats[$a->id] ?? null)
    <tr><td><a href="{{ route('admin.announcements.edit', $a->id) }}"><strong>{{ $a->title }}</strong></a>@if ($a->version) <span class="badge">v{{ $a->version }}</span>@endif</td>
      <td class="small">{{ \App\Models\Announcement::KINDS[$a->kind] }}</td>
      <td><span class="badge {{ ['sent' => 'ok', 'scheduled' => 'warn', 'sending' => 'warn', 'cancelled' => 'err'][$a->status] ?? '' }}">{{ \App\Models\Announcement::STATUSES[$a->status] }}</span></td>
      <td>{{ (int) ($s->n ?? 0) }}@if (($s->f ?? 0) > 0) <span class="badge err">{{ $s->f }} eșuate</span>@endif</td>
      <td>{{ ($s->n ?? 0) ? round(100 * $s->o / $s->n).'%' : '—' }}</td>
      <td class="small muted">{{ ($a->sent_at ?? $a->scheduled_at ?? $a->created_at)?->setTimezone('Europe/Bucharest')->format('d.m.Y H:i') }}</td></tr>
  @empty
    <tr><td colspan="6" class="muted">Niciun anunț încă. La următoarea actualizare a platformei apare aici automat o ciornă cu noutățile versiunii.</td></tr>
  @endforelse
  </tbody>
</table></div>
@include('partials.pager', ['paginator' => $items])
@endsection
