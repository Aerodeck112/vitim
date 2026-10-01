{{-- Scorurile pe categorii ale unui site (0–100). $site obligatoriu. --}}
@if ($site->scores)
<div class="scores">
  @foreach (\App\Audit\Guidance::CATEGORIES as $key => $label)
    @php($score = $site->scores[$key] ?? 100)
    <div class="score {{ $score >= 85 ? 'good' : ($score >= 60 ? 'mid' : 'bad') }}"><b>{{ $score }}</b><span>{{ $label }}</span></div>
  @endforeach
</div>
@endif
