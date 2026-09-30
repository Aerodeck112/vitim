@extends('layouts.app')
@section('title', 'Site-uri')
@section('content')
<div class="head"><div><h1>Site-uri</h1><p>Toate site-urile clienților.</p></div></div>
<div class="table-wrap"><table>
  <thead><tr><th>Domeniu</th><th>Organizație</th><th>Platformă</th><th>Status</th><th>Verificare</th><th>Plugin</th></tr></thead>
  <tbody>
  @forelse ($sites as $site)
    <tr><td><strong>{{ $site->domain }}</strong><div class="small muted">{{ $site->name }}</div></td>
      <td><a href="{{ route('admin.organizations.show', $site->organization->slug) }}">{{ $site->organization->name }}</a></td>
      <td>{{ $site->platform->label() }}</td><td><span class="badge">{{ $site->status }}</span></td><td><span class="badge">{{ $site->verification_status }}</span></td>
      <td class="small muted">{{ $site->last_seen_at ? 'văzut '.$site->last_seen_at->diffForHumans() : 'neconectat' }}</td></tr>
  @empty
    <tr><td colspan="6" class="empty">Niciun site.</td></tr>
  @endforelse
  </tbody>
</table></div>
@include('partials.pager', ['paginator' => $sites])
@endsection
