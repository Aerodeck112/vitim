@extends('layouts.app')
@section('title', 'Agent AI')
@section('content')
<div class="head"><div><h1>Agent AI</h1><p>Configurează agentul, testează-l din panou, apoi pornește-l pe site.</p></div></div>
<div class="table-wrap" style="margin-bottom:18px"><table>
  <thead><tr><th>Agent</th><th>Site</th><th>Status</th><th>Limbă</th></tr></thead>
  <tbody>
  @forelse ($agents as $agent)
    <tr><td><a href="{{ route('portal.agents.edit', [$organization->slug, $agent->id]) }}"><strong>{{ $agent->name }}</strong></a></td>
      <td>{{ $agent->site?->domain ?? '—' }}</td><td><span class="badge">{{ $agent->status->value }}</span></td><td>{{ $agent->default_language }}</td></tr>
  @empty
    <tr><td colspan="4" class="empty">Niciun agent.</td></tr>
  @endforelse
  </tbody>
</table></div>
@if ($canManage)
<form method="post" action="{{ route('portal.agents.store', $organization->slug) }}" class="card" style="max-width:640px">
  @csrf
  <h2>Agent nou</h2>
  <div class="row">
    <div class="fl"><label for="name">Nume</label><input id="name" type="text" name="name" value="{{ old('name') }}" required></div>
    <div class="fl"><label for="site_id">Site</label><select id="site_id" name="site_id"><option value="">—</option>@foreach ($sites as $s)<option value="{{ $s->id }}">{{ $s->domain }}</option>@endforeach</select></div>
  </div>
  <div class="fl"><label for="template">Pornește de la</label><select id="template" name="template">@foreach ($templates as $key => $t)<option value="{{ $key }}">{{ $t['label'] }}</option>@endforeach</select>
    <div class="hint">Completează tonul, instrucțiunile și datele cerute pentru lead. Le poți schimba oricând.</div></div>
  <button class="btn btn-p" type="submit">Creează agentul</button>
</form>
@endif
@endsection
