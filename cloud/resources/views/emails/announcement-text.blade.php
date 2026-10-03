{!! $a->title !!}

{!! $greeting !!}

@if ($intro){!! $intro !!}

@endif
{!! preg_replace('/\*\*(.+?)\*\*/', '$1', (string) $bodyText) !!}
@if ($personal)

{!! mb_strtoupper($personal['title']) !!}
@foreach ($personal['stats'] ?? [] as $label => $value)
- {!! $label !!}: {!! $value !!}
@endforeach
@foreach ($personal['items'] ?? [] as $item)
- {!! $item['label'] !!}: {!! $item['text'] !!} ({!! $item['date'] !!})
@endforeach
@if (! empty($personal['report']))
Raportul lunar: {!! $personal['report'] !!}
@endif
@endif
@if ($cta)

{!! $cta['label'] !!}: {!! $cta['url'] !!}
@endif

Ai o întrebare? Răspunde la acest email.
Echipa VITIM

--
Toate noutățile: {!! $portal !!}
Nu mai vreau aceste emailuri: {!! $unsubscribe !!}
