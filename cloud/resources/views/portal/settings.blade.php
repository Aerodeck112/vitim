@extends('layouts.app')
@section('title', 'Setări')
@section('content')
<div class="head"><div><h1>Setări</h1><p>Plan {{ strtoupper($subscription?->plan ?? '—') }} · {{ $subscription?->status->value }}</p></div></div>
@if (session('issued'))
  <div class="card"><h2>Cod de instalare</h2>@include('partials.install', ['publicKey' => session('issued')['public'], 'secret' => session('issued')['secret']])</div>
@endif
<form method="post" action="{{ route('portal.settings.profile', $organization->slug) }}" class="card">
  @csrf @method('put')
  <h2>Firma</h2>
  <fieldset @disabled(! $can['organization']) style="border:0;padding:0;margin:0">
  <div class="row">
    <div class="fl"><label for="name">Nume afișat</label><input id="name" type="text" name="name" value="{{ old('name', $organization->name) }}" required></div>
    <div class="fl"><label for="company_name">Denumire juridică</label><input id="company_name" type="text" name="company_name" value="{{ old('company_name', $organization->company_name) }}"></div>
  </div>
  <div class="row">
    <div class="fl"><label for="vat_id">CUI / VAT</label><input id="vat_id" type="text" name="vat_id" value="{{ old('vat_id', $organization->vat_id) }}"></div>
    <div class="fl"><label for="country">Țară</label><input id="country" type="text" name="country" value="{{ old('country', $organization->country) }}" maxlength="2" required></div>
  </div>
  <div class="row">
    <div class="fl"><label for="timezone">Fus orar</label><input id="timezone" type="text" name="timezone" value="{{ old('timezone', $organization->timezone) }}" required></div>
    <div class="fl"><label for="default_language">Limba implicită</label><input id="default_language" type="text" name="default_language" value="{{ old('default_language', $organization->default_language) }}" maxlength="2" required></div>
  </div>
  @if ($can['organization'])<button class="btn btn-p" type="submit">Salvează</button>@else<p class="tag-note">Doar proprietarul modifică datele firmei.</p>@endif
  </fieldset>
</form>

<div class="card">
  <h2>Utilizatori</h2>
  <div class="table-wrap"><table>
    <thead><tr><th>Nume</th><th>Email</th><th>Rol</th><th>2FA</th><th></th></tr></thead>
    <tbody>
    @foreach ($members as $m)
      <tr><td>{{ $m->user->name }}</td><td>{{ $m->user->email }}</td>
        <td>@if ($can['users'] && in_array($m->role, $roles, true))
          <form method="post" action="{{ route('portal.settings.role', [$organization->slug, $m->id]) }}" class="inline">@csrf @method('put')
            <select name="role" aria-label="Rol">@foreach ($roles as $r)<option value="{{ $r->value }}" @selected($m->role === $r)>{{ $r->label() }}</option>@endforeach</select>
            <button class="btn btn-s" type="submit">Schimbă</button></form>
          @else {{ $m->role->label() }} @endif</td>
        <td>{{ $m->user->hasTwoFactor() ? 'activ' : '—' }}@if (\App\Services\Invitations::pending($m->user))<div class="small">@include('partials.invite-status', ['user' => $m->user])</div>@endif</td>
        <td>@if ($can['users'] && \App\Services\Invitations::pending($m->user))
          <form method="post" action="{{ route('portal.settings.resend', [$organization->slug, $m->id]) }}" style="margin-bottom:6px">@csrf<button class="btn btn-s" type="submit">Retrimite invitația</button></form>
        @endif
        @if ($can['users'] && in_array($m->role, $roles, true) && $m->user_id !== auth()->id())
          <form method="post" action="{{ route('portal.settings.remove', [$organization->slug, $m->id]) }}" onsubmit="return confirm('Elimini utilizatorul din firmă?')">@csrf @method('delete')<button class="btn btn-s btn-d" type="submit">Elimină</button></form>
        @endif</td></tr>
    @endforeach
    </tbody>
  </table></div>
  @if ($can['users'])
    <form method="post" action="{{ route('portal.settings.invite', $organization->slug) }}" style="margin-top:14px">
      @csrf
      <div class="row">
        <div class="fl"><label for="inv_name">Nume</label><input id="inv_name" type="text" name="name" required></div>
        <div class="fl"><label for="inv_email">Email</label><input id="inv_email" type="email" name="email" required></div>
      </div>
      <div class="fl"><label for="inv_role">Rol</label><select id="inv_role" name="role">@foreach ($roles as $r)<option value="{{ $r->value }}">{{ $r->label() }}</option>@endforeach</select></div>
      <button class="btn btn-p" type="submit">Trimite invitația</button>
      <p class="tag-note">Utilizatorul primește pe email linkul pentru setarea parolei.</p>
    </form>
  @endif
</div>

<div class="card">
  <h2>Site-uri</h2>
  @forelse ($sites as $site)
    <div style="padding:10px 0;border-bottom:1px solid var(--border)"><strong>{{ $site->name }}</strong> <span class="small muted">{{ $site->domain }} · {{ $site->platform->label() }}</span>
      <div style="margin:4px 0">@include('partials.site-health', ['site' => $site])</div>
      @if ($can['sites'] && ($key = $site->keys->first()))@include('partials.install', ['publicKey' => $key->public_key])@endif</div>
  @empty
    <p class="muted">Niciun site.</p>
  @endforelse
  @if ($can['sites'])
    <form method="post" action="{{ route('portal.settings.sites', $organization->slug) }}" style="margin-top:14px">
      @csrf
      <div class="row">
        <div class="fl"><label for="domain">Domeniu</label><input id="domain" type="text" name="domain" placeholder="firma.ro" required></div>
        <div class="fl"><label for="platform">Platformă</label><select id="platform" name="platform">@foreach ($platforms as $p)<option value="{{ $p->value }}">{{ $p->label() }}</option>@endforeach</select></div>
      </div>
      <button class="btn" type="submit">Adaugă site</button>
    </form>
  @endif
</div>
@endsection
