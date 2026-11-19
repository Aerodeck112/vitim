{{ $urgent ? 'Probleme critice noi' : 'Rezumatul de monitorizare' }} — {{ $report['clients'] }} clienți, {{ $report['sites'] }} site-uri
@if ($report['items'] === [] && ($report['platform'] ?? []) === [])

Nimic de semnalat: toate site-urile raportează, fără probleme critice.
@endif
@foreach ($report['platform'] ?? [] as $note)

PLATFORMĂ: {{ $note }}
@endforeach
@foreach ($groups as $org => $items)

{{ $org }}
@foreach ($items as $item)
  [{{ $item['level'] === 'critical' ? 'CRITIC' : 'atenție' }}] {{ $item['title'] }}@if ($item['detail']) — {{ $item['detail'] }}@endif

    {{ $item['url'] }}
@endforeach
@endforeach
@if (! $urgent && ($report['other_warnings'] ?? 0) > 0)

Alte {{ $report['other_warnings'] }} avertismente de audit sunt deschise (SEO, legal, performanță).
@endif

Toate detaliile: {{ $url }}

— Agentul de monitorizare VITIM AI
