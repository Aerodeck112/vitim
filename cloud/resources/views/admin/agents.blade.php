@extends('layouts.app')
@section('title', 'Agenți')
@section('content')
<div class="head"><div><h1>Agenți</h1><p>Agenții AI configurați. Răspunsurile către vizitatori pornesc în Faza 2.</p></div></div>
<div class="table-wrap"><table>
  <thead><tr><th>Agent</th><th>Organizație</th><th>Site</th><th>Status</th><th>Model</th><th>Actualizat</th></tr></thead>
  <tbody>
  @forelse ($agents as $agent)
    <tr><td><a href="{{ route('portal.agents.edit', [$agent->organization->slug, $agent->id]) }}"><strong>{{ $agent->name }}</strong></a></td>
      <td>{{ $agent->organization->name }}</td><td>{{ $agent->site?->domain ?? '—' }}</td>
      <td><span @class(['badge', 'ok' => $agent->status->value === 'active'])>{{ $agent->status->value }}</span></td>
      <td class="mono">{{ $agent->model_configuration['model'] ?? '—' }}</td><td class="small muted">{{ $agent->updated_at?->format('d.m.Y H:i') }}</td></tr>
  @empty
    <tr><td colspan="6" class="empty">Niciun agent.</td></tr>
  @endforelse
  </tbody>
</table></div>
@include('partials.pager', ['paginator' => $agents])
@endsection
