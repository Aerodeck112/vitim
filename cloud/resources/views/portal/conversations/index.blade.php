@extends('layouts.app')
@section('title', 'Conversații')
@section('content')
<div class="head"><div><h1>Conversații</h1><p>Discuțiile vizitatorilor de pe site cu asistentul AI și cu echipa ta.@if ($pending) <span class="badge warn">{{ $pending }} cer un om</span>@endif @if ($online) <span class="badge ok">{{ $online }} vizitator{{ $online > 1 ? 'i' : '' }} în chat acum</span>@endif</p></div></div>
<div class="tabs">
  <a href="?" @class(['on' => ! $status])>Toate</a>
  <a href="?status=pending" @class(['on' => $status === 'pending'])>Cer un om</a>
  <a href="?status=live" @class(['on' => $status === 'live'])>Live (răspunde echipa)</a>
  <a href="?status=open" @class(['on' => $status === 'open'])>Deschise</a>
  <a href="?status=closed" @class(['on' => $status === 'closed'])>Încheiate</a>
</div>
<div class="table-wrap"><table>
  <thead><tr><th>Ultimul mesaj</th><th>Vizitator</th><th>Site</th><th>Mesaje</th><th>Stare</th></tr></thead>
  <tbody>
  @forelse ($conversations as $c)
    @php($unread = $c->last_inbound_at && (! $c->staff_read_at || \Illuminate\Support\Carbon::parse($c->last_inbound_at)->gt($c->staff_read_at)))
    <tr><td class="small"><a href="{{ route('portal.conversations.show', [$organization->slug, $c->id]) }}">@if ($unread)<strong>@endif{{ $c->last_message_at?->format('d.m.Y H:i') ?? $c->created_at->format('d.m.Y H:i') }}@if ($unread)</strong>@endif</a></td>
      <td>@if ($c->visitorOnline())<span class="dot on" title="pe site acum"></span>@endif{{ $c->contact?->displayName() ?? 'Anonim' }}</td><td class="small">{{ $c->site?->domain ?? '—' }}</td><td>{{ $c->messages_count }}</td>
      <td>@if ($unread)<span class="badge err">necitit</span>@endif @if ($c->isLive() && $c->status->value !== 'closed')<span class="badge ok">live</span>@endif @if ($c->lead)<span class="badge ok">lead</span>@endif @if ($c->status->value === 'pending')<span class="badge warn">cere un om</span>@endif @if ($c->status->value === 'closed')<span class="badge">încheiată</span>@endif</td></tr>
  @empty
    <tr><td colspan="5" class="empty">Nicio conversație încă. Apar aici după ce agentul e activ pe site.</td></tr>
  @endforelse
  </tbody>
</table></div>
@include('partials.pager', ['paginator' => $conversations])
@endsection
