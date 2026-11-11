{{--
  Views (tabs), search and sort above a server-paged list (rule P1). On a phone (Sat 18/10/2026) the tabs and sort fold
  into a Filters panel under the search box; on wider screens they show as before and the column headers sort.
  Params: route, views (key => label, may be empty), view, q, placeholder, sorts (key => label), sort, dir,
  keep (extra query values to carry, optional), clearUrl (optional), clearLabel (optional), search (false: no search box).
--}}
@php
  $views ??= []; $sorts ??= []; $keep ??= []; $search ??= true; $q ??= '';
  $base = array_filter(array_merge($keep, ['q' => $q ?: null]), fn ($v) => $v !== null && $v !== '');
  $clearUrl ??= route($route, array_filter(array_merge($keep, ['view' => ($view ?? 'all') === 'all' ? null : $view])));
@endphp
<div class="listtop">
  <form class="filterbar{{ $search ? '' : ' nosearch' }}" id="listSearch" method="GET" action="{{ route($route) }}" role="search">
    @foreach ($keep as $k => $val)<input type="hidden" name="{{ $k }}" value="{{ $val }}">@endforeach
    @if ($views && $view !== 'all')<input type="hidden" name="view" value="{{ $view }}">@endif
    @if (request()->filled('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
    @if ($search)
      <div class="field grow"><label for="q">Search</label><input class="inp" id="q" name="q" value="{{ $q }}" placeholder="{{ $placeholder }}"></div>
      <button class="btn sec icon" type="submit" aria-label="Search" title="Search"><svg class="ico" aria-hidden="true"><use href="#i-search"/></svg></button>
    @endif
    @if ($views || $sorts)
      <button class="btn sec foldbtn" type="button" aria-expanded="false" aria-controls="listFold" data-fold><svg class="ico"><use href="#i-filter"/></svg>Filters @if ($views && $view !== 'all')<span class="badge b-neu">{{ $views[$view] }}</span>@endif</button>
    @endif
    @if ($q !== '' || ($clearLabel ?? null))<a class="btn gh" href="{{ $clearUrl }}">{{ $clearLabel ?? 'Clear' }}</a>@endif
  </form>
  @if ($views || $sorts)
    <div class="listfold" id="listFold">
      @if ($views)
        <nav class="tabs" aria-label="{{ $label ?? 'Views' }}">
          @foreach ($views as $k => $vl)
            <a class="tabl {{ $view === $k ? 'on' : '' }}" href="{{ route($route, array_merge($base, array_filter(['view' => $k === 'all' ? null : $k]))) }}" @if ($view === $k) aria-current="page" @endif>{{ $vl }}</a>
          @endforeach
        </nav>
      @endif
      @if ($sorts)
        <div class="foldsort">
          <div class="field"><label for="sort">Sort by</label>
            <select class="inp" id="sort" name="sort" form="listSearch">@foreach ($sorts as $k => $sl)<option value="{{ $k }}" @selected($sort === $k)>{{ $sl }}</option>@endforeach</select></div>
          <div class="field"><label for="dir">Order</label>
            <select class="inp" id="dir" name="dir" form="listSearch"><option value="asc" @selected($dir === 'asc')>Ascending</option><option value="desc" @selected($dir === 'desc')>Descending</option></select></div>
          <button class="btn pri" type="submit" form="listSearch">Apply</button>
        </div>
      @endif
    </div>
  @endif
</div>
