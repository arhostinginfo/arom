{{-- (24/11/2026) AMC › Return & Replace › History of one customer: per Location, per entry of the AMC Client Inventory,
     each time it was returned (newest first). Open = no replace date yet. --}}
@php
  $df = config('nursery.date_format');
  $money = fn ($v) => $v !== null ? \App\Support\Money::indian($v, 2) : '';
@endphp
@extends('layouts.app', ['title' => 'Return & Replace history · '.$client->name])

@section('content')
@include('amc.partials.assets')
<div class="pagehd">
  <h1>Return &amp; Replace history: {{ $client->name }}</h1>
  <span class="badge b-neu">{{ $returnCount }} return{{ $returnCount === 1 ? '' : 's' }}</span>
  <span class="sp"></span>
  @can('amc-return.add')<a class="btn add sm" href="{{ route('amc.returns.create') }}"><svg class="ico"><use href="#i-plus"/></svg>Add new</a>@endcan
  <a class="btn gh sm" href="{{ route('amc.returns.index') }}"><svg class="ico"><use href="#i-list"/></svg>Return &amp; Replace list</a>
</div>

@if (count($allLocs) > 1)
<form class="filterbar" method="GET" action="{{ route('amc.returns.history', $client) }}">
  <div class="field"><label for="hloc">Location</label>
    <select class="inp" id="hloc" name="location" onchange="this.form.submit()">
      <option value="" @selected($location === null)>All locations</option>
      @foreach (array_map('strval', $allLocs) as $o)<option value="{{ $o === '' ? \App\Http\Controllers\Amc\AmcReturnController::NO_LOCATION : $o }}" @selected($location === $o)>{{ $o === '' ? '(no Location)' : $o }}</option>@endforeach
    </select></div>
  <noscript><button class="btn sec" type="submit">Show</button></noscript>
</form>
@endif

@forelse ($locs as $loc => $g)
  @php
    $locItems = collect($g['lines'] ?? [])->flatMap(fn ($l) => $byLine->get($l->id, collect()))->merge($g['other'] ?? collect());
    $locOpen = $locItems->filter(fn ($i) => ! $i->return->replace_date)->pluck('amc_return_id')->unique()->count();
  @endphp
  <div class="sectcard amchist" id="loc-{{ md5($loc) }}">
    <div class="sh"><h2>{{ $loc === '' ? '(no Location)' : $loc }}</h2><span class="sp"></span>
      <span class="hint txt">{{ count($g['lines'] ?? []) }} entr{{ count($g['lines'] ?? []) === 1 ? 'y' : 'ies' }} · {{ $locItems->pluck('amc_return_id')->unique()->count() }} return(s) · {{ $locOpen }} open</span></div>
    <div class="sb" style="display:grid;gap:14px">
      @foreach ($g['lines'] ?? [] as $l)
        @php
          $rs = $byLine->get($l->id, collect());
          $st = \App\Services\Amc\AmcReturnService::statusOf($rs->map(fn ($i) => (object) ['request_date' => $i->return->request_date, 'replace_date' => $i->return->replace_date, 'qty' => $i->qty()]));
        @endphp
        <div class="amchentry @if ($markLine === $l->id) mark @endif" id="e-{{ $l->id }}">
          <div class="amchhd">
            <b>{{ $l->location_2 ?: 'Entry '.$l->line_no }}</b>
            <span class="hint txt">{{ collect([$l->planter ? $l->planter->name.($l->planter_qty !== null ? ' ×'.$l->planter_qty : '') : null, $l->plant ? $l->plant->label().($l->plant_qty !== null ? ' ×'.$l->plant_qty : '') : null])->filter()->implode(', ') }}</span>
            <span class="badge {{ ['done' => 'b-ok', 'open' => 'b-warn'][$st['state']] ?? 'b-neu' }}">{{ $st['text'] }}</span>
          </div>
          @include('amc.returns.history-table', ['rs' => $rs])
        </div>
      @endforeach
      @if (! empty($g['other']) && count($g['other']))
        <div class="amchentry">
          <div class="amchhd"><b>Rows without an entry</b><span class="hint txt">saved before the rows came from entries, or their entry was removed from the AMC Client Inventory</span></div>
          @include('amc.returns.history-table', ['rs' => $g['other'], 'showL2' => true])
        </div>
      @endif
    </div>
  </div>
@empty
  <div class="tablecard"><div class="empty">This customer has no entries in the AMC Client Inventory and no Return &amp; Replace entries yet.</div></div>
@endforelse

@if ($markLine || $markReturn)
<script>(function(){var e=document.querySelector('.amchentry.mark,tr.mark');if(e)e.scrollIntoView({block:'center'})})();</script>
@endif
@endsection
