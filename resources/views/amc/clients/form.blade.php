{{-- AMC › AMC Client Inventory: one customer, its business segment and location lines. Gardener: an active Gardener of the AMC Employee master (22/11/2026). --}}
@php
  $isNew = ! $client->exists;
  $u = auth()->user();
  $canSave = $isNew ? $u->can('amc-client.add') : $u->can('amc-client.edit');
  $v = fn ($f) => old($f, $client->{$f});
  $lines = old('lines') ?? $client->lines->map(fn ($l) => $l->only(['id', 'location_1', 'location_2', 'amc_planter_id', 'planter_qty', 'amc_plant_id', 'plant_qty']))->all();
  if (! $lines) { $lines = [[]]; }
  $contractGardener = $client->activeContract?->gardener;
@endphp
@extends('layouts.app', ['title' => $isNew ? 'Add AMC client' : $client->name])

@section('content')
@include('amc.partials.assets')
<div class="pagehd">
  <h1>{{ $isNew ? 'Add AMC client' : $client->name }}</h1>
  <span class="sp"></span>
  @if (! $isNew && $u->can('amc-contract.add'))<a class="btn gh sm" href="{{ route('amc.contract.create', ['client' => $client->id]) }}"><svg class="ico"><use href="#i-file"/></svg>New contract</a>@endif
  @if (! $isNew && $u->can('amc-client.add'))<a class="btn add sm" href="{{ route('amc.clients.create') }}"><svg class="ico"><use href="#i-plus"/></svg>Add new</a>@endif
  <a class="btn gh sm" href="{{ route('amc.clients.index') }}"><svg class="ico"><use href="#i-list"/></svg>Client list</a>
</div>

@include('amc.partials.errors')

<form method="POST" action="{{ $isNew ? route('amc.clients.store') : route('amc.clients.update', $client) }}" novalidate data-dirty-guard data-saving>
  @csrf
  @unless ($isNew) @method('PUT') @endunless
  <div class="sectcard">
    <div class="sh"><h2>Customer</h2></div>
    <div class="sb">
      <fieldset @disabled(! $canSave) style="border:0;padding:0;margin:0;min-width:0;display:grid;gap:14px">
      <div class="fgrid">
        <div class="field">
          <label for="party_id">Customer name<span class="req">*</span></label>
          <div class="picker" data-picker="field" data-url="{{ route('amc.lookup.parties', array_filter(['keep' => $client->party_id])) }}" data-placeholder="Type a customer name">
            <select class="inp @error('party_id') err @enderror @error('name') err @enderror" id="party_id" name="party_id" aria-required="true">
              <option value="">Select a customer</option>
              @foreach ($parties as $id => $label)<option value="{{ $id }}" @selected((string) old('party_id', $client->party_id) === (string) $id)>{{ $label }}</option>@endforeach
            </select>
          </div>
          <span class="hint txt">Active customers of the Party master (Admin › Masters › Parties).@if (! $isNew && ! $client->party_id) Not linked yet: was "{{ $client->name }}".@endif</span>
          @include('partials.ferr', ['name' => 'party_id'])
          @include('partials.ferr', ['name' => 'name'])
        </div>
        <div class="field">
          <label for="amc_vertical_id">Business segment<span class="req">*</span></label>
          <select class="inp @error('amc_vertical_id') err @enderror" id="amc_vertical_id" name="amc_vertical_id" aria-required="true">
            <option value="">Select</option>
            @foreach ($verticals as $o)<option value="{{ $o->id }}" @selected((string) $v('amc_vertical_id') === (string) $o->id)>{{ $o->name }}</option>@endforeach
          </select>
          <span class="hint txt">From the Business segment master.</span>
          @include('partials.ferr', ['name' => 'amc_vertical_id'])
        </div>
        <div class="field">
          <label for="gardener_id">Gardener name</label>
          <div class="picker" data-picker="field" data-url="{{ route('amc.lookup.employees', ['role' => 'Gardener']) }}" data-placeholder="Type a name or code">
            <select class="inp @error('gardener_id') err @enderror" id="gardener_id" name="gardener_id">
              <option value="">None</option>
              @foreach ($gardeners as $id => $label)<option value="{{ $id }}" @selected((string) old('gardener_id', $client->gardener_id) === (string) $id)>{{ $label }}</option>@endforeach
            </select>
          </div>
          <span class="hint txt">Active AMC employees whose role is Gardener. A new contract for this client starts with this gardener.@if ($contractGardener && $contractGardener->id !== $client->gardener_id) The Active contract has {{ $contractGardener->label() }}.@endif</span>
          @include('partials.ferr', ['name' => 'gardener_id'])
        </div>
      </div>

      {{-- (22/11/2026) Grouped by Location 1: each Location 1 is typed once, with its entries (Location 2, planter, plant
           and quantities) below it. Saved as one line per entry, each with its Location 1, so import and export are unchanged. --}}
      @php
        $groups = [];
        foreach ($lines as $i => $l) { $groups[(string) ($l['location_1'] ?? '')][$i] = $l; }
        $nextKey = $lines ? max(array_map('intval', array_keys($lines))) + 1 : 1;
      @endphp
      <div class="lbl">Locations, planters and plants</div>
      <div id="amcLocs" class="amclocs" data-next="{{ $nextKey }}">
        @foreach ($groups as $loc1 => $rows)
          @php($loc1Err = collect(array_keys($rows))->map(fn ($i) => $errors->first("lines.$i.location_1"))->filter()->first())
          <div class="amcloc" data-loc>
            <div class="amcloch">
              <div class="field grow">
                <label>Location</label>
                <input class="inp {{ $loc1Err ? 'err' : '' }}" value="{{ $loc1 }}" maxlength="150" placeholder="e.g. Tower A" aria-label="Location" data-loc1>
                @if ($loc1Err)<span class="ferr">{{ $loc1Err }}</span>@endif
              </div>
              @if ($canSave)<button class="btn gh sm" type="button" data-del-loc style="color:var(--error-700)"><svg class="ico"><use href="#i-x"/></svg>Remove location</button>@endif
            </div>
            <div class="tablecard" style="overflow-x:auto">
              <table class="atable lines rtable">
                <thead><tr><th style="min-width:150px">Location 2</th><th style="min-width:150px">Planter</th><th class="r" style="min-width:90px">Planter qty</th><th style="min-width:150px">Plant</th><th class="r" style="min-width:90px">Plant qty</th><th><span class="sr">Remove</span></th></tr></thead>
                <tbody data-rows>
                  @foreach ($rows as $i => $l)
                    @include('amc.clients.line-row', ['i' => $i, 'l' => $l, 'loc1' => $loc1])
                  @endforeach
                </tbody>
              </table>
            </div>
            @if ($canSave)<div><button class="btn gh sm" type="button" data-add-row><svg class="ico"><use href="#i-plus"/></svg>Add row under this location</button></div>@endif
          </div>
        @endforeach
      </div>
      @include('partials.ferr', ['name' => 'lines'])
      @if ($canSave)<div><button class="btn add sm" type="button" data-add-loc><svg class="ico"><use href="#i-plus"/></svg>Add Location</button></div>@endif
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>Fields marked * are mandatory. The customer name cannot repeat. Type each Location once (for example "Tower A") and add its entries below it: Location 2 (for example "Floor 5 - Lobby"), a planter and a plant with their quantities. An empty entry is not saved. Planter and Plant come from their AMC masters.</span></div>
      </fieldset>
    </div>
  </div>
  @include('crm.partials.audit', ['m' => $client])
  <div class="formfoot">
    <span class="sp"></span>
    @if ($canSave)<button class="btn {{ $isNew ? 'add' : 'pri' }}" type="submit"><svg class="ico"><use href="#i-{{ $isNew ? 'plus' : 'check' }}"/></svg>Submit</button>@endif
  </div>
</form>

{{-- A new entry row: active planters and plants only. --}}
<template id="amcRowTpl">@include('amc.clients.line-row', ['i' => '__i__', 'l' => [], 'loc1' => '', 'planters' => $planters->where('is_active', true), 'plants' => $plants->where('is_active', true)])</template>
<template id="amcLocTpl">
  <div class="amcloc" data-loc>
    <div class="amcloch">
      <div class="field grow"><label>Location</label><input class="inp" maxlength="150" placeholder="e.g. Tower A" aria-label="Location" data-loc1></div>
      <button class="btn gh sm" type="button" data-del-loc style="color:var(--error-700)"><svg class="ico"><use href="#i-x"/></svg>Remove location</button>
    </div>
    <div class="tablecard" style="overflow-x:auto">
      <table class="atable lines rtable">
        <thead><tr><th style="min-width:150px">Location 2</th><th style="min-width:150px">Planter</th><th class="r" style="min-width:90px">Planter qty</th><th style="min-width:150px">Plant</th><th class="r" style="min-width:90px">Plant qty</th><th><span class="sr">Remove</span></th></tr></thead>
        <tbody data-rows></tbody>
      </table>
    </div>
    <div><button class="btn gh sm" type="button" data-add-row><svg class="ico"><use href="#i-plus"/></svg>Add row under this location</button></div>
  </div>
</template>
@include('crm.partials.history', ['history' => $history])
@endsection
