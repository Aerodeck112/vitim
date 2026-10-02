@php($s = $organization->slug)
<div class="tabs" style="margin-bottom:18px">
  @foreach ([['portal.campaigns.index', 'Campanii', 'portal.campaigns.*'], ['portal.flows.index', 'Automatizări', 'portal.flows.*'], ['portal.audience', 'Audiență', 'portal.audience*'], ['portal.forms.index', 'Formulare', 'portal.forms.*'], ['portal.analytics', 'Analiză', 'portal.analytics'], ['portal.brand', 'Brand', 'portal.brand'], ['portal.channels', 'Canale', 'portal.channels']] as [$route, $label, $pattern])
    @if (\Illuminate\Support\Facades\Route::has($route))<a href="{{ route($route, $s) }}" @class(['on' => request()->routeIs($pattern)])>{{ $label }}</a>@endif
  @endforeach
</div>
