<details class="add-step"><summary aria-label="Adaugă un pas">+</summary>
  <form method="post" action="{{ route('portal.flows.steps.store', [$organization->slug, $flow->id]) }}" style="display:flex;gap:6px;flex-wrap:wrap;justify-content:center;margin-top:6px">@csrf
    @if ($after)<input type="hidden" name="after" value="{{ $after->id }}">@elseif ($parent)<input type="hidden" name="parent" value="{{ $parent->id }}"><input type="hidden" name="branch" value="{{ $branch }}">@endif
    @foreach ($types as $k => $l)<button class="btn btn-s" type="submit" name="type" value="{{ $k }}">{{ $l }}</button>@endforeach
  </form>
</details>
