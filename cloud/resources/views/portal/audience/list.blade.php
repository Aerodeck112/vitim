@extends('layouts.app')
@section('title', $list->name)
@section('content')
<div class="head"><div><h1>{{ $list->name }}</h1><p>{{ $list->description ?: 'Listă statică.' }} Adaugi contacte de pe fișa contactului, din import sau din formulare.</p></div>
  <form method="post" action="{{ route('portal.audience.list.destroy', [$organization->slug, $list->id]) }}" onsubmit="return confirm('Ștergi lista? Contactele rămân.')">@csrf @method('delete')<button class="btn btn-d" type="submit">Șterge lista</button></form></div>
@include('partials.marketing-tabs')
<form method="get" class="toolbar"><input type="text" name="q" value="{{ $q }}" placeholder="Caută în listă"><button class="btn" type="submit">Caută</button></form>
<div class="table-wrap"><table>
  <thead><tr><th>Contact</th><th>Email</th><th>Telefon</th><th></th></tr></thead>
  <tbody>
  @forelse ($members as $c)
    <tr><td><a href="{{ route('portal.contacts.show', [$organization->slug, $c->id]) }}">{{ $c->displayName() }}</a></td><td class="small">{{ $c->email }}</td><td class="small">{{ $c->phone }}</td>
      <td><form method="post" action="{{ route('portal.audience.list.members', [$organization->slug, $list->id]) }}">@csrf<input type="hidden" name="action" value="remove"><input type="hidden" name="contact_id" value="{{ $c->id }}"><button class="btn btn-s" type="submit">Scoate</button></form></td></tr>
  @empty
    <tr><td colspan="4" class="empty">Lista e goală.</td></tr>
  @endforelse
  </tbody>
</table></div>
@include('partials.pager', ['paginator' => $members])
@endsection
