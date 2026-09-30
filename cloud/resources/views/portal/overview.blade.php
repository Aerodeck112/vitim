@extends('layouts.app')
@section('title', $organization->name)
@section('content')
<div class="head"><div><h1>{{ $organization->name }}</h1><p>Prezentare generală.</p></div></div>
<div class="grid" style="margin-bottom:18px">
  @foreach ($kpi as [$label, $value])<div class="kpi"><small>{{ $label }}</small><b>{{ $value }}</b></div>@endforeach
</div>
<div class="card">
  <h2>Agent AI</h2>
  @forelse ($agents as $agent)
    <p style="margin:6px 0"><a href="{{ route('portal.agents.edit', [$organization->slug, $agent->id]) }}"><strong>{{ $agent->name }}</strong></a> <span class="badge">{{ $agent->status->value }}</span></p>
  @empty
    <p class="muted" style="margin:0">Niciun agent configurat. <a href="{{ route('portal.agents.index', $organization->slug) }}">Configurează agentul</a></p>
  @endforelse
  <p class="tag-note" style="margin:10px 0 0">Agentul începe să răspundă vizitatorilor după activarea widgetului pe site (în dezvoltare).</p>
</div>
@can('view_leads')
<div class="card">
  <h2>Lead-uri recente</h2>
  @if ($recentLeads->isEmpty())
    <p class="muted" style="margin:0">Niciun lead încă.</p>
  @else
    <div class="table-wrap"><table><thead><tr><th>Contact</th><th>Intenție</th><th>Status</th><th>Data</th></tr></thead><tbody>
    @foreach ($recentLeads as $lead)
      <tr><td><a href="{{ route('portal.contacts.show', [$organization->slug, $lead->contact_id]) }}">{{ $lead->contact->displayName() }}</a></td>
        <td class="mono">{{ $lead->intent->value }}</td><td><span class="badge">{{ $lead->status->label() }}</span></td><td class="small muted">{{ $lead->created_at->format('d.m.Y H:i') }}</td></tr>
    @endforeach
    </tbody></table></div>
  @endif
</div>
@endcan
@endsection
