{{-- (23/11/2026) One row of a Return & Replace entry: $n (index or __N__), $it (the row's values), $entry (its entry of the
     AMC Client Inventory, or null), $planters / $plants (id => master row). Location 2, Planter, Plant, Plant size and
     Rate come from the entry and the masters and are shown as text; only the two quantities and the quality are chosen,
     each quantity at most the entry's own. --}}
@php
  $entry ??= null;
  $planterId = $entry ? $entry->amc_planter_id : ($it['amc_planter_id'] ?? null);
  $plantId = $entry ? $entry->amc_plant_id : ($it['amc_plant_id'] ?? null);
  $pl = $plantId ? ($plants[$plantId] ?? null) : null;
  $l2 = $entry ? $entry->location_2 : ($it['location_2'] ?? null);
  $maxP = $entry?->planter_qty;
  $maxQ = $entry?->plant_qty;
  // (24/11/2026) a saved row keeps its own rate and size; a new one takes the Plant master's
  $rate = ! empty($it['saved']) ? $it['saved_rate'] : $pl?->rate;
  $size = ! empty($it['saved']) ? $it['saved_size'] : $pl?->size;
  $amt = $rate !== null && is_numeric($it['plant_qty'] ?? null) ? (float) $rate * (int) $it['plant_qty'] : null;
@endphp
<tr data-item data-line="{{ $it['amc_client_line_id'] ?? '' }}" data-rate="{{ $rate }}">
  <td data-label="Location 2">
    <input type="hidden" name="items[{{ $n }}][amc_client_line_id]" value="{{ $it['amc_client_line_id'] ?? '' }}" data-f="line">
    @unless ($entry)<input type="hidden" name="items[{{ $n }}][location_2]" value="{{ $l2 }}">@endunless{{-- (24/11/2026, test fix) a row without an entry keeps its own --}}
    <span data-f="l2">{{ $l2 }}</span>
    @include('partials.ferr', ['name' => "items.$n.amc_client_line_id"])
  </td>
  <td data-label="Planter">
    @unless ($entry)<input type="hidden" name="items[{{ $n }}][amc_planter_id]" value="{{ $planterId }}">@endunless
    <span data-f="planter">{{ $planterId ? ($planters[$planterId]->name ?? '') : '' }}</span>
  </td>
  <td data-label="Planter qty">
    <input class="inp num @error("items.$n.planter_qty") err @enderror" name="items[{{ $n }}][planter_qty]" value="{{ $it['planter_qty'] ?? '' }}" inputmode="numeric" aria-label="Planter qty" data-f="pqty" style="max-width:90px" @if ($maxP !== null) data-max="{{ $maxP }}" @endif @if ($entry && ! $planterId) disabled @endif>
    <span class="hint txt" data-f="pmax">@if ($maxP !== null)of {{ $maxP }}@endif</span>
    @include('partials.ferr', ['name' => "items.$n.planter_qty"])
  </td>
  <td data-label="Plant">
    @unless ($entry)<input type="hidden" name="items[{{ $n }}][amc_plant_id]" value="{{ $plantId }}">@endunless
    <span data-f="plant">{{ $pl?->name }}</span>
  </td>
  <td data-label="Plant size"><span data-f="size">{{ $size }}</span></td>
  <td data-label="Plant qty">
    <input class="inp num @error("items.$n.plant_qty") err @enderror" name="items[{{ $n }}][plant_qty]" value="{{ $it['plant_qty'] ?? '' }}" inputmode="numeric" aria-label="Plant qty" data-f="qty" style="max-width:90px" @if ($maxQ !== null) data-max="{{ $maxQ }}" @endif @if ($entry && ! $plantId) disabled @endif>
    <span class="hint txt" data-f="qmax">@if ($maxQ !== null)of {{ $maxQ }}@endif</span>
    @include('partials.ferr', ['name' => "items.$n.plant_qty"])
  </td>
  <td data-label="Rate (₹)" class="r num"><span data-f="rate">{{ $rate !== null ? \App\Support\Money::indian($rate, 2) : '' }}</span></td>
  <td data-label="Amount (₹)" class="r num"><span data-f="amount">{{ $amt !== null ? \App\Support\Money::indian($amt, 2) : '' }}</span></td>
  <td data-label="Quality">
    <select class="inp @error("items.$n.quality") err @enderror" name="items[{{ $n }}][quality]" aria-label="Quality" aria-required="true" data-f="quality">
      <option value="">Select</option>
      @foreach (config('amc.qualities') as $o)<option @selected(($it['quality'] ?? null) === $o)>{{ $o }}</option>@endforeach
    </select>
    @include('partials.ferr', ['name' => "items.$n.quality"])
  </td>
  <td><button class="btn gh sm" type="button" data-del-item aria-label="Remove this row" title="Remove this row"><svg class="ico"><use href="#i-x"/></svg></button></td>
</tr>
