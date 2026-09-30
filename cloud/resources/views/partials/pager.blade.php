@if ($paginator->hasPages())
  <div class="pager">
    @if ($paginator->onFirstPage())<span class="btn btn-s" aria-disabled="true">Înapoi</span>@else<a class="btn btn-s" href="{{ $paginator->previousPageUrl() }}" rel="prev">Înapoi</a>@endif
    <span class="small muted" style="align-self:center">Pagina {{ $paginator->currentPage() }} din {{ $paginator->lastPage() }}</span>
    @if ($paginator->hasMorePages())<a class="btn btn-s" href="{{ $paginator->nextPageUrl() }}" rel="next">Înainte</a>@else<span class="btn btn-s" aria-disabled="true">Înainte</span>@endif
  </div>
@endif
