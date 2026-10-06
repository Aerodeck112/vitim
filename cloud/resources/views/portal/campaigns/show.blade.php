@extends('layouts.app')
@section('title', $campaign->name)
@section('content')
@php($slug = $organization->slug)
@php($ch = $campaign->channel->value)
@php($labels = ['email' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'])
@php($a = (array) $campaign->audience)
@include('partials.marketing-tabs')
<div class="head"><div><h1>{{ $campaign->name }}</h1>
  <p>{{ $labels[$ch] }} · <span class="badge">{{ \App\Models\Campaign::STATUSES[$campaign->status] }}</span>
    @if ($campaign->approved_at) · aprobată de {{ $campaign->approver?->name ?? '—' }} la {{ $campaign->approved_at->setTimezone('Europe/Bucharest')->format('d.m.Y H:i') }}@endif
    @if ($campaign->status === 'scheduled' && $campaign->scheduled_at) · pleacă la {{ $campaign->scheduled_at->setTimezone('Europe/Bucharest')->format('d.m.Y H:i') }}@endif</p></div>
  <a class="btn" href="{{ route('portal.campaigns.index', $slug) }}">Toate campaniile</a></div>

@if (! $account)<div class="alert alert-warn">Contul de {{ $labels[$ch] }} nu e conectat. <a href="{{ route('portal.channels', $slug) }}#{{ $ch }}">Conectează-l</a> ca să poți trimite.</div>@endif
@if ($campaign->last_error)<div class="alert alert-err">Campania s-a oprit singură după mai multe erori la rând: {{ $campaign->last_error }}. Verifică contul în Canale de trimitere, apoi apasă „Continuă”.</div>@endif
@error('campaign')<div class="alert alert-err">{{ $message }}</div>@enderror

@if (! $campaign->editable())
  @php($done = ($stats['sent'] ?? 0) + ($stats['delivered'] ?? 0) + ($stats['read'] ?? 0) + ($stats['unsubscribed'] ?? 0))
  <div class="grid" style="margin-bottom:18px">
    @foreach (array_filter([['Trimise', $done, null], ['În așteptare', $stats['pending'] ?? 0, null],
      $ch === 'email' ? ['Deschideri unice', $engagement['opened'], $engagement['open_rate'].'%'] : null, $ch === 'email' ? ['Click-uri unice', $engagement['clicked'], $engagement['click_rate'].'%'] : null,
      $ch === 'whatsapp' ? ['Livrate / citite', ($stats['delivered'] ?? 0) + ($stats['read'] ?? 0), null] : null,
      ($revenue['orders'] ?? 0) > 0 ? ['Comenzi atribuite', $revenue['orders'], number_format($revenue['revenue'], 2, ',', '.').' lei'] : null,
      ['Eșuate', $stats['failed'] ?? 0, null], ['Excluse (fără acord etc.)', $stats['excluded'] ?? 0, null], ['Dezabonați', $stats['unsubscribed'] ?? 0, null]]) as [$label, $value, $rate])
      <div class="kpi"><small>{{ $label }}</small><b>{{ number_format($value, 0, ',', '.') }}</b>@if ($rate)<span class="muted"> · {{ $rate }}</span>@endif</div>
    @endforeach
  </div>
  <div style="display:flex;gap:8px;margin-bottom:18px">
    @foreach (array_filter(['pause' => in_array($campaign->status, ['scheduled', 'sending'], true) ? 'Oprește' : null, 'resume' => $campaign->status === 'paused' ? 'Continuă' : null, 'cancel' => in_array($campaign->status, ['scheduled', 'paused'], true) ? 'Anulează' : null]) as $action => $label)
      <form method="post" action="{{ route('portal.campaigns.action', [$slug, $campaign->id]) }}" @if ($action === 'cancel') onsubmit="return confirm('Anulezi campania? Mesajele netrimise nu mai pleacă.')" @endif>@csrf<input type="hidden" name="action" value="{{ $action }}"><button class="btn {{ $action === 'cancel' ? 'btn-d' : '' }}" type="submit">{{ $label }}</button></form>
    @endforeach
  </div>
  @if ($resendCounts !== null)
  <form method="post" action="{{ route('portal.campaigns.relaunch', [$slug, $campaign->id]) }}" class="card resend" id="reluare">
    @csrf
    <h2>Reluare campanie</h2>
    <p class="small muted" style="margin-top:-6px">Pregătește o campanie nouă cu același mesaj și același public. O verifici, îți trimiți un test și o aprobi, ca de obicei. Cine s-a dezabonat între timp sau nu are acord nu o primește.</p>
    <div class="resend-opts">
      @foreach (\App\Services\CampaignService::RESEND as $mode => $label)
        @continue($mode === 'not_opened' && $ch !== 'email')
        <label class="chk"><input type="radio" name="mode" value="{{ $mode }}" @checked(old('mode', $ch === 'email' ? 'not_opened' : 'all') === $mode) required> {{ $label }}
          @if (isset($resendCounts[$mode]))<span class="badge">{{ number_format($resendCounts[$mode], 0, ',', '.') }}</span>@endif</label>
      @endforeach
    </div>
    @if ($ch === 'email')<p class="hint small muted">„Nu au deschis” e orientativ: unele programe de email blochează imaginile și nu raportează deschiderea, iar altele o raportează automat.</p>@endif
    @error('resend')<div class="err">{{ $message }}</div>@enderror
    <button class="btn btn-p" type="submit">Pregătește reluarea</button>
  </form>
  @elseif (in_array($campaign->status, ['scheduled', 'sending', 'paused'], true))
  <p class="small muted">Reluarea campaniei devine disponibilă după ce trimiterea se termină sau după ce campania e anulată.</p>
  @endif
@endif

@if ($resendFrom && $campaign->editable())
  <div class="alert alert-warn">Aceasta e o reluare a campaniei <a href="{{ route('portal.campaigns.show', [$slug, $resendFrom->id]) }}">{{ $resendFrom->name }}</a>.
    Pleacă către: <strong>{{ mb_strtolower(\App\Services\CampaignService::RESEND[$a['resend']['mode'] ?? 'all'] ?? '') }}</strong>, cu acord de marketing valabil acum.</div>
@endif

@if ($ab)
  @php($metric = ($campaign->ab['metric'] ?? 'open') === 'click' ? 'click_rate' : 'open_rate')
  <div class="card">
    <h2>Test A/B @if ($campaign->ab_winner)<span class="badge ok">a câștigat varianta {{ strtoupper($campaign->ab_winner) }}</span>@else<span class="badge warn">în desfășurare</span>@endif</h2>
    <div class="table-wrap"><table>
      <thead><tr><th>Variantă</th><th>Subiect</th><th>Trimise</th><th>Deschideri</th><th>Click-uri</th></tr></thead>
      <tbody>
      @foreach (['a' => $campaign->subject, 'b' => $campaign->ab['subject_b']] as $v => $subj)
        <tr @if ($campaign->ab_winner === $v) style="font-weight:600" @endif><td>{{ strtoupper($v) }}@if ($campaign->ab_winner === $v) 🏆@endif</td><td>{{ $subj }}</td><td>{{ $ab[$v]['sent'] }}</td>
          <td>{{ $ab[$v]['open_rate'] }}% <span class="muted small">({{ $ab[$v]['opened'] }})</span></td><td>{{ $ab[$v]['click_rate'] }}% <span class="muted small">({{ $ab[$v]['clicked'] }})</span></td></tr>
      @endforeach
      </tbody>
    </table></div>
    <p class="small muted" style="margin-bottom:0">@if ($campaign->ab_winner)Decis la {{ $campaign->ab_decided_at?->setTimezone('Europe/Bucharest')->format('d.m.Y H:i') }} după {{ $metric === 'open_rate' ? 'rata de deschidere' : 'rata de click' }}; restul destinatarilor au primit varianta câștigătoare.@else Restul destinatarilor primesc varianta câștigătoare la {{ $campaign->ab['wait_hours'] }} ore după trimiterea grupelor de test.@endif</p>
  </div>
@endif

<div class="grid" style="grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);align-items:start">
<div>
<form method="post" action="{{ route('portal.campaigns.update', [$slug, $campaign->id]) }}" class="card">
  @csrf @method('put')
  <fieldset @disabled(! $campaign->editable()) style="border:0;padding:0;margin:0">
  <h2>Mesajul</h2>
  <div class="fl"><label for="name">Numele campaniei (intern)</label><input id="name" type="text" name="name" value="{{ old('name', $campaign->name) }}" maxlength="160" required></div>
  @if ($ch === 'email')
    <div class="fl"><label for="subject">Subiect</label><input id="subject" type="text" name="subject" value="{{ old('subject', $campaign->subject) }}" maxlength="200" placeholder="ex. @{{prenume}}, 20% reducere până duminică">@error('subject')<div class="err">{{ $message }}</div>@enderror</div>
    @if (! empty($campaign->blocks))
      <div class="alert" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">Emailul e construit cu editorul vizual ({{ count($campaign->blocks) }} blocuri).<a class="btn btn-p btn-s" href="{{ route('portal.design.campaign', [$slug, $campaign->id]) }}">{{ $campaign->editable() ? 'Deschide editorul vizual' : 'Vezi designul' }}</a></div>
    @else
    <div class="fl"><label for="body">Textul emailului</label><textarea id="body" name="body" rows="12" placeholder="Bună @{{prenume}},&#10;&#10;…">{{ old('body', $campaign->body) }}</textarea>
      <div class="hint">Paragrafele se despart printr-un rând gol. **text** = îngroșat; linkurile https:// devin clicabile. Datele firmei și linkul de dezabonare se adaugă automat la final.@if ($campaign->editable()) Vrei imagini, butoane și produse? <a href="{{ route('portal.design.campaign', [$slug, $campaign->id]) }}">Folosește editorul vizual</a> (după ce salvezi subiectul).@endif</div></div>
    @endif
  @elseif ($ch === 'sms')
    <div class="fl"><label for="body">Textul SMS-ului</label><textarea id="body" name="body" rows="5" maxlength="900">{{ old('body', $campaign->body) }}</textarea>
      <div class="hint">Linkul de dezabonare se adaugă automat la final. Mesajul previzualizat are <strong>{{ mb_strlen($preview['body']) }}</strong> caractere = <strong>{{ $smsParts }} SMS</strong> per destinatar.</div></div>
  @else
    <p class="small muted" style="margin-top:-6px">WhatsApp permite campanii doar cu șabloane aprobate de Meta (WhatsApp Manager → Message templates, categoria Marketing). Scrie aici exact numele și limba șablonului și valorile pentru @{{1}}, @{{2}}… Pune în șablon un buton „Dezabonare” / „STOP”.</p>
    <div class="row">
      <div class="fl"><label for="tn">Numele șablonului</label><input id="tn" type="text" name="template_name" value="{{ old('template_name', $campaign->template['name'] ?? '') }}" placeholder="oferta_primavara">@error('template_name')<div class="err">{{ $message }}</div>@enderror</div>
      <div class="fl"><label for="tl">Limba</label><input id="tl" type="text" name="template_language" value="{{ old('template_language', $campaign->template['language'] ?? 'ro') }}" maxlength="10"></div>
    </div>
    <div class="fl"><label for="tv">Valorile variabilelor (câte una pe rând: prima = @{{1}}, a doua = @{{2}}…)</label><textarea id="tv" name="template_variables" rows="3">{{ old('template_variables', implode("\n", $campaign->template['variables'] ?? [])) }}</textarea></div>
    <div class="fl"><label for="body">Textul șablonului (doar ca notă internă / previzualizare)</label><textarea id="body" name="body" rows="4">{{ old('body', $campaign->body) }}</textarea></div>
  @endif
  @if ($ch === 'email')
    @php($abc = (array) ($campaign->ab ?? []))
    <details class="ab-box" @if ($abc) open @endif style="border:1px solid var(--border);border-radius:10px;padding:10px 14px;margin:6px 0 14px">
      <summary style="cursor:pointer;font-weight:600">Test A/B pe subiect @if ($abc)<span class="badge ok">activ</span>@endif</summary>
      <p class="small muted">O parte din destinatari primește subiectul A, o parte subiectul B. După câteva ore, varianta cu rezultate mai bune pleacă automat la restul.</p>
      <label class="chk"><input type="checkbox" name="ab_enabled" value="1" @checked($abc)> Activează testul</label>
      <div class="fl"><label for="subject_b">Subiectul B</label><input id="subject_b" type="text" name="subject_b" maxlength="200" value="{{ old('subject_b', $abc['subject_b'] ?? '') }}" placeholder="ex. Doar până duminică: -20% la cabane">@error('subject_b')<div class="err">{{ $message }}</div>@enderror</div>
      <div class="fl"><label for="preheader_b">Textul de previzualizare B (opțional)</label><input id="preheader_b" type="text" name="preheader_b" maxlength="150" value="{{ old('preheader_b', $abc['preheader_b'] ?? '') }}"></div>
      <div class="row">
        <div class="fl"><label for="ab_percent">Grupul de test</label><select id="ab_percent" name="ab_percent">@foreach ([10 => '10% (5% A + 5% B)', 20 => '20% (10% + 10%)', 30 => '30%', 50 => '50%', 100 => 'toți (jumătate A, jumătate B)'] as $k => $l)<option value="{{ $k }}" @selected(($abc['test_percent'] ?? 20) == $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="fl"><label for="ab_metric">Câștigă varianta cu mai multe</label><select id="ab_metric" name="ab_metric"><option value="open" @selected(($abc['metric'] ?? 'open') === 'open')>deschideri</option><option value="click" @selected(($abc['metric'] ?? '') === 'click')>click-uri</option></select></div>
        <div class="fl"><label for="ab_wait">Decizia după</label><select id="ab_wait" name="ab_wait">@foreach ([2, 4, 6, 12, 24] as $h)<option value="{{ $h }}" @selected(($abc['wait_hours'] ?? 4) == $h)>{{ $h }} ore</option>@endforeach</select></div>
      </div>
    </details>
  @endif
  <p class="small muted">Poți folosi: @foreach (\App\Services\CampaignRenderer::VARIABLES as $var => $desc)<span class="mono">{{ $var }}</span> ({{ $desc }})@if (! $loop->last), @endif @endforeach</p>

  <h2 style="margin-top:20px">Cui pleacă</h2>
  <p class="small muted" style="margin-top:-6px">Doar contactele cu acord de marketing pe {{ $labels[$ch] }} și nedezabonate. Nimic bifat la „Trimite către” = toate contactele cu acord.</p>
  <div class="grid" style="grid-template-columns:1fr 1fr">
    @foreach (['include' => 'Trimite către', 'exclude' => 'Exclude'] as $key => $title)
      <div><div class="lbl">{{ $title }}</div>
        @foreach ($lists as $list)<label class="chk"><input type="checkbox" name="{{ $key }}[]" value="list:{{ $list->id }}" @checked(in_array('list:'.$list->id, $a[$key] ?? [], true))> 📋 {{ $list->name }}</label>@endforeach
        @foreach ($segments as $segment)<label class="chk"><input type="checkbox" name="{{ $key }}[]" value="segment:{{ $segment->id }}" @checked(in_array('segment:'.$segment->id, $a[$key] ?? [], true))> ⚡ {{ $segment->name }}</label>@endforeach
        @if ($lists->isEmpty() && $segments->isEmpty())<p class="small muted"><a href="{{ route('portal.audience', $slug) }}">Creează liste și segmente</a></p>@endif
      </div>
    @endforeach
  </div>
  @if ($campaign->editable())<button class="btn btn-p" type="submit">Salvează</button>@endif
  </fieldset>
</form>
</div>

<div>
  <div class="card">
    <h2>Previzualizare</h2>
    @if ($ch === 'email')<div class="small muted">Subiect: <strong>{{ $preview['subject'] ?: '—' }}</strong></div>
      <iframe title="Previzualizare email" sandbox srcdoc="{{ $preview['html'] }}" style="width:100%;height:420px;border:1px solid var(--border);border-radius:10px;margin-top:8px;background:#fff"></iframe>
    @elseif ($ch === 'sms')<div class="bubble ai" style="max-width:100%;white-space:pre-wrap">{{ $preview['body'] }}</div>
    @else<div class="bubble ai" style="max-width:100%;white-space:pre-wrap">{{ $preview['body'] ?: 'Șablon: '.($preview['template']['name'] ?? '—') }}</div>
      <div class="small muted" style="margin-top:6px">Variabile: {{ implode(' · ', $preview['template']['variables'] ?? []) ?: '—' }}</div>@endif
    <p class="small muted" style="margin-bottom:0">Exemplu pentru contactul „Maria Popescu”.</p>
  </div>

  @if ($campaign->editable())
    <div class="card">
      <h2>Destinatari</h2>
      <p style="margin:0 0 6px"><strong style="font-size:22px">{{ number_format($estimate['eligible'], 0, ',', '.') }}</strong> contacte vor primi campania.</p>
      @if ($estimate['excluded'])<ul class="small muted" style="margin:0;padding-left:18px">@foreach ($estimate['excluded'] as $reason => $n)<li>{{ $n }} excluse: {{ $reason }}</li>@endforeach</ul>@endif
      @if ($estimate['eligible'] === 0)<p class="small">Adaugă contacte cu acord: <a href="{{ route('portal.contacts.import', $slug) }}">import cu acord</a> sau acordul înregistrat pe fișa contactului.</p>@endif
    </div>
    @if ($account)
    <div class="card">
      <h2>1. Trimite-ți un test</h2>
      <form method="post" action="{{ route('portal.campaigns.test', [$slug, $campaign->id]) }}" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">@csrf
        <div class="fl" style="margin:0;flex:1"><label for="test_to">{{ $ch === 'email' ? 'Adresa ta de email' : 'Numărul tău de telefon' }}</label><input id="test_to" type="text" name="test_to" required value="{{ $ch === 'email' ? auth()->user()->email : '' }}"></div>
        <button class="btn" type="submit">Trimite testul</button></form>
      @error('test')<div class="err">{{ $message }}</div>@enderror @error('test_to')<div class="err">{{ $message }}</div>@enderror
      <p class="hint small muted" style="margin:8px 0 0">În test, @{{prenume}} se completează cu prenumele contactului cu {{ $ch === 'email' ? 'această adresă' : 'acest număr' }} (dacă există în contacte), altfel cu „Maria”. În campania reală, fiecare destinatar își vede propriul prenume.</p>
    </div>
    <div class="card" style="border-color:var(--brand)">
      <h2>2. Aprobă și trimite</h2>
      <form method="post" action="{{ route('portal.campaigns.approve', [$slug, $campaign->id]) }}">@csrf
        <div class="fl"><label for="when">Când pleacă (gol = acum)</label><input id="when" type="datetime-local" name="when" value="{{ old('when') }}">@error('when')<div class="err">{{ $message }}</div>@enderror
          @if ($ch === 'email' && $sendTime)<div class="hint">Ora recomandată: <strong>{{ sprintf('%02d:00', $sendTime['hour']) }}</strong>
            {{ $sendTime['reliable'] ? '(când abonații tăi deschid cel mai des emailurile, din '.$sendTime['sample'].' deschideri)' : '(implicit; se calculează după primele '.\App\Services\SendTime::MIN_SAMPLE.' deschideri)' }}
            · <a href="#" onclick="document.getElementById('when').value='{{ $sendTime['next'] }}';return false">programează la această oră</a></div>@endif</div>
        @if ($campaign->ab)<p class="small">Test A/B activ: {{ $campaign->ab['test_percent'] }}% din destinatari în grupele de test, câștigătorul după {{ $campaign->ab['wait_hours'] }} ore.</p>@endif
        <label class="chk"><input type="checkbox" name="confirm" value="1"> Am verificat testul. Trimite către {{ number_format($estimate['eligible'], 0, ',', '.') }} contacte.</label>@error('confirm')<div class="err">{{ $message }}</div>@enderror
        @if ($ch === 'email' && $account->hourly_limit)<p class="small muted">Pleacă maximum {{ $account->hourly_limit }} emailuri pe oră (limita contului): {{ $estimate['eligible'] > $account->hourly_limit ? 'aproximativ '.ceil($estimate['eligible'] / $account->hourly_limit).' ore în total.' : 'toate în prima oră.' }}</p>@endif
        <button class="btn btn-p" type="submit" @disabled($estimate['eligible'] === 0)>Aprobă campania</button>
      </form>
    </div>
    @endif
  @endif
</div>
</div>

@if (! $campaign->editable())
<div class="card">
  <h2>Destinatari</h2>
  <div class="table-wrap"><table>
    <thead><tr><th>Contact</th><th>Adresă</th><th>Stare</th><th>Detalii</th><th>Trimis</th>@if ($ch === 'email')<th>Deschis</th><th>Click</th>@endif</tr></thead>
    <tbody>
    @foreach ($recipients as $r)
      <tr><td>@if ($r->contact)<a href="{{ route('portal.contacts.show', [$slug, $r->contact_id]) }}">{{ $r->contact->displayName() }}</a>@else — @endif</td>
        <td class="small mono">{{ $r->address ?? '—' }}</td>
        <td><span class="badge {{ ['sent' => 'ok', 'delivered' => 'ok', 'read' => 'ok', 'failed' => 'err', 'unsubscribed' => 'warn'][$r->status] ?? '' }}">{{ ['pending' => 'în așteptare', 'sent' => 'trimis', 'delivered' => 'livrat', 'read' => 'citit', 'failed' => 'eșuat', 'excluded' => 'exclus', 'unsubscribed' => 'dezabonat'][$r->status] ?? $r->status }}</span></td>
        <td class="small muted">{{ $r->reason }}</td><td class="small muted">{{ $r->sent_at?->setTimezone('Europe/Bucharest')->format('d.m H:i') }}</td>
        @if ($ch === 'email')<td>{{ $r->opened_at ? '✓' : '' }}</td><td>{{ $r->clicked_at ? '✓ '.$r->click_count : '' }}</td>@endif</tr>
    @endforeach
    </tbody>
  </table></div>
  @include('partials.pager', ['paginator' => $recipients])
</div>
@endif
@endsection
