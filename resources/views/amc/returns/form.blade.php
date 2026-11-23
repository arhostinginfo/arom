{{-- AMC › Return & Replace: an entry with rows (23/11/2026), one per entry of the chosen Location. Customer fills the locations and, from its Active contract, the AMC type and gardener. --}}
@php
  $isNew = ! $r->exists;
  $u = auth()->user();
  $canSave = $isNew ? $u->can('amc-return.add') : $u->can('amc-return.edit');
  $df = config('nursery.date_format');
  $v = fn ($f) => old($f, $r->{$f});
  $d = fn ($f) => old($f, $r->{$f}?->format($df));
  // (22/11/2026) the Location is picked first; its entries in the AMC Client Inventory show below it.
  $loc1 = old('location_1', $isNew ? null : $r->location_1);
  // (23/11/2026) the rows; Location 2 of a posted-back row is read again from the customer's entry
  $lineById = ($client?->lines ?? collect())->keyBy('id');
  $items = array_map(fn ($it) => $it + ['location_2' => $lineById->get((int) ($it['amc_client_line_id'] ?? 0))?->location_2], $items);
  $added = array_filter(array_map(fn ($it) => (string) ($it['amc_client_line_id'] ?? ''), $items));
  $loc1s = ($client?->lines ?? collect())->pluck('location_1')->filter(fn ($x) => $x !== null && $x !== '')->unique()->values();
@endphp
@extends('layouts.app', ['title' => $isNew ? 'Return & Replace' : $r->number()])

@section('content')
@include('amc.partials.assets')
<div class="pagehd">
  <h1>{{ $isNew ? 'Return & Replace' : $r->number().' · '.$client?->name }}</h1>
  <span class="sp"></span>
  @if (! $isNew && $u->can('amc-return.add'))<a class="btn add sm" href="{{ route('amc.returns.create') }}"><svg class="ico"><use href="#i-plus"/></svg>Add new</a>@endif
  <a class="btn gh sm" href="{{ route('amc.returns.index') }}"><svg class="ico"><use href="#i-list"/></svg>Return &amp; Replace list</a>
</div>

@include('amc.partials.errors')

<form method="POST" action="{{ $isNew ? route('amc.returns.store') : route('amc.returns.update', $r) }}" novalidate data-dirty-guard data-saving id="amcReturnForm"
  data-info-url="{{ route('amc.clients.info', ['client' => '__ID__'] + ($isNew ? [] : ['except' => $r->id])) }}">
  @csrf
  @unless ($isNew) @method('PUT') @endunless
  <div class="sectcard">
    <div class="sh"><h2>Return &amp; Replace</h2></div>
    <div class="sb">
      <fieldset @disabled(! $canSave) style="border:0;padding:0;margin:0;min-width:0;display:grid;gap:14px">
      <div class="fgrid">
        <div class="field" style="grid-column:1/-1">
          <label for="amc_client_id">Customer<span class="req">*</span></label>
          <div class="picker" data-picker="field" data-url="{{ route('amc.lookup.clients') }}" data-placeholder="Type a customer name">
            <select class="inp @error('amc_client_id') err @enderror" id="amc_client_id" name="amc_client_id" aria-required="true">
              <option value="">Select</option>
              @foreach ($clients as $id => $name)<option value="{{ $id }}" @selected((string) $v('amc_client_id') === (string) $id)>{{ $name }}</option>@endforeach
            </select>
          </div>
          <span class="hint txt" id="amcContractNote">From the AMC Client Inventory.</span>
          @include('partials.ferr', ['name' => 'amc_client_id'])
        </div>
        <div class="field">
          <label for="amc_location_1">Location</label>
          <select class="inp @error('location_1') err @enderror" id="amc_location_1" name="location_1">
            <option value="">Select a location</option>
            @foreach ($loc1s as $o)<option @selected((string) $loc1 === (string) $o)>{{ $o }}</option>@endforeach
            @if ($loc1 !== null && $loc1 !== '' && ! $loc1s->contains($loc1))<option selected>{{ $loc1 }}</option>@endif
          </select>
          <span class="hint txt">From the customer's lines in the AMC Client Inventory.</span>
          @include('partials.ferr', ['name' => 'location_1'])
        </div>
        <div class="field" style="grid-column:1/-1" id="amcLocEntries" data-loc-returns='@json((object) ($replaced[''] ?? []))' @if (! $loc1) hidden @endif>
          <span class="lbl">Entries under this Location</span>
          <p class="hint txt" id="amcLocSummary" aria-live="polite"></p>
          <div class="tablecard" style="overflow-x:auto">
            <table class="atable rtable">
              <thead><tr><th>Location 2</th><th>Planter</th><th class="r">Planter qty</th><th>Plant</th><th class="r">Plant qty</th><th>Replaced so far</th><th><span class="sr">Add</span></th></tr></thead>
              <tbody id="amcLocRows">
              @foreach ($client?->lines ?? [] as $l)
                @php($sv = ($savedRows ?? collect())->get(\App\Services\Amc\AmcReturnService::keptKey($l->id, $l->amc_plant_id)))
                <tr @class(["clk", "sel" => in_array((string) $l->id, $added, true)]) data-id="{{ $l->id }}" data-l1="{{ $l->location_1 }}" data-l2="{{ $l->location_2 }}" data-no="{{ $l->line_no }}" data-planter="{{ $l->amc_planter_id }}" data-planter-name="{{ $l->planter?->name }}" data-pqty="{{ $l->planter_qty }}" data-plant="{{ $l->amc_plant_id }}" data-plant-name="{{ $l->plant?->name }}" data-size="{{ $sv ? $sv->plant_size : $l->plant?->size }}" data-rate="{{ $sv ? $sv->rate : $l->plant?->rate }}" data-qty="{{ $l->plant_qty }}" @if ((string) $l->location_1 !== (string) $loc1) hidden @endif>
                  <td data-label="Location 2">{{ $l->location_2 }}</td>
                  <td data-label="Planter">{{ $l->planter?->name }}</td>
                  <td data-label="Planter qty" class="r num">{{ $l->planter_qty }}</td>
                  <td data-label="Plant">{{ $l->plant?->label() }}</td>
                  <td data-label="Plant qty" class="r num">{{ $l->plant_qty }}</td>
                  @php($st = $replaced[$l->id] ?? \App\Services\Amc\AmcReturnService::statusOf([]))
                  <td data-label="Replaced so far" data-state="{{ $st['state'] }}"><span class="badge {{ ['done' => 'b-ok', 'open' => 'b-warn'][$st['state']] ?? 'b-neu' }}">{{ $st['text'] }}</span></td>
                  <td><button class="btn sec sm" type="button" data-add-entry>{{ in_array((string) $l->id, $added, true) ? 'Added' : 'Add' }}</button></td>
                </tr>
              @endforeach
              </tbody>
            </table>
          </div>
          <span class="hint txt">Click an entry (or its Add button) to add it to the return below; added entries are marked.</span>
        </div>
        <div class="field" style="grid-column:1/-1" id="amcItems">
          <span class="lbl">Return rows<span class="req">*</span></span>
          <div class="tablecard" style="overflow-x:auto">
            <table class="atable rtable">
              <thead><tr><th>Location 2</th><th>Planter</th><th>Planter qty</th><th>Plant</th><th>Plant size</th><th>Plant qty</th><th class="r">Rate (₹)</th><th class="r">Amount (₹)</th><th>Quality<span class="req">*</span></th><th><span class="sr">Remove</span></th></tr></thead>
              <tbody id="amcItemRows" data-next="{{ $items ? max(array_map('intval', array_keys($items))) + 1 : 0 }}">
              @foreach ($items as $n => $it)
                @include('amc.returns.item-row', ['n' => $n, 'it' => $it, 'entry' => $lineById->get((int) ($it['amc_client_line_id'] ?? 0))])
              @endforeach
              </tbody>
              @php($total = collect($items)->sum(function ($it) use ($lineById, $plants) {
                  $e = $lineById->get((int) ($it['amc_client_line_id'] ?? 0));
                  $rate = ! empty($it['saved']) ? $it['saved_rate'] : ($plants[$e ? $e->amc_plant_id : ($it['amc_plant_id'] ?? 0)] ?? null)?->rate;
                  return $rate !== null && is_numeric($it['plant_qty'] ?? null) ? (float) $rate * (int) $it['plant_qty'] : 0;
              }))
              <tfoot><tr><td colspan="7" class="r"><b>Total amount</b></td><td class="r num"><b id="amcItemTotal">{{ \App\Support\Money::indian($total, 2) }}</b></td><td colspan="2"></td></tr></tfoot>
            </table>
          </div>
          <p class="hint txt" id="amcItemsEmpty" @if (count($items)) hidden @endif>No rows yet: choose the Location, then click its entries above.</p>
          <span class="hint txt">Location 2, Planter and Plant come from the AMC Client Inventory, Plant size and Rate from the Plant master; only the planter qty, plant qty (each up to the qty in the inventory) and the quality are entered. Amount = Rate × Plant qty.</span>
          @include('partials.ferr', ['name' => 'items'])
        </div>
        <div class="field">
          <label for="request_date">Request date<span class="req">*</span></label>
          <input class="inp num @error('request_date') err @enderror" id="request_date" name="request_date" value="{{ $d('request_date') }}" placeholder="dd/mm/yyyy" inputmode="numeric" required>
          @include('partials.ferr', ['name' => 'request_date'])
        </div>
        <div class="field">
          <label for="replace_date">Replace date</label>
          <input class="inp num @error('replace_date') err @enderror" id="replace_date" name="replace_date" value="{{ $d('replace_date') }}" placeholder="dd/mm/yyyy" inputmode="numeric">
          @include('partials.ferr', ['name' => 'replace_date'])
        </div>
        <div class="field">
          <label for="reported_by_id">Reported by</label>
          <div class="picker" data-picker="field" data-url="{{ route('amc.lookup.employees') }}" data-placeholder="Type a name or code">
            <select class="inp @error('reported_by_id') err @enderror" id="reported_by_id" name="reported_by_id">
              <option value="">None</option>
              @foreach ($reporters as $id => $label)<option value="{{ $id }}" @selected((string) $v('reported_by_id') === (string) $id)>{{ $label }}</option>@endforeach
            </select>
          </div>
          <span class="hint txt">From the AMC Employee master.</span>
          @include('partials.ferr', ['name' => 'reported_by_id'])
        </div>
        <div class="field">
          <label for="amc_type_id">AMC type</label>
          <select class="inp @error('amc_type_id') err @enderror" id="amc_type_id" name="amc_type_id">
            <option value="">Select</option>
            @foreach ($types as $o)<option value="{{ $o->id }}" @selected((string) $v('amc_type_id') === (string) $o->id)>{{ $o->name }}</option>@endforeach
          </select>
          <span class="hint txt">Filled from the customer's Active contract; can be changed.</span>
          @include('partials.ferr', ['name' => 'amc_type_id'])
        </div>
        <div class="field">
          <label for="gardener_id">Gardener</label>
          <div class="picker" data-picker="field" data-url="{{ route('amc.lookup.employees', ['role' => 'Gardener']) }}" data-placeholder="Type a name or code">
            <select class="inp @error('gardener_id') err @enderror" id="gardener_id" name="gardener_id">
              <option value="">None</option>
              @foreach ($gardeners as $id => $label)<option value="{{ $id }}" @selected((string) $v('gardener_id') === (string) $id)>{{ $label }}</option>@endforeach
            </select>
          </div>
          <span class="hint txt">The contract's gardener by default.</span>
          @include('partials.ferr', ['name' => 'gardener_id'])
        </div>
        <div class="field" style="grid-column:1/-1">
          <label for="return_remark">Return remark</label>
          <input class="inp @error('return_remark') err @enderror" id="return_remark" name="return_remark" value="{{ $v('return_remark') }}" maxlength="500">
          @include('partials.ferr', ['name' => 'return_remark'])
        </div>
      </div>
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>Fields marked * are mandatory. Dates as dd/mm/yyyy; the replace date cannot be before the request date. Location 2 and the plant size are saved with each row as they are now.</span></div>
      </fieldset>
    </div>
  </div>
  @include('crm.partials.audit', ['m' => $r])
  <div class="formfoot">
    @if (! $isNew && $u->can('amc-return.delete'))
      <button class="btn sec" type="submit" form="rrDelForm" style="color:var(--error-700)"><svg class="ico"><use href="#i-x"/></svg>Delete entry</button>
    @endif
    <span class="sp"></span>
    @if ($canSave)<button class="btn {{ $isNew ? 'add' : 'pri' }}" type="submit"><svg class="ico"><use href="#i-{{ $isNew ? 'plus' : 'check' }}"/></svg>Submit</button>@endif
  </div>
</form>
@if (! $isNew && $u->can('amc-return.delete'))
  <form id="rrDelForm" method="POST" action="{{ route('amc.returns.destroy', $r) }}" data-confirm="Delete {{ $r->number() }}?">@csrf @method('DELETE')</form>
@endif
{{-- the row a click adds: amc.js fills it from the entry (an entry has its planter / plant, so no hidden ids) --}}
<template id="amcItemTpl">@include('amc.returns.item-row', ['n' => '__N__', 'it' => [], 'entry' => new \App\Models\Amc\AmcClientLine()])</template>
@include('crm.partials.history', ['history' => $history])
@endsection
