@extends('layouts.app')
@section('title', 'Audiență')
@section('content')
<div class="head"><div><h1>Audiență</h1><p>Listele sunt fixe (le completezi tu, formularele sau importul). Segmentele se actualizează singure după comportament.</p></div>
  <a class="btn btn-p" href="{{ route('portal.audience.segment.create', $organization->slug) }}">Segment nou</a></div>
@include('partials.marketing-tabs')
<div class="grid" style="grid-template-columns:minmax(0,1fr) minmax(0,1fr);align-items:start">
  <div class="card">
    <h2>Liste</h2>
    <table><tbody>
      @forelse ($lists as $list)
        <tr><td><a href="{{ route('portal.audience.list', [$organization->slug, $list->id]) }}"><strong>{{ $list->name }}</strong></a>@if ($list->description)<div class="small muted">{{ $list->description }}</div>@endif</td><td style="text-align:right">{{ number_format($list->members_count, 0, ',', '.') }} contacte</td></tr>
      @empty
        <tr><td class="muted">Nicio listă. De exemplu: „Abonați newsletter”, „Clienți 2025”, „Participanți târg”.</td></tr>
      @endforelse
    </tbody></table>
    <form method="post" action="{{ route('portal.audience.lists.store', $organization->slug) }}" style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">@csrf
      <input type="text" name="name" required maxlength="120" placeholder="Numele listei" style="flex:1;min-width:180px"><button class="btn" type="submit">Creează lista</button></form>
  </div>
  <div class="card">
    <h2>Segmente</h2>
    <table><tbody>
      @forelse ($segments as $segment)
        <tr><td><a href="{{ route('portal.audience.segment', [$organization->slug, $segment->id]) }}"><strong>{{ $segment->name }}</strong></a>
          <div class="small muted">{{ \App\Services\SegmentQuery::describe($segment->definition, $names) }}</div></td><td style="text-align:right;white-space:nowrap">{{ number_format($segment->contacts_count, 0, ',', '.') }} contacte</td></tr>
      @empty
        <tr><td class="muted">Niciun segment. Exemple: „Implicați: au deschis un email în ultimele 30 de zile”, „Cereri fără comandă”, „Clienți VIP: au cheltuit peste 2.000 lei”.</td></tr>
      @endforelse
    </tbody></table>
  </div>
</div>
@endsection
