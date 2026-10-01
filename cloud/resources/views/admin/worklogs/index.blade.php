@extends('layouts.app')
@section('title', 'Lucrări · '.$organization->name)
@section('content')
@php($slug = $organization->slug)
<div class="head"><div><h1>Lucrări pentru {{ $organization->name }}</h1><p>Ce face echipa VITIM pentru client. Lucrările vizibile apar în panoul clientului, la „Lucrări VITIM”.</p></div></div>
<form class="card" method="post" action="{{ $edit ? route('admin.worklogs.update', [$slug, $edit->id]) : route('admin.worklogs.store', $slug) }}">
  @csrf @if ($edit) @method('put') @endif
  <h2>{{ $edit ? 'Modifică lucrarea' : 'Lucrare nouă' }}</h2>
  <div class="row">
    <div class="fl"><label for="performed_at">Data</label><input id="performed_at" type="datetime-local" name="performed_at" required value="{{ old('performed_at', ($edit?->performed_at ?? now())->format('Y-m-d\TH:i')) }}"></div>
    <div class="fl"><label for="category">Tip</label><select id="category" name="category">@foreach ($categories as $c)<option value="{{ $c->value }}" @selected(old('category', $edit?->category->value) === $c->value)>{{ $c->label() }}</option>@endforeach</select></div>
  </div>
  <div class="row">
    <div class="fl"><label for="site_id">Site</label><select id="site_id" name="site_id"><option value="">— general (fără site)</option>@foreach ($sites as $s)<option value="{{ $s->id }}" @selected((string) old('site_id', $edit?->site_id) === (string) $s->id)>{{ $s->domain }}</option>@endforeach</select></div>
    <div class="fl"><label for="duration_minutes">Durată (minute)</label><input id="duration_minutes" type="number" min="1" name="duration_minutes" value="{{ old('duration_minutes', $edit?->duration_minutes) }}" placeholder="ex. 45"></div>
  </div>
  <div class="fl"><label for="title">Ce s-a făcut</label><input id="title" type="text" name="title" maxlength="190" required value="{{ old('title', $edit?->title) }}" placeholder="ex. Actualizare WordPress 6.8 și 12 pluginuri">@error('title')<div class="err">{{ $message }}</div>@enderror</div>
  <div class="fl"><label for="description">Detalii (opțional)</label><textarea id="description" name="description" maxlength="5000">{{ old('description', $edit?->description) }}</textarea></div>
  <label class="chk"><input type="checkbox" name="visible_to_client" value="1" @checked(old('visible_to_client', $edit?->visible_to_client ?? true))> Vizibilă pentru client</label>
  <div style="margin-top:12px;display:flex;gap:8px"><button class="btn btn-p" type="submit">{{ $edit ? 'Salvează' : 'Adaugă lucrarea' }}</button>
    @if ($edit)<a class="btn" href="{{ route('admin.worklogs.index', $slug) }}">Renunță</a>@endif</div>
</form>
@if ($logs)
<div class="table-wrap"><table>
  <thead><tr><th>Data</th><th>Tip</th><th>Lucrare</th><th>Site</th><th>Durată</th><th>Client</th><th></th></tr></thead>
  <tbody>
  @forelse ($logs as $log)
    <tr><td class="small">{{ $log->performed_at->format('d.m.Y H:i') }}</td><td><span class="badge">{{ $log->category->label() }}</span></td>
      <td><strong>{{ $log->title }}</strong>@if ($log->description)<div class="small muted">{{ \Illuminate\Support\Str::limit($log->description, 140) }}</div>@endif
        <div class="small muted">{{ $log->author?->name ?? ($log->source === 'manual' ? '—' : $log->source) }}</div></td>
      <td class="small">{{ $log->site?->domain ?? '—' }}</td><td class="small">{{ $log->duration_minutes ? $log->duration_minutes.' min' : '—' }}</td>
      <td>@if ($log->visible_to_client)<span class="badge ok">vizibilă</span>@else<span class="badge">internă</span>@endif</td>
      <td style="white-space:nowrap"><a class="small" href="{{ route('admin.worklogs.edit', [$slug, $log->id]) }}">Modifică</a>
        <form method="post" action="{{ route('admin.worklogs.destroy', [$slug, $log->id]) }}" style="display:inline" onsubmit="return confirm('Ștergi lucrarea?')">@csrf @method('delete')<button class="linkbtn small" type="submit">Șterge</button></form></td></tr>
  @empty
    <tr><td colspan="7" class="empty">Nicio lucrare înregistrată.</td></tr>
  @endforelse
  </tbody>
</table></div>
@include('partials.pager', ['paginator' => $logs])
@endif
@endsection
