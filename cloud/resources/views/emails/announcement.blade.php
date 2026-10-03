<!doctype html>
<html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $a->title }}</title></head>
<body style="margin:0;padding:0;background:#f4f6fb">
<div style="display:none;max-height:0;overflow:hidden;opacity:0">{{ \Illuminate\Support\Str::limit(strip_tags($intro), 140) }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fb"><tr><td align="center" style="padding:24px 12px">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:14px;font-family:Arial,Helvetica,sans-serif;color:#0f172a;font-size:16px;line-height:1.6">
<tr><td style="padding:22px 32px;border-bottom:1px solid #eef1f6"><span style="font-weight:800;font-size:18px;letter-spacing:.02em">VITIM AI</span> <span style="color:#64748b;font-size:13px">· {{ \App\Models\Announcement::KINDS[$a->kind] ?? '' }}</span></td></tr>
<tr><td style="padding:28px 32px 8px">
  <h1 style="font-size:23px;line-height:1.3;margin:0 0 16px">{{ $a->title }}</h1>
  <p style="margin:0 0 12px">{{ $greeting }}</p>
  @if ($intro)<p style="margin:0 0 18px">{{ $intro }}</p>@endif
  {!! $body !!}
  @if ($personal)
    <div style="margin:22px 0 18px;padding:18px 20px;background:#f6f8fd;border:1px solid #e3e8f4;border-radius:12px">
      <div style="font-weight:700;font-size:16px;margin-bottom:10px">{{ $personal['title'] }}</div>
      @if (! empty($personal['stats']))
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:10px">
          @foreach ($personal['stats'] as $label => $value)<tr><td style="padding:5px 0;color:#475569;font-size:15px">{{ $label }}</td><td align="right" style="padding:5px 0;font-weight:700;font-size:16px">{{ number_format($value, 0, ',', '.') }}</td></tr>@endforeach
        </table>
      @endif
      @if (! empty($personal['items']))
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
          @foreach ($personal['items'] as $item)<tr><td style="padding:6px 0;border-top:1px solid #e3e8f4;font-size:14.5px"><span style="color:#2f6bff;font-weight:600">{{ $item['label'] }}</span> · {{ $item['text'] }}</td><td align="right" style="padding:6px 0;border-top:1px solid #e3e8f4;color:#64748b;font-size:13px;white-space:nowrap">{{ $item['date'] }}</td></tr>@endforeach
        </table>
      @endif
      @if (! empty($personal['updates']))<p style="margin:12px 0 0;font-size:14.5px"><strong>Nou în platformă luna aceasta:</strong> {{ implode(' · ', $personal['updates']) }}</p>@endif
      @if (! empty($personal['report']))<p style="margin:12px 0 0;font-size:14.5px"><a href="{{ $personal['report'] }}" style="color:#2f6bff;font-weight:600">Citește raportul lunar complet →</a></p>@endif
    </div>
  @endif
  @if ($cta)<div style="margin:22px 0 10px"><a href="{{ $cta['url'] }}" style="display:inline-block;background:#2f6bff;color:#ffffff;text-decoration:none;font-weight:bold;padding:13px 26px;border-radius:9px">{{ $cta['label'] }}</a></div>@endif
  <p style="margin:18px 0 8px">Ai o întrebare sau vrei să activăm ceva pentru {{ $company }}? Răspunde la acest email, îți scriem noi.</p>
  <p style="margin:0 0 24px">Echipa VITIM</p>
</td></tr></table>
<p style="max-width:600px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:#64748b;text-align:center;margin:14px auto">
  Primești acest email pentru că firma {{ $company }} folosește platforma VITIM AI. <a href="{{ $portal }}" style="color:#64748b">Toate noutățile în panou</a> · <a href="{{ $unsubscribe }}" style="color:#64748b">Nu mai vreau aceste emailuri</a>
</p>
@if ($pixel)<img src="{{ $pixel }}" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0">@endif
</td></tr></table>
</body></html>
