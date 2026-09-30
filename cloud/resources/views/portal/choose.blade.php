@extends('layouts.auth')
@section('title', 'Alege firma')
@section('content')
@if ($organizations->isEmpty())
  <p>Contul tău nu este încă asociat cu o firmă. Contactează echipa VITIM.</p>
@else
  <p class="muted small" style="margin-top:0">Alege firma:</p>
  @foreach ($organizations as $org)
    <p><a class="btn" style="width:100%" href="{{ route('portal.home', $org->slug) }}">{{ $org->name }}</a></p>
  @endforeach
@endif
<form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-s" type="submit">Ieșire</button></form>
@endsection
