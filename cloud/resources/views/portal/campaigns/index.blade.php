@extends('layouts.app')
@section('title', 'Campanii')
@section('content')
<div class="head"><div><h1>Campanii</h1><p>Email, SMS și WhatsApp către contactele care și-au dat acordul. Fiecare campanie trece prin test și aprobare înainte să plece.</p></div>
  <a class="btn" href="{{ route('portal.channels', $organization->slug) }}">Canale de trimitere</a></div>
@php($labels = ['email' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'])
@if ($accounts->isEmpty())
  <div class="alert alert-warn">Niciun canal conectat încă. Începe din <a href="{{ route('portal.channels', $organization->slug) }}">Canale de trimitere</a>: contul de email al firmei, SMSLink sau WhatsApp Business.</div>
@endif
<form method="post" action="{{ route('portal.campaigns.store', $organization->slug) }}" class="card" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  @csrf
  <div class="fl" style="margin:0;flex:1;min-width:220px"><label for="name">Campanie nouă</label><input id="name" type="text" name="name" required maxlength="160" placeholder="ex. Ofertă de primăvară"></div>
  <div class="fl" style="margin:0"><label for="channel">Canal</label><select id="channel" name="channel">@foreach ($labels as $k => $l)<option value="{{ $k }}">{{ $l }}{{ isset($accounts[$k]) ? '' : ' (neconectat)' }}</option>@endforeach</select></div>
  <button class="btn btn-p" type="submit">Creează</button>
</form>
<div class="table-wrap"><table>
  <thead><tr><th>Campanie</th><th>Canal</th><th>Stare</th><th>Trimise</th><th>Eșuate</th><th>Excluse</th><th>Dezabonați</th><th>Data</th></tr></thead>
  <tbody>
  @forelse ($campaigns as $c)
    @php($s = $stats[$c->id] ?? collect())
    <tr><td><a href="{{ route('portal.campaigns.show', [$organization->slug, $c->id]) }}"><strong>{{ $c->name }}</strong></a></td>
      <td>{{ $labels[$c->channel->value] }}</td>
      <td><span class="badge {{ ['completed' => 'ok', 'sending' => 'ok', 'paused' => 'warn', 'scheduled' => 'warn', 'cancelled' => 'err'][$c->status] ?? '' }}">{{ \App\Models\Campaign::STATUSES[$c->status] }}</span></td>
      <td>{{ ($s['sent'] ?? 0) + ($s['delivered'] ?? 0) + ($s['read'] ?? 0) + ($s['unsubscribed'] ?? 0) }}</td><td>{{ $s['failed'] ?? 0 }}</td><td>{{ $s['excluded'] ?? 0 }}</td><td>{{ $s['unsubscribed'] ?? 0 }}</td>
      <td class="small muted">{{ ($c->completed_at ?? $c->scheduled_at ?? $c->created_at)->setTimezone('Europe/Bucharest')->format('d.m.Y H:i') }}</td></tr>
  @empty
    <tr><td colspan="8" class="empty">Nicio campanie încă.</td></tr>
  @endforelse
  </tbody>
</table></div>
@include('partials.pager', ['paginator' => $campaigns])
@endsection
