@extends('layouts.app')
@section('title', 'Raport '.$r['label'])
@section('content')
<div class="head"><div><h1>Raport {{ $r['label'] }}</h1><p>{{ $organization->name }} · pregătit de echipa VITIM</p></div>
  <button class="btn noprint" type="button" onclick="window.print()">Descarcă PDF / tipărește</button></div>
@include('partials.report', ['r' => $r])
@endsection
