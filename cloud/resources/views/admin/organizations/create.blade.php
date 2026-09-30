@extends('layouts.app')
@section('title', 'Client nou')
@section('content')
<div class="head"><div><h1>Client nou</h1><p>Proprietarul primește pe email linkul pentru setarea parolei.</p></div></div>
<form method="post" action="{{ route('admin.organizations.store') }}" class="card" style="max-width:640px">
  @csrf
  <div class="fl"><label for="name">Numele firmei</label><input id="name" type="text" name="name" value="{{ old('name') }}" required></div>
  <div class="row">
    <div class="fl"><label for="company_name">Denumire juridică</label><input id="company_name" type="text" name="company_name" value="{{ old('company_name') }}"></div>
    <div class="fl"><label for="vat_id">CUI / VAT</label><input id="vat_id" type="text" name="vat_id" value="{{ old('vat_id') }}"></div>
  </div>
  <div class="row">
    <div class="fl"><label for="country">Țară (cod ISO)</label><input id="country" type="text" name="country" value="{{ old('country', 'RO') }}" maxlength="2" required></div>
    <div class="fl"><label for="default_language">Limba implicită</label><input id="default_language" type="text" name="default_language" value="{{ old('default_language', 'ro') }}" maxlength="2" required></div>
  </div>
  <div class="fl"><label for="plan">Plan</label>
    <select id="plan" name="plan">@foreach ($plans as $plan)<option value="{{ $plan }}" @selected(old('plan', 'start') === $plan)>{{ strtoupper($plan) }}</option>@endforeach</select>
    <div class="hint">Începe cu perioadă de probă de {{ config('plans.trial_days') }} zile.</div></div>
  <div class="row">
    <div class="fl"><label for="owner_name">Proprietar: nume</label><input id="owner_name" type="text" name="owner_name" value="{{ old('owner_name') }}" required></div>
    <div class="fl"><label for="owner_email">Proprietar: email</label><input id="owner_email" type="email" name="owner_email" value="{{ old('owner_email') }}" required></div>
  </div>
  <button class="btn btn-p" type="submit">Creează clientul</button>
</form>
@endsection
