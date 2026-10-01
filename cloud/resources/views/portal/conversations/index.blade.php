@extends('layouts.app')
@section('title', 'Conversații')
@section('content')
<div class="head"><div><h1>Conversații</h1><p>Discuțiile vizitatorilor cu asistentul AI de pe site.@if ($pending) <span class="badge warn">{{ $pending }} cer un om</span>@endif</p></div></div>
<div class="tabs">
  <a href="?" @class(['on' => ! $status])>Toate</a>
  <a href="?status=pending" @class(['on' => $status === 'pending'])>Cer un om</a>
  <a href="?status=open" @class(['on' => $status === 'open'])>Deschise</a>
</div>
<div class="table-wrap"><table>
  <thead><tr><th>Ultimul mesaj</th><th>Vizitator</th><th>Site</th><th>Mesaje</th><th>Rezultat</th></tr></thead>
  <tbody>
  @forelse ($conversations as $c)
    <tr><td class="small"><a href="{{ route('portal.conversations.show', [$organization->slug, $c->id]) }}">{{ $c->last_message_at?->format('d.m.Y H:i') ?? $c->created_at->format('d.m.Y H:i') }}</a></td>
      <td>{{ $c->contact?->displayName() ?? 'Anonim' }}</td><td class="small">{{ $c->site?->domain ?? '—' }}</td><td>{{ $c->messages_count }}</td>
      <td>@if ($c->lead)<span class="badge ok">lead</span>@endif @if ($c->status->value === 'pending')<span class="badge warn">cere un om</span>@endif</td></tr>
  @empty
    <tr><td colspan="5" class="empty">Nicio conversație încă. Apar aici după ce agentul e activ pe site.</td></tr>
  @endforelse
  </tbody>
</table></div>
@include('partials.pager', ['paginator' => $conversations])
@endsection
