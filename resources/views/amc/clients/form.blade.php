{{-- AMC › AMC Client Master: one customer, its business segment and location lines. Gardener comes from its Active contract. --}}
@php
  $isNew = ! $client->exists;
  $u = auth()->user();
  $canSave = $isNew ? $u->can('amc-client.add') : $u->can('amc-client.edit');
  $v = fn ($f) => old($f, $client->{$f});
  $lines = old('lines') ?? $client->lines->map(fn ($l) => $l->only(['id', 'location_1', 'location_2', 'amc_planter_id', 'planter_qty', 'amc_plant_id', 'plant_qty']))->all();
  if (! $lines) { $lines = [[]]; }
  $gardener = $client->activeContract?->gardener;
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
          <label for="name">Customer name<span class="req">*</span></label>
          <input class="inp @error('name') err @enderror" id="name" name="name" value="{{ $v('name') }}" maxlength="150" required>
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
          <label for="gardener_show">Gardener name</label>
          <input class="inp" id="gardener_show" value="{{ $gardener?->label() }}" readonly placeholder="No Active contract" tabindex="-1">
          <span class="hint txt">From the client's current Active contract (Customer PO / AMC).</span>
        </div>
      </div>

      <div class="lbl">Locations, planters and plants</div>
      <div class="tablecard" style="overflow-x:auto">
        <table class="atable lines rtable">
          <thead><tr><th style="min-width:150px">Location 1</th><th style="min-width:150px">Location 2</th><th style="min-width:150px">Planter</th><th class="r" style="min-width:90px">Planter qty</th><th style="min-width:150px">Plant</th><th class="r" style="min-width:90px">Plant qty</th><th><span class="sr">Remove</span></th></tr></thead>
          <tbody id="amcLines">
          @foreach (array_values($lines) as $i => $l)
            <tr>
              <td data-label="Location 1">@if (! empty($l['id']))<input type="hidden" name="lines[{{ $i }}][id]" value="{{ $l['id'] }}">@endif<input class="inp @error("lines.$i.location_1") err @enderror" name="lines[{{ $i }}][location_1]" value="{{ $l['location_1'] ?? '' }}" maxlength="150" aria-label="Location 1, line {{ $i + 1 }}" placeholder="e.g. Tower A">@include('partials.ferr', ['name' => "lines.$i.location_1"])</td>
              <td data-label="Location 2"><input class="inp @error("lines.$i.location_2") err @enderror" name="lines[{{ $i }}][location_2]" value="{{ $l['location_2'] ?? '' }}" maxlength="150" aria-label="Location 2, line {{ $i + 1 }}" placeholder="e.g. Floor 5 - Lobby">@include('partials.ferr', ['name' => "lines.$i.location_2"])</td>
              <td data-label="Planter"><select class="inp @error("lines.$i.amc_planter_id") err @enderror" name="lines[{{ $i }}][amc_planter_id]" aria-label="Planter, line {{ $i + 1 }}"><option value="">None</option>@foreach ($planters as $o)<option value="{{ $o->id }}" @selected((string) ($l['amc_planter_id'] ?? '') === (string) $o->id)>{{ $o->name }}</option>@endforeach</select>@include('partials.ferr', ['name' => "lines.$i.amc_planter_id"])</td>
              <td data-label="Planter qty"><input class="inp num @error("lines.$i.planter_qty") err @enderror" name="lines[{{ $i }}][planter_qty]" value="{{ $l['planter_qty'] ?? '' }}" inputmode="numeric" aria-label="Planter qty, line {{ $i + 1 }}">@include('partials.ferr', ['name' => "lines.$i.planter_qty"])</td>
              <td data-label="Plant"><select class="inp @error("lines.$i.amc_plant_id") err @enderror" name="lines[{{ $i }}][amc_plant_id]" aria-label="Plant, line {{ $i + 1 }}"><option value="">None</option>@foreach ($plants as $o)<option value="{{ $o->id }}" @selected((string) ($l['amc_plant_id'] ?? '') === (string) $o->id)>{{ $o->label() }}</option>@endforeach</select>@include('partials.ferr', ['name' => "lines.$i.amc_plant_id"])</td>
              <td data-label="Plant qty"><input class="inp num @error("lines.$i.plant_qty") err @enderror" name="lines[{{ $i }}][plant_qty]" value="{{ $l['plant_qty'] ?? '' }}" inputmode="numeric" aria-label="Plant qty, line {{ $i + 1 }}">@include('partials.ferr', ['name' => "lines.$i.plant_qty"])</td>
              <td class="del">@if ($canSave)<button class="btn gh icon sm" type="button" data-del-line aria-label="Remove line {{ $i + 1 }}"><svg class="ico"><use href="#i-x"/></svg></button>@endif</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
      @include('partials.ferr', ['name' => 'lines'])
      @if ($canSave)<div><button class="btn add sm" type="button" data-add-line><svg class="ico"><use href="#i-plus"/></svg>Add line</button></div>@endif
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>Fields marked * are mandatory. The customer name cannot repeat. Each line is optional and can have two locations (for example "Tower A" and "Floor 5 - Lobby"), a planter and a plant with their quantities; an empty line is not saved. Planter and Plant come from their AMC masters.</span></div>
      </fieldset>
    </div>
  </div>
  @include('crm.partials.audit', ['m' => $client])
  <div class="formfoot">
    <span class="sp"></span>
    @if ($canSave)<button class="btn {{ $isNew ? 'add' : 'pri' }}" type="submit"><svg class="ico"><use href="#i-{{ $isNew ? 'plus' : 'check' }}"/></svg>{{ $isNew ? 'Add client' : 'Save changes' }}</button>@endif
  </div>
</form>

<template id="amcLineTpl">
  <tr>
    <td data-label="Location 1"><input class="inp" name="lines[__i__][location_1]" maxlength="150" aria-label="Location 1, line __n__" placeholder="e.g. Tower A"></td>
    <td data-label="Location 2"><input class="inp" name="lines[__i__][location_2]" maxlength="150" aria-label="Location 2, line __n__" placeholder="e.g. Floor 5 - Lobby"></td>
    <td data-label="Planter"><select class="inp" name="lines[__i__][amc_planter_id]" aria-label="Planter, line __n__"><option value="">None</option>@foreach ($planters as $o)@if ($o->is_active)<option value="{{ $o->id }}">{{ $o->name }}</option>@endif @endforeach</select></td>
    <td data-label="Planter qty"><input class="inp num" name="lines[__i__][planter_qty]" inputmode="numeric" aria-label="Planter qty, line __n__"></td>
    <td data-label="Plant"><select class="inp" name="lines[__i__][amc_plant_id]" aria-label="Plant, line __n__"><option value="">None</option>@foreach ($plants as $o)@if ($o->is_active)<option value="{{ $o->id }}">{{ $o->label() }}</option>@endif @endforeach</select></td>
    <td data-label="Plant qty"><input class="inp num" name="lines[__i__][plant_qty]" inputmode="numeric" aria-label="Plant qty, line __n__"></td>
    <td class="del"><button class="btn gh icon sm" type="button" data-del-line aria-label="Remove line __n__"><svg class="ico"><use href="#i-x"/></svg></button></td>
  </tr>
</template>
@include('crm.partials.history', ['history' => $history])
@endsection
