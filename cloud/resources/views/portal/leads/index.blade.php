@extends('layouts.app')
@section('title', 'Lead-uri')
@section('content')
<div class="head"><div><h1>Lead-uri</h1><p>Oportunitățile de vânzare. Lead-urile noi se adaugă din fișa contactului.</p></div></div>
<form method="get" class="toolbar">
  <select name="status" aria-label="Status"><option value="">Toate statusurile</option>@foreach ($statuses as $s)<option value="{{ $s->value }}" @selected($status === $s->value)>{{ $s->label() }}</option>@endforeach</select>
  <button class="btn" type="submit">Filtrează</button>
</form>
<div class="table-wrap"><table>
  <thead><tr><th>Contact</th><th>Intenție</th><th>Rezumat</th><th>Status / responsabil</th><th>Data</th></tr></thead>
  <tbody>
  @forelse ($leads as $lead)
    <tr><td><a href="{{ route('portal.contacts.show', [$organization->slug, $lead->contact_id]) }}">{{ $lead->contact->displayName() }}</a></td>
      <td class="mono">{{ $lead->intent->value }}</td><td class="small">{{ \Illuminate\Support\Str::limit($lead->summary ?? '—', 90) }}</td>
      <td>@if ($canManage)
        <form method="post" action="{{ route('portal.leads.update', [$organization->slug, $lead->id]) }}" class="inline">@csrf @method('put')
          <select name="status" aria-label="Status">@foreach ($statuses as $s)<option value="{{ $s->value }}" @selected($lead->status === $s)>{{ $s->label() }}</option>@endforeach</select>
          <select name="assigned_to" aria-label="Responsabil"><option value="">neatribuit</option>@foreach ($members as $m)<option value="{{ $m->user_id }}" @selected($lead->assigned_to === $m->user_id)>{{ $m->user->name }}</option>@endforeach</select>
          <button class="btn btn-s" type="submit">Salvează</button></form>
        @else <span class="badge">{{ $lead->status->label() }}</span> {{ $lead->assignee?->name }} @endif</td>
      <td class="small muted">{{ $lead->created_at->format('d.m.Y') }}</td></tr>
  @empty
    <tr><td colspan="5" class="empty">Niciun lead.</td></tr>
  @endforelse
  </tbody>
</table></div>
@include('partials.pager', ['paginator' => $leads])
@endsection
