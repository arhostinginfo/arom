{{--
  Entry picker (decisions D11, D23, UX2): one searchable field. Empty, it lists the latest 20 entries
  with their status; typing a number, party or date searches on the server. Arrow keys only move
  the highlight; an entry opens on Enter, click or pick. Without JavaScript the old select + Find form shows.
--}}
@php($currentOption = collect($options)->firstWhere('on', true))
<div class="entrybar">
  <div class="picker entrypick" data-picker="nav" data-url="{{ $lookupUrl }}" style="flex:1 1 320px;min-width:0;max-width:520px;display:none">
    {{-- Sat 18/10/2026: a search icon instead of the word "Entry"; the label is still read out. --}}
    <label for="entryPick" class="entryico" title="Search entries"><svg class="ico" aria-hidden="true"><use href="#i-search"/></svg><span class="sr-only">Search entries</span></label>
    <input class="inp" id="entryPick" value="{{ $currentOption['label'] ?? '' }}" data-current="{{ $currentOption['label'] ?? '' }}" placeholder="{{ $current ? $placeholder : $newLabel.' · '.$placeholder }}" autocomplete="off">
  </div>
  <noscript>
    <form method="GET" action="{{ $action }}" role="search" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <label for="entrySel">Entry</label>
      <select class="inp" id="entrySel" name="id">
        @unless ($current)<option value="" selected>{{ $newLabel }}</option>@endunless
        @foreach ($options as $o)<option value="{{ $o['id'] }}" @selected($o['on'])>{{ $o['label'] }}</option>@endforeach
      </select>
      <input class="inp" name="q" value="{{ $q }}" placeholder="{{ $placeholder }}" aria-label="Search entries">
      <button class="btn gh sm" type="submit"><svg class="ico"><use href="#i-search"/></svg>Find</button>
    </form>
    <div class="small muted">Matches: @foreach ($options as $o)<a href="{{ $o['url'] }}">{{ $o['label'] }}</a>@if (! $loop->last), @endif @endforeach</div>
  </noscript>
  @if ($addUrl)<a class="btn add sm" href="{{ $addUrl }}"><svg class="ico"><use href="#i-plus"/></svg>Add new</a>@endif
 
  <div class="capbtns">
    @foreach ($buttons as $b)
      @if (isset($b['kind']))
        @include('partials.iobtn', array_intersect_key($b, array_flip(['kind', 'label', 'href', 'open'])))
      @elseif (isset($b['open']))
        <button class="btn gh sm" type="button" data-open="{{ $b['open'] }}"><svg class="ico"><use href="#i-{{ $b['icon'] }}"/></svg>{{ $b['label'] }}</button>
      @elseif (! empty($b['nav']))
        <a class="btn gh sm" href="{{ $b['href'] }}"><svg class="ico"><use href="#i-{{ $b['icon'] }}"/></svg>{{ $b['label'] }}</a>
      @else
        <a class="btn gh sm" href="{{ $b['href'] }}" download><svg class="ico"><use href="#i-{{ $b['icon'] }}"/></svg>{{ $b['label'] }}</a>
      @endif
    @endforeach
  </div>
</div>
@if ($q !== '' && ! count($options))
  <div class="alert info"><svg class="ico"><use href="#i-info"/></svg><span>No entry matches “{{ $q }}”.</span></div>
@endif
