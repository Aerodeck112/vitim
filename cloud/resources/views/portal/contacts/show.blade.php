@extends('layouts.app')
@section('title', $contact->displayName())
@section('content')
@php($statusLabels = ['granted' => 'acordat', 'revoked' => 'retras', 'unknown' => 'necunoscut'])
<div class="head">
  <div><h1>{{ $contact->displayName() }}</h1><p>Sursă: {{ $contact->source->value }} · adăugat {{ $contact->created_at->format('d.m.Y H:i') }}</p></div>
  <div class="inline">
    @if ($can['manage'])<a class="btn" href="{{ route('portal.contacts.edit', [$organization->slug, $contact->id]) }}">Editează</a>@endif
    @if ($can['delete'])
      <form method="post" action="{{ route('portal.contacts.destroy', [$organization->slug, $contact->id]) }}" onsubmit="return confirm('Ștergi definitiv contactul, consimțămintele și lead-urile lui?')">
        @csrf @method('delete')<button class="btn btn-d" type="submit">Șterge (GDPR)</button></form>
    @endif
  </div>
</div>
<div class="card">
  <h2>Date</h2>
  <dl class="kv">
    <dt>Email</dt><dd>{{ $contact->email ?? '—' }}</dd>
    <dt>Telefon</dt><dd>{{ $contact->phone ?? '—' }}</dd>
    <dt>Firmă</dt><dd>{{ $contact->company ?? '—' }}</dd>
    <dt>Identități</dt><dd>@forelse ($contact->identities as $i)<span class="badge">{{ $i->type->value }}{{ $i->provider ? ':'.$i->provider : '' }}</span> <span class="mono">{{ $i->normalized_value }}</span><br>@empty — @endforelse</dd>
  </dl>
</div>
<div class="card">
  <h2>Consimțământ</h2>
  <div class="table-wrap"><table>
    <thead><tr><th>Canal</th>@foreach ($purposes as $p)<th>{{ $p->value }}</th>@endforeach</tr></thead>
    <tbody>@foreach ($consents as $channel => $row)<tr><td>{{ $channel }}</td>@foreach ($row as $status)
      <td><span @class(['badge', 'ok' => $status->value === 'granted', 'err' => $status->value === 'revoked'])>{{ $statusLabels[$status->value] }}</span></td>@endforeach</tr>@endforeach</tbody>
  </table></div>
  @if ($can['consent'])
    <form method="post" action="{{ route('portal.contacts.consent', [$organization->slug, $contact->id]) }}" class="toolbar" style="margin-top:14px">
      @csrf
      <select name="channel" aria-label="Canal">@foreach ($channels as $c)<option value="{{ $c->value }}">{{ $c->value }}</option>@endforeach</select>
      <select name="purpose" aria-label="Scop">@foreach ($purposes as $p)<option value="{{ $p->value }}">{{ $p->value }}</option>@endforeach</select>
      <select name="status" aria-label="Status">@foreach ($statuses as $s)<option value="{{ $s->value }}">{{ $statusLabels[$s->value] }}</option>@endforeach</select>
      <input type="text" name="source" placeholder="Sursă (ex. telefon, formular)" required maxlength="40" aria-label="Sursă">
      <button class="btn" type="submit">Înregistrează</button>
    </form>
  @endif
  @if ($history->isNotEmpty())
    <details style="margin-top:12px"><summary class="small">Istoric ({{ $history->count() }})</summary>
      <div class="table-wrap" style="margin-top:8px"><table><thead><tr><th>Data</th><th>Canal</th><th>Scop</th><th>Status</th><th>Sursă</th><th>IP</th></tr></thead><tbody>
      @foreach ($history as $h)<tr><td class="small">{{ $h->occurred_at->format('d.m.Y H:i') }}</td><td>{{ $h->channel->value }}</td><td>{{ $h->purpose->value }}</td><td>{{ $statusLabels[$h->status->value] }}</td><td>{{ $h->source }}</td><td class="mono">{{ $h->ip_address ?? '—' }}</td></tr>@endforeach
      </tbody></table></div></details>
  @endif
</div>
<div class="card">
  <h2>Lead-uri</h2>
  @forelse ($leads as $lead)
    <p style="margin:6px 0"><span class="badge">{{ $lead->status->label() }}</span> <span class="mono">{{ $lead->intent->value }}</span> — {{ $lead->summary ?? 'fără rezumat' }} <span class="small muted">{{ $lead->created_at->format('d.m.Y') }}</span></p>
  @empty
    <p class="muted">Niciun lead.</p>
  @endforelse
  @if ($can['leads'])
    <form method="post" action="{{ route('portal.leads.store', $organization->slug) }}" style="margin-top:12px">
      @csrf <input type="hidden" name="contact_id" value="{{ $contact->id }}">
      <div class="row">
        <div class="fl"><label for="intent">Intenție</label><select id="intent" name="intent">@foreach ($intents as $i)<option value="{{ $i->value }}">{{ $i->value }}</option>@endforeach</select></div>
        <div class="fl"><label for="summary">Rezumat</label><input id="summary" type="text" name="summary" maxlength="500"></div>
      </div>
      <button class="btn" type="submit">Adaugă lead</button>
    </form>
  @endif
</div>
@endsection
