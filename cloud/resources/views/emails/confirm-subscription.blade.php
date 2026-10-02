<!doctype html>
<html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Confirmă abonarea</title></head>
<body style="margin:0;padding:0;background:#f4f6fb">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fb"><tr><td align="center" style="padding:24px 12px">
<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="width:100%;max-width:560px;background:#ffffff;border-radius:12px;font-family:Arial,Helvetica,sans-serif;color:#0f172a;font-size:16px;line-height:1.55">
<tr><td style="padding:32px">
@if ($logo)<div style="text-align:center;margin-bottom:18px"><img src="{{ $logo }}" alt="{{ $company }}" style="max-width:160px;max-height:64px;border:0"></div>@endif
<p style="margin:0 0 12px">{{ $greeting }}</p>
<p style="margin:0 0 20px">Mai ai un singur pas: confirmă că vrei să primești pe email noutățile și ofertele <strong>{{ $company }}</strong>.</p>
<div style="text-align:center;margin:0 0 22px"><a href="{{ $url }}" style="display:inline-block;background:{{ $color }};color:#ffffff;text-decoration:none;font-weight:bold;padding:13px 28px;border-radius:8px">Confirm abonarea</a></div>
<p style="margin:0;font-size:14px;color:#475569">Dacă nu tu te-ai abonat, ignoră acest email: nu vei primi nimic de la noi.</p>
</td></tr></table>
<p style="max-width:560px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:#64748b;text-align:center;margin:14px auto">{!! nl2br(e($footer)) !!}</p>
</td></tr></table>
</body></html>
