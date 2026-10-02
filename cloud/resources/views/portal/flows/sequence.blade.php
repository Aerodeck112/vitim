{{-- O secvență de pași (rădăcina sau o ramură da / nu a unei condiții). --}}
@php($key = ($parent?->id ?? 0).':'.($branch ?? ''))
@php($list = $steps[$key] ?? collect())
@php($types = \App\Models\FlowStep::TYPES)
@php($add = function ($after) use ($flow, $organization, $parent, $branch, $types) { return view('portal.flows.add', compact('flow', 'organization', 'parent', 'branch', 'types', 'after')); })
<div class="seq">
  {!! $add(null) !!}
  @foreach ($list as $step)
    @php($s = $stats['steps'][$step->id] ?? null)
    <div class="step step-{{ $step->type }}" id="step-{{ $step->id }}">
      <details @if (session('ok') && request()->fullUrl() === url()->current()) @endif>
        <summary><span class="step-type">{{ $types[$step->type] }}</span>
          <span class="step-sum">@switch($step->type)
            @case('wait') {{ $step->conf('amount') }} {{ ['minutes' => 'minute', 'hours' => 'ore', 'days' => 'zile'][$step->conf('unit', 'days')] }} @break
            @case('email') {{ $step->conf('subject') ?: '(fără subiect)' }} @break
            @case('sms') {{ \Illuminate\Support\Str::limit((string) $step->conf('body'), 60) ?: '(gol)' }} @break
            @case('whatsapp') șablon {{ $step->conf('template')['name'] ?? '' ?: '(neales)' }} @break
            @case('condition') {{ \App\Services\FlowService::describeCondition((array) $step->conf('definition'), $lists->all()) }} @break
            @case('list_add') {{ $lists[$step->conf('list_id')] ?? '(alege lista)' }} @break
          @endswitch</span>
          @if ($s)<span class="step-stats">{{ $s->sent }} trimise @if ($step->type === 'email') · {{ $s->sent ? round(100 * $s->opened / $s->sent) : 0 }}% deschideri · {{ $s->sent ? round(100 * $s->clicked / $s->sent) : 0 }}% click @endif @if ($s->skipped) · {{ $s->skipped }} sărite @endif</span>@endif
        </summary>
        <form method="post" action="{{ route('portal.flows.steps.update', [$organization->slug, $flow->id, $step->id]) }}" style="margin-top:10px">@csrf @method('put')
          @switch($step->type)
            @case('wait')
              <div style="display:flex;gap:8px;align-items:center">Așteaptă <input type="number" name="amount" min="1" max="365" value="{{ $step->conf('amount', 1) }}" style="width:90px">
                <select name="unit" style="width:auto">@foreach (['minutes' => 'minute', 'hours' => 'ore', 'days' => 'zile'] as $k => $l)<option value="{{ $k }}" @selected($step->conf('unit') === $k)>{{ $l }}</option>@endforeach</select></div>
              @break
            @case('email')
              <div class="fl"><label>Subiect</label><input type="text" name="subject" value="{{ $step->conf('subject') }}" maxlength="200"></div>
              @if (! empty($step->conf('blocks')))
                <input type="hidden" name="body" value="{{ $step->conf('body') }}">
                <div class="alert" style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap">Construit cu editorul vizual ({{ count($step->conf('blocks')) }} blocuri).<a class="btn btn-s btn-p" href="{{ route('portal.flows.steps.design', [$organization->slug, $flow->id, $step->id]) }}">Deschide editorul vizual</a></div>
              @else
              <div class="fl"><label>Textul emailului</label><textarea name="body" rows="8">{{ $step->conf('body') }}</textarea>
                <div class="hint">Variabile: @{{prenume}}, @{{nume}}, @{{firma}}; din eveniment: @{{produse}}, @{{total}}, @{{link_cos}}, @{{produs}}, @{{link_produs}}. Vrei imagini și butoane? <a href="{{ route('portal.flows.steps.design', [$organization->slug, $flow->id, $step->id]) }}">Deschide editorul vizual</a></div></div>
              @endif
              <label class="chk"><input type="checkbox" name="ignore_smart_sending" value="1" @checked($step->conf('ignore_smart_sending'))> Trimite chiar dacă a primit alt email recent (fără smart sending)</label>
              @break
            @case('sms')
              <div class="fl"><label>Textul SMS-ului</label><textarea name="body" rows="3" maxlength="900">{{ $step->conf('body') }}</textarea><div class="hint">Linkul de dezabonare se adaugă automat. Pleacă doar între 9 și 20.</div></div>
              <label class="chk"><input type="checkbox" name="ignore_smart_sending" value="1" @checked($step->conf('ignore_smart_sending'))> Fără smart sending</label>
              @break
            @case('whatsapp')
              <div class="row"><div class="fl"><label>Șablon aprobat de Meta</label><input type="text" name="template_name" value="{{ $step->conf('template')['name'] ?? '' }}" placeholder="oferta_primavara"></div>
                <div class="fl"><label>Limba</label><input type="text" name="template_language" value="{{ $step->conf('template')['language'] ?? 'ro' }}"></div></div>
              <div class="fl"><label>Variabile (câte una pe rând)</label><textarea name="template_variables" rows="2">{{ implode("\n", $step->conf('template')['variables'] ?? []) }}</textarea></div>
              @break
            @case('condition')
              @php($def = (array) $step->conf('definition'))
              <p class="small muted" style="margin:0 0 8px">Contactul merge pe ramura <strong>Da</strong> dacă îndeplinește condițiile, altfel pe <strong>Nu</strong>.</p>
              <select name="definition[match]" style="width:auto;margin-bottom:8px"><option value="all" @selected(($def['match'] ?? 'all') === 'all')>toate condițiile</option><option value="any" @selected(($def['match'] ?? '') === 'any')>oricare condiție</option></select>
              @php($conds = array_values((array) ($def['conditions'] ?? [])))
              @foreach ($conds + [count($conds) => ['type' => 'since_start', 'event' => '', 'op' => 'did'], count($conds) + 1 => ['type' => 'segment', 'segment_id' => '', 'op' => 'in']] as $i => $c)
                @if (($c['type'] ?? '') === 'since_start')
                  <div style="display:flex;gap:6px;align-items:center;margin-bottom:6px;flex-wrap:wrap"><input type="hidden" name="definition[conditions][{{ $i }}][type]" value="since_start">De la intrarea în flux
                    <select name="definition[conditions][{{ $i }}][op]" style="width:auto"><option value="did" @selected(($c['op'] ?? '') === 'did')>a făcut</option><option value="not" @selected(($c['op'] ?? '') === 'not')>nu a făcut</option></select>
                    <select name="definition[conditions][{{ $i }}][event]" style="width:auto"><option value="">— (fără condiție)</option>@foreach ($events as $k => [$l])<option value="{{ $k }}" @selected(($c['event'] ?? '') === $k)>{{ mb_strtolower($l) }}</option>@endforeach</select></div>
                @elseif (($c['type'] ?? '') === 'segment')
                  <div style="display:flex;gap:6px;align-items:center;margin-bottom:6px;flex-wrap:wrap"><input type="hidden" name="definition[conditions][{{ $i }}][type]" value="segment">
                    <select name="definition[conditions][{{ $i }}][op]" style="width:auto"><option value="in" @selected(($c['op'] ?? '') === 'in')>e în segmentul</option><option value="not_in" @selected(($c['op'] ?? '') === 'not_in')>nu e în segmentul</option></select>
                    <select name="definition[conditions][{{ $i }}][segment_id]" style="width:auto"><option value="">— (fără condiție)</option>@foreach ($segments as $id => $n)<option value="{{ $id }}" @selected((int) ($c['segment_id'] ?? 0) === $id)>{{ $n }}</option>@endforeach</select></div>
                @else
                  <div class="small muted" style="margin-bottom:6px">+ {{ \App\Services\SegmentQuery::describe(['conditions' => [$c]], $lists->all()) }}
                    @foreach ($c as $k => $v)<input type="hidden" name="definition[conditions][{{ $i }}][{{ $k }}]" value="{{ is_scalar($v) ? $v : '' }}">@endforeach</div>
                @endif
              @endforeach
              @break
            @case('list_add')
              <select name="list_id"><option value="">— alege lista —</option>@foreach ($lists as $id => $n)<option value="{{ $id }}" @selected((int) $step->conf('list_id') === $id)>{{ $n }}</option>@endforeach</select>
              @break
          @endswitch
          <div style="display:flex;gap:8px;margin-top:10px"><button class="btn btn-p btn-s" type="submit">Salvează pasul</button></div>
        </form>
        <form method="post" action="{{ route('portal.flows.steps.destroy', [$organization->slug, $flow->id, $step->id]) }}" onsubmit="return confirm('Ștergi pasul{{ $step->type === 'condition' ? ' și ramurile lui' : '' }}?')" style="margin-top:6px">@csrf @method('delete')<button class="btn btn-s btn-d" type="submit">Șterge pasul</button></form>
      </details>
      @if ($step->type === 'condition')
        <div class="branches">
          @foreach (['yes' => 'Da', 'no' => 'Nu'] as $b => $label)
            <div class="branch"><div class="branch-label {{ $b }}">{{ $label }}</div>
              @include('portal.flows.sequence', ['parent' => $step, 'branch' => $b])</div>
          @endforeach
        </div>
      @endif
    </div>
    @if ($step->type !== 'condition'){!! $add($step) !!}@endif
  @endforeach
  @if ($list->isEmpty() || $list->last()->type === 'condition')<div class="step-end">Final</div>@endif
</div>
