{{-- AMC Client Inventory (22/11/2026): one entry under a Location 1. Params: $i (row key, or '__i__' in the template), $l (values),
     $loc1 (the block's Location 1, copied into a hidden field kept in step by amc.js), $planters, $plants, $canSave. --}}
@php
  $n = $i;
  // (24/11/2026) Rate from the Plant master (text, not typed) and Amount = Rate x Plant qty
  $pl = ! empty($l['amc_plant_id']) ? $plants->firstWhere('id', (int) $l['amc_plant_id']) : null;
  $rate = $pl?->rate;
  $amt = $rate !== null && preg_match('/^\d+$/', trim((string) ($l['plant_qty'] ?? ''))) ? (float) $rate * (int) $l['plant_qty'] : null;
@endphp
<tr data-inv-row>
  <td data-label="Location 2">@if (! empty($l['id']))<input type="hidden" name="lines[{{ $n }}][id]" value="{{ $l['id'] }}">@endif<input type="hidden" name="lines[{{ $n }}][location_1]" value="{{ $loc1 }}" data-loc1-copy><input class="inp @error("lines.$n.location_2") err @enderror" name="lines[{{ $n }}][location_2]" value="{{ $l['location_2'] ?? '' }}" maxlength="150" aria-label="Location 2" placeholder="e.g. Floor 5 - Lobby">@include('partials.ferr', ['name' => "lines.$n.location_2"])</td>
  <td data-label="Planter"><select class="inp @error("lines.$n.amc_planter_id") err @enderror" name="lines[{{ $n }}][amc_planter_id]" aria-label="Planter"><option value="">None</option>@foreach ($planters as $o)<option value="{{ $o->id }}" @selected((string) ($l['amc_planter_id'] ?? '') === (string) $o->id)>{{ $o->name }}</option>@endforeach</select>@include('partials.ferr', ['name' => "lines.$n.amc_planter_id"])</td>
  <td data-label="Planter qty"><input class="inp num @error("lines.$n.planter_qty") err @enderror" name="lines[{{ $n }}][planter_qty]" value="{{ $l['planter_qty'] ?? '' }}" inputmode="numeric" aria-label="Planter qty">@include('partials.ferr', ['name' => "lines.$n.planter_qty"])</td>
  <td data-label="Plant"><select class="inp @error("lines.$n.amc_plant_id") err @enderror" name="lines[{{ $n }}][amc_plant_id]" aria-label="Plant" data-inv-plant><option value="">None</option>@foreach ($plants as $o)<option value="{{ $o->id }}" data-rate="{{ $o->rate }}" @selected((string) ($l['amc_plant_id'] ?? '') === (string) $o->id)>{{ $o->label() }}</option>@endforeach</select>@include('partials.ferr', ['name' => "lines.$n.amc_plant_id"])</td>
  <td data-label="Plant qty"><input class="inp num @error("lines.$n.plant_qty") err @enderror" name="lines[{{ $n }}][plant_qty]" value="{{ $l['plant_qty'] ?? '' }}" inputmode="numeric" aria-label="Plant qty" data-inv-qty>@include('partials.ferr', ['name' => "lines.$n.plant_qty"])</td>
  <td data-label="Rate (₹)" class="r num"><span data-inv-rate>{{ $rate !== null ? \App\Support\Money::indian($rate, 2) : '' }}</span></td>
  <td data-label="Amount (₹)" class="r num"><span data-inv-amount>{{ $amt !== null ? \App\Support\Money::indian($amt, 2) : '' }}</span></td>
  <td class="del">@if ($canSave)<button class="btn gh icon sm" type="button" data-del-row aria-label="Remove this entry"><svg class="ico"><use href="#i-x"/></svg></button>@endif</td>
</tr>
