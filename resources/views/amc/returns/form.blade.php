{{-- AMC › Return & Replace: one entry. Customer fills the locations and, from its Active contract, the AMC type and gardener. --}}
@php
  $isNew = ! $r->exists;
  $u = auth()->user();
  $canSave = $isNew ? $u->can('amc-return.add') : $u->can('amc-return.edit');
  $df = config('nursery.date_format');
  $v = fn ($f) => old($f, $r->{$f});
  $d = fn ($f) => old($f, $r->{$f}?->format($df));
  $lineId = old('amc_client_line_id', $r->amc_client_line_id);
  $line = $client?->lines->firstWhere('id', (int) $lineId);
  // (22/11/2026) Location 1 is picked first; the entries of that Location 1 in the AMC Client Inventory show below it.
  $loc1 = old('location_1', $line?->location_1 ?? ($isNew ? null : $r->location_1));
  $loc1s = ($client?->lines ?? collect())->pluck('location_1')->filter(fn ($x) => $x !== null && $x !== '')->unique()->values();
  $plantId = $v('amc_plant_id');
  $plant = $plants->firstWhere('id', (int) $plantId);
@endphp
@extends('layouts.app', ['title' => $isNew ? 'Return & Replace' : $r->number()])

@section('content')
@include('amc.partials.assets')
<div class="pagehd">
  <h1>{{ $isNew ? 'Return & Replace' : $r->number().' · '.$client?->name }}</h1>
  @unless ($isNew)<span class="badge {{ $r->qualityBadge() }}">{{ $r->quality }}</span>@endunless
  <span class="sp"></span>
  @if (! $isNew && $u->can('amc-return.add'))<a class="btn add sm" href="{{ route('amc.returns.create') }}"><svg class="ico"><use href="#i-plus"/></svg>Add new</a>@endif
  <a class="btn gh sm" href="{{ route('amc.returns.index') }}"><svg class="ico"><use href="#i-list"/></svg>Return &amp; Replace list</a>
</div>

@include('amc.partials.errors')

<form method="POST" action="{{ $isNew ? route('amc.returns.store') : route('amc.returns.update', $r) }}" novalidate data-dirty-guard data-saving id="amcReturnForm"
  data-info-url="{{ route('amc.clients.info', ['client' => '__ID__']) }}">
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
        <div class="field">
          <label for="amc_client_line_id">Location 2 (entry)</label>
          <select class="inp @error('amc_client_line_id') err @enderror" id="amc_client_line_id" name="amc_client_line_id">
            <option value="">No entry</option>
            @foreach (($client?->lines ?? collect())->filter(fn ($l) => $loc1 !== null && $loc1 !== '' && (string) $l->location_1 === (string) $loc1) as $l)
              <option value="{{ $l->id }}" @selected((string) $lineId === (string) $l->id)>{{ $l->location_2 !== null && $l->location_2 !== '' ? $l->location_2 : 'Entry '.$l->line_no }}</option>
            @endforeach
          </select>
          <span class="hint txt">The entry the return is for (optional); its plant fills the Plant box when that is empty.</span>
          @include('partials.ferr', ['name' => 'amc_client_line_id'])
        </div>
        <div class="field" style="grid-column:1/-1" id="amcLocEntries" @if (! $loc1) hidden @endif>
          <span class="lbl">Entries under this Location</span>
          <div class="tablecard" style="overflow-x:auto">
            <table class="atable rtable">
              <thead><tr><th>Location 2</th><th>Planter</th><th class="r">Planter qty</th><th>Plant</th><th class="r">Plant qty</th></tr></thead>
              <tbody id="amcLocRows">
              @foreach ($client?->lines ?? [] as $l)
                <tr data-id="{{ $l->id }}" data-l1="{{ $l->location_1 }}" data-l2="{{ $l->location_2 }}" data-no="{{ $l->line_no }}" data-plant="{{ $l->amc_plant_id }}" @class(['sel' => (string) $lineId === (string) $l->id]) @if ((string) $l->location_1 !== (string) $loc1) hidden @endif>
                  <td data-label="Location 2">{{ $l->location_2 }}</td>
                  <td data-label="Planter">{{ $l->planter?->name }}</td>
                  <td data-label="Planter qty" class="r num">{{ $l->planter_qty }}</td>
                  <td data-label="Plant">{{ $l->plant?->label() }}</td>
                  <td data-label="Plant qty" class="r num">{{ $l->plant_qty }}</td>
                </tr>
              @endforeach
              </tbody>
            </table>
          </div>
          <span class="hint txt">The entry picked in Location 2 is marked.</span>
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
          <label for="amc_plant_id">Plant<span class="req">*</span></label>
          <select class="inp @error('amc_plant_id') err @enderror" id="amc_plant_id" name="amc_plant_id" aria-required="true">
            <option value="">Select</option>
            @foreach ($plants as $o)<option value="{{ $o->id }}" data-size="{{ $o->size }}" @selected((string) $plantId === (string) $o->id)>{{ $o->name }}</option>@endforeach
          </select>
          @include('partials.ferr', ['name' => 'amc_plant_id'])
        </div>
        <div class="field">
          <label for="plant_size_show">Plant size</label>
          <input class="inp" id="plant_size_show" value="{{ $plant?->size ?? ($isNew ? '' : $r->plant_size) }}" readonly tabindex="-1">
          <span class="hint txt">From the Plant master.</span>
        </div>
        <div class="field">
          <label for="qty">Qty<span class="req">*</span></label>
          <input class="inp num @error('qty') err @enderror" id="qty" name="qty" value="{{ $v('qty') }}" inputmode="numeric" required>
          @include('partials.ferr', ['name' => 'qty'])
        </div>
        <div class="field">
          <label for="rate">Rate (₹)</label>
          <input class="inp num @error('rate') err @enderror" id="rate" name="rate" value="{{ old('rate', $r->rate !== null ? rtrim(rtrim((string) $r->rate, '0'), '.') : '') }}" inputmode="decimal">
          @include('partials.ferr', ['name' => 'rate'])
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
        <div class="field">
          <label for="quality">Quality<span class="req">*</span></label>
          <select class="inp @error('quality') err @enderror" id="quality" name="quality" aria-required="true">
            <option value="">Select</option>
            @foreach (config('amc.qualities') as $o)<option @selected($v('quality') === $o)>{{ $o }}</option>@endforeach
          </select>
          @include('partials.ferr', ['name' => 'quality'])
        </div>
        <div class="field" style="grid-column:1/-1">
          <label for="return_remark">Return remark</label>
          <input class="inp @error('return_remark') err @enderror" id="return_remark" name="return_remark" value="{{ $v('return_remark') }}" maxlength="500">
          @include('partials.ferr', ['name' => 'return_remark'])
        </div>
      </div>
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>Fields marked * are mandatory. Dates as dd/mm/yyyy; the replace date cannot be before the request date. The locations and plant size are saved with the entry as they are now.</span></div>
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
@include('crm.partials.history', ['history' => $history])
@endsection
