@extends('layouts.app')
@section('title', 'Clienți')
@section('content')
<div class="head">
  <div><h1>Clienți</h1><p>Firmele care folosesc VITIM AI.</p></div>
  @if (auth()->user()->platform_role === \App\Enums\PlatformRole::Admin)
    <a class="btn btn-p" href="{{ route('admin.organizations.create') }}">Client nou</a>
  @endif
</div>
<div class="grid" style="margin-bottom:18px">
  @foreach ($kpi as $label => $value)
    <div class="kpi"><small>{{ $label }}</small><b>{{ $value }}</b></div>
  @endforeach
</div>
<div class="table-wrap">
  <table>
    <thead><tr><th>Firmă</th><th>Plan</th><th>Abonament</th><th>Site-uri</th><th>Utilizatori</th><th>Creat</th></tr></thead>
    <tbody>
    @forelse ($organizations as $org)
      @php($sub = $subscriptions[$org->id] ?? null)
      <tr>
        <td><a href="{{ route('admin.organizations.show', $org->slug) }}"><strong>{{ $org->name }}</strong></a>
          @unless ($org->isActive())<span class="badge err">suspendat</span>@endunless</td>
        <td>{{ strtoupper($sub?->plan ?? '—') }}</td>
        <td>@if ($sub)<span @class(['badge', 'ok' => $sub->isServiceable(), 'err' => ! $sub->isServiceable()])>{{ $sub->status->value }}</span>@endif</td>
        <td>{{ $sites[$org->id] ?? 0 }}</td>
        <td>{{ $members[$org->id] ?? 0 }}</td>
        <td class="muted small">{{ $org->created_at?->format('d.m.Y') }}</td>
      </tr>
    @empty
      <tr><td colspan="6" class="muted">Niciun client încă.</td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@endsection
