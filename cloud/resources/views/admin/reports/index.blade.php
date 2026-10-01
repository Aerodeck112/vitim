@extends('layouts.app')
@section('title', 'Servicii și rapoarte · '.$organization->name)
@section('content')
@php($slug = $organization->slug)
<div class="head"><div><h1>Servicii și rapoarte</h1><p>Ce servicii are {{ $organization->name }} și raportul lunar pe care îl vede clientul.</p></div></div>
<form class="card" method="post" action="{{ route('admin.reports.services', $slug) }}">
  @csrf
  <h2>Servicii contractate</h2>
  @foreach (\App\Reports\ServiceCatalog::SERVICES as $key => $def)
    <label class="chk"><input type="checkbox" name="services[]" value="{{ $key }}" @checked(($services[$key]->status ?? null) === 'active')> {{ $def[0] }}
      @if (isset($services[$key]) && $services[$key]->started_at)<span class="small muted">· din {{ $services[$key]->started_at->format('d.m.Y') }}</span>@endif</label>
  @endforeach
  <div style="margin-top:12px"><button class="btn btn-p" type="submit">Salvează serviciile</button></div>
</form>
<div class="table-wrap"><table>
  <thead><tr><th>Luna</th><th>Stare</th><th></th></tr></thead><tbody>
  @foreach ($periods as $p)
    @php($rep = $reports[$p] ?? null)
    <tr><td>{{ ucfirst(\Illuminate\Support\Carbon::createFromFormat('Y-m-d', $p.'-01')->locale('ro')->translatedFormat('F Y')) }}</td>
      <td>@if ($rep?->isPublished())<span class="badge ok">publicat {{ $rep->published_at->format('d.m.Y') }}</span>@elseif ($rep)<span class="badge warn">ciornă</span>@else<span class="muted small">—</span>@endif</td>
      <td><a href="{{ route('admin.reports.edit', [$slug, $p]) }}">{{ $rep ? 'Deschide' : 'Completează' }}</a></td></tr>
  @endforeach
  </tbody></table></div>
@endsection
