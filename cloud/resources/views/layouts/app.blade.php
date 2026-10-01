<!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title') · VITIM AI</title>
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
@php
  $user = auth()->user();
  $inPortal = isset($organization) && request()->routeIs('portal.*');
  $slug = isset($organization) ? $organization->slug : null;
@endphp
<div class="shell">
  <nav class="side" aria-label="Navigare">
    <div class="brand">VITIM AI<small>{{ $user->isPlatformStaff() ? $user->platform_role->label() : 'dashboard firmă' }}</small></div>
    @if ($user->isPlatformStaff())
      <div class="grp">Echipa VITIM</div>
      <a href="{{ route('admin.organizations.index') }}" @class(['on' => request()->routeIs('admin.organizations.index', 'admin.organizations.create')])>Prezentare</a>
      <a href="{{ route('admin.sites.index') }}" @class(['on' => request()->routeIs('admin.sites.*')])>Site-uri</a>
      <a href="{{ route('admin.agents.index') }}" @class(['on' => request()->routeIs('admin.agents.*')])>Agenți</a>
      <a href="{{ route('admin.users.index') }}" @class(['on' => request()->routeIs('admin.users.*')])>Utilizatori</a>
      @if ($user->platform_role === \App\Enums\PlatformRole::SuperAdmin)<a href="{{ route('admin.system') }}" @class(['on' => request()->routeIs('admin.system*')])>Sistem</a>@endif
    @endif
    @isset($organization)
      <div class="grp">{{ $organization->name }}</div>
      @if ($user->isPlatformStaff())
        <a href="{{ route('admin.organizations.show', $slug) }}" @class(['on' => request()->routeIs('admin.organizations.show')])>Fișa clientului</a>
      @endif
      <a href="{{ route('portal.home', $slug) }}" @class(['on' => request()->routeIs('portal.home')])>Prezentare</a>
      <a href="{{ route('portal.agents.index', $slug) }}" @class(['on' => request()->routeIs('portal.agents.*')])>Agent AI</a>
      <a href="{{ route('portal.upcoming', [$slug, 'inbox']) }}" @class(['on' => request()->is('*/in-curand/inbox')])>Inbox <span class="soon">curând</span></a>
      @can('view_contacts')<a href="{{ route('portal.contacts.index', $slug) }}" @class(['on' => request()->routeIs('portal.contacts.*')])>Contacte</a>@endcan
      @can('view_leads')<a href="{{ route('portal.leads.index', $slug) }}" @class(['on' => request()->routeIs('portal.leads.*')])>Lead-uri</a>@endcan
      <a href="{{ route('portal.upcoming', [$slug, 'campanii']) }}" @class(['on' => request()->is('*/in-curand/campanii')])>Campanii <span class="soon">curând</span></a>
      <a href="{{ route('portal.upcoming', [$slug, 'automatizari']) }}" @class(['on' => request()->is('*/in-curand/automatizari')])>Automatizări <span class="soon">curând</span></a>
      <a href="{{ route('portal.upcoming', [$slug, 'analytics']) }}" @class(['on' => request()->is('*/in-curand/analytics')])>Analytics <span class="soon">curând</span></a>
      <a href="{{ route('portal.upcoming', [$slug, 'integrari']) }}" @class(['on' => request()->is('*/in-curand/integrari')])>Integrări <span class="soon">curând</span></a>
      <a href="{{ route('portal.settings', $slug) }}" @class(['on' => request()->routeIs('portal.settings')])>Setări</a>
    @endisset
    <div class="grow"></div>
    <span class="small" style="padding:8px 12px">{{ $user->email }}</span>
    <form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Ieșire</button></form>
  </nav>
  <main class="main">
    @include('partials.flash')
    @yield('content')
  </main>
</div>
</body>
</html>
