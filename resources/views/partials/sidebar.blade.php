{{-- Same markup as the prototype's left menu (design file section 9), built from the user's permissions. --}}
<div class="lg"><div class="brandmark">N</div><div class="bt"><span>Nursery</span><small id="sideUnit">{{ $currentUnit?->name ?? 'No unit' }} · FY {{ $financialYear }}</small></div></div>
{{-- With the menu bar on top (22/10/2026), a wide screen shows here only the reports of the menu selected there; its
     pages open from the bar (nav.scoped, app.css). The phone and tablet menu (drawer) still lists every menu with its
     pages and reports, grouped as before. --}}
<nav @if ($menuBar['selected']) class="scoped" data-selected="{{ $menuBar['selected'] }}" @endif>
  @if ($menuBar['selected'] && ! $menuBar['reports'])
    <p class="norep">No reports for this menu.</p>
  @endif
  @foreach ($menu as $group => $items)
    @if ($group !== '')
      <div class="gl">{{ $group }}</div>
    @else
      <div class="gsep" role="separator"></div>
    @endif
    @foreach ($items as $it)
      @php($hasSub = $it['subs'] || $it['reports'])
      <a class="it{{ $it['on'] ? ' on' : '' }}" href="{{ $it['url'] }}" @if ($it['on']) aria-current="page" @endif @if ($hasSub) aria-expanded="{{ $it['on'] ? 'true' : 'false' }}" @endif @if ($hasSub && ! $it['on']) aria-controls="fly-{{ $it['key'] }}" data-fly @endif>
        @if ($it['on'])<span class="pill" aria-hidden="true"></span>@endif
        <span class="ib"><svg class="ico"><use href="#i-{{ $it['icon'] }}"/></svg></span><span class="tx">{{ $it['title'] }}</span>
        @if ($hasSub)<svg class="ico chev" aria-hidden="true"><use href="#i-down"/></svg>@endif
      </a>
      @if ($it['on'] && $it['key'] === 'reports')
        <div class="subs rep">
          {{-- Each group folds (js/app.js): on a wide screen, groups that do not fit the height close, so the side
               menu never needs a scroll bar; the group of the open report stays open. --}}
          @foreach ($it['reports'] as $g => $links)
            <div class="rg">
              <button class="rgrp rgt" type="button" aria-expanded="true" aria-controls="rgl-{{ $loop->index }}">{{ $g }}<svg class="ico" aria-hidden="true"><use href="#i-down"/></svg></button>
              <div class="rgl" id="rgl-{{ $loop->index }}">
                @foreach ($links as $r)
                  <a class="repl{{ $r['on'] ? ' on' : '' }}" href="{{ $r['url'] }}" @if ($r['on']) aria-current="page" @endif>{{ $r['title'] }}</a>
                @endforeach
              </div>
            </div>
          @endforeach
        </div>
      @elseif ($it['on'])
        <div class="subs{{ $it['reports'] ? '' : ' nore' }}">
          @foreach ($it['subs'] as $s)
            <a class="pg{{ $s['on'] ? ' on' : '' }}" href="{{ $s['url'] }}" @if ($s['on']) aria-current="page" @endif>{{ $s['title'] }}</a>
          @endforeach
          @if ($it['reports'])
            <div class="rgrp">{{ $it['title'] }} reports</div>
            @foreach ($it['reports'] as $r)
              <a class="repl{{ $r['on'] ? ' on' : '' }}" href="{{ $r['url'] }}" @if ($r['on']) aria-current="page" @endif>{{ $r['title'] }}</a>
            @endforeach
          @endif
        </div>
      @elseif ($hasSub)
        {{-- The other modules' sub-items open beside the menu on hover or keyboard focus (Sat 18/10/2026); a click still opens the module. --}}
        <div class="navfly" id="fly-{{ $it['key'] }}" role="group" aria-label="{{ $it['title'] }}" hidden>
          <div class="flyh">{{ $it['title'] }}</div>
          @if ($it['key'] === 'reports')
            @foreach ($it['reports'] as $g => $links)
              <div class="rgrp">{{ $g }}</div>
              @foreach ($links as $r)<a href="{{ $r['url'] }}">{{ $r['title'] }}</a>@endforeach
            @endforeach
          @else
            @foreach ($it['subs'] as $s)<a href="{{ $s['url'] }}">{{ $s['title'] }}</a>@endforeach
            @if ($it['reports'])
              <div class="rgrp">{{ $it['title'] }} reports</div>
              @foreach ($it['reports'] as $r)<a href="{{ $r['url'] }}">{{ $r['title'] }}</a>@endforeach
            @endif
          @endif
        </div>
      @endif
    @endforeach
  @endforeach
</nav>
<div class="me"><span class="avatar">{{ auth()->user()->initials() }}</span><div class="mt"><b>{{ auth()->user()->name }}</b><span class="rc">{{ auth()->user()->roleName() }}</span></div></div>
