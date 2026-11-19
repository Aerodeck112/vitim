<!doctype html>
<html lang="ro"><body style="margin:0;padding:24px;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#1b1f24">
<div style="max-width:640px;margin:0 auto;background:#fff;border-radius:10px;padding:24px">
  <h1 style="font-size:19px;margin:0 0 4px">{{ $urgent ? 'Probleme critice noi' : 'Rezumatul de monitorizare' }}</h1>
  <p style="margin:0 0 16px;color:#5b6470;font-size:13px">{{ $report['clients'] }} clienți · {{ $report['sites'] }} site-uri · {{ $report['critical'] }} critice · {{ $report['warning'] }} de urmărit</p>
  @if ($report['items'] === [] && ($report['platform'] ?? []) === [])
    <p style="margin:0 0 16px;padding:12px 14px;background:#e9f7ef;border-radius:8px;font-size:14px">Nimic de semnalat: toate site-urile raportează, fără probleme critice.</p>
  @endif
  @foreach ($report['platform'] ?? [] as $note)
    <p style="margin:0 0 10px;padding:12px 14px;background:#fdecea;border-radius:8px;font-size:14px"><strong>Platformă:</strong> {{ $note }}</p>
  @endforeach
  @foreach ($groups as $org => $items)
    <h2 style="font-size:15px;margin:18px 0 6px;border-bottom:1px solid #e6e8eb;padding-bottom:6px">{{ $org }}</h2>
    @foreach ($items as $item)
      <p style="margin:0 0 8px;font-size:14px;line-height:1.45">
        <span style="display:inline-block;padding:1px 7px;border-radius:9px;font-size:11px;font-weight:bold;color:#fff;background:{{ $item['level'] === 'critical' ? '#c0392b' : '#b7791f' }}">{{ $item['level'] === 'critical' ? 'CRITIC' : 'atenție' }}</span>
        <a href="{{ $item['url'] }}" style="color:#1b1f24;font-weight:bold;text-decoration:none">{{ $item['title'] }}</a>
        @if ($item['detail'])<br><span style="color:#5b6470;font-size:13px">{{ $item['detail'] }}</span>@endif
      </p>
    @endforeach
  @endforeach
  @if (! $urgent && ($report['other_warnings'] ?? 0) > 0)
    <p style="margin:16px 0 0;color:#5b6470;font-size:13px">Alte {{ $report['other_warnings'] }} avertismente de audit sunt deschise (SEO, legal, performanță).</p>
  @endif
  <p style="margin:20px 0 0"><a href="{{ $url }}" style="display:inline-block;background:#1b1f24;color:#fff;padding:10px 16px;border-radius:7px;text-decoration:none;font-size:14px">Deschide monitorizarea</a></p>
  <p style="margin:18px 0 0;color:#8a929c;font-size:12px">Agentul de monitorizare VITIM AI</p>
</div>
</body></html>
