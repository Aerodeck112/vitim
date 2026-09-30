@extends('layouts.app')
@section('title', 'Utilizatori')
@section('content')
<div class="head"><div><h1>Utilizatori</h1><p>Toate conturile platformei (echipa VITIM și utilizatorii clienților).</p></div></div>
<div class="table-wrap"><table>
  <thead><tr><th>Nume</th><th>Email</th><th>Rol VITIM</th><th>Firme</th><th>2FA</th><th>Ultima autentificare</th></tr></thead>
  <tbody>
  @foreach ($users as $u)
    <tr><td>{{ $u->name }}</td><td>{{ $u->email }}</td><td>{{ $u->platform_role?->label() ?? '—' }}</td><td>{{ $memberships[$u->id] ?? 0 }}</td>
      <td>@if ($u->hasTwoFactor())<span class="badge ok">activ</span>@else<span class="badge">inactiv</span>@endif</td>
      <td class="small muted">{{ $u->last_login_at?->format('d.m.Y H:i') ?? 'niciodată' }}</td></tr>
  @endforeach
  </tbody>
</table></div>
@include('partials.pager', ['paginator' => $users])
@endsection
