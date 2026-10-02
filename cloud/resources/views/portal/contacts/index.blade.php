@extends('layouts.app')
@section('title', 'Contacte')
@section('content')
<div class="head">
  <div><h1>Contacte</h1><p>Clienții și potențialii clienți ai firmei.</p></div>
  @if ($canManage)<div style="display:flex;gap:8px"><a class="btn" href="{{ route('portal.contacts.import', $organization->slug) }}">Import din Excel / CSV</a><a class="btn btn-p" href="{{ route('portal.contacts.create', $organization->slug) }}">Contact nou</a></div>@endif
</div>
<form method="get" class="toolbar">
  <input type="text" name="q" value="{{ $q }}" placeholder="Caută după nume, email, telefon, firmă" aria-label="Caută">
  <button class="btn" type="submit">Caută</button>
</form>
<div class="table-wrap"><table>
  <thead><tr><th>Nume</th><th>Email</th><th>Telefon</th><th>Firmă</th><th>Sursă</th><th>Adăugat</th></tr></thead>
  <tbody>
  @forelse ($contacts as $c)
    <tr><td><a href="{{ route('portal.contacts.show', [$organization->slug, $c->id]) }}"><strong>{{ $c->displayName() }}</strong></a></td>
      <td>{{ $c->email ?? '—' }}</td><td>{{ $c->phone ?? '—' }}</td><td>{{ $c->company ?? '—' }}</td>
      <td><span class="badge">{{ $c->source->value }}</span></td><td class="small muted">{{ $c->created_at->format('d.m.Y') }}</td></tr>
  @empty
    <tr><td colspan="6" class="empty">{{ $q ? 'Niciun rezultat.' : 'Niciun contact încă.' }}</td></tr>
  @endforelse
  </tbody>
</table></div>
@include('partials.pager', ['paginator' => $contacts])
@endsection
