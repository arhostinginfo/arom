{{--
  Pager for every list (rule P1 in docs/ARCHITECTURE.md). Works with paginate() (page numbers and a total)
  and simplePaginate() (Previous / Next only, no COUNT, for very large tables). Page size 25, 50 or 100;
  page, filters and sort stay in the URL so links and Back work.
--}}
@php($full = $p instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
@if ($p->count() > 0 || ! $p->onFirstPage())
<div class="pager">
  <span>
    @if ($p->count() > 0)Showing {{ $p->firstItem() }} to {{ $p->lastItem() }}@if ($full) of {{ number_format($p->total()) }}@endif @else No more entries @endif
  </span>
  <form method="GET" class="perpage">
    @foreach (request()->except(['per_page', 'page']) as $k => $v)
      @if (is_scalar($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
    @endforeach
    <label for="perPage">Rows per page</label>
    <select class="inp sm" id="perPage" name="per_page" onchange="this.form.submit()">
      @foreach (\App\Support\ListQuery::PER_PAGE as $n)<option value="{{ $n }}" @selected($p->perPage() === $n)>{{ $n }}</option>@endforeach
    </select>
    <noscript><button class="btn gh sm" type="submit">Show</button></noscript>
  </form>
  @if ($p->hasPages())
  <nav class="pg" aria-label="Pages">
    @if ($p->onFirstPage())<span class="dis" aria-hidden="true"><svg class="ico" style="width:14px"><use href="#i-left"/></svg></span>@else<a href="{{ $p->previousPageUrl() }}" aria-label="Previous page"><svg class="ico" style="width:14px"><use href="#i-left"/></svg></a>@endif
    @if ($full)
      @foreach ($p->getUrlRange(max(1, $p->currentPage() - 2), min($p->lastPage(), $p->currentPage() + 2)) as $n => $link)
        @if ($n === $p->currentPage())<span class="on" aria-current="page">{{ $n }}</span>@else<a href="{{ $link }}">{{ $n }}</a>@endif
      @endforeach
    @else
      <span class="on" aria-current="page">{{ $p->currentPage() }}</span>
    @endif
    @if ($p->hasMorePages())<a href="{{ $p->nextPageUrl() }}" aria-label="Next page"><svg class="ico" style="width:14px"><use href="#i-right"/></svg></a>@else<span class="dis" aria-hidden="true"><svg class="ico" style="width:14px"><use href="#i-right"/></svg></span>@endif
  </nav>
  @endif
</div>
@endif
