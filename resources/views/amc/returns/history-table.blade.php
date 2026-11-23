{{-- (24/11/2026) The returns of one entry (or of the rows without one), newest first. $rs: AmcReturnItem rows with their return. --}}
@php($showL2 ??= false)
@if (! count($rs))
  <p class="hint txt" style="margin:0">Not returned yet.</p>
@else
<div class="tablecard" style="overflow-x:auto">
  <table class="atable rtable">
    <thead><tr>
      <th>Return no</th><th>Request date</th><th>Replace date</th><th>Status</th>@if ($showL2)<th>Location 2</th>@endif
      <th>Planter</th><th class="r">Planter qty</th><th>Plant</th><th class="r">Plant qty</th><th class="r">Rate (₹)</th><th class="r">Amount (₹)</th><th>Quality</th><th>Gardener</th><th>Return remark</th>
    </tr></thead>
    <tbody>
    @foreach ($rs as $i)
      @php($r = $i->return)
      <tr @class(['mark' => $markReturn === $r->id])>
        <td data-label="Return no" class="num"><a href="{{ route('amc.returns.edit', $r->id) }}"><b>{{ $r->number() }}</b></a></td>
        <td data-label="Request date" class="num">{{ $r->request_date?->format($df) }}</td>
        <td data-label="Replace date" class="num">{{ $r->replace_date?->format($df) }}</td>
        <td data-label="Status"><span class="badge {{ $r->replace_date ? 'b-ok' : 'b-warn' }}">{{ $r->replace_date ? 'Replaced' : 'Open' }}</span></td>
        @if ($showL2)<td data-label="Location 2">{{ $i->location_2 }}</td>@endif
        <td data-label="Planter">{{ $i->planter?->name }}</td>
        <td data-label="Planter qty" class="r num">{{ $i->planter_qty }}</td>
        <td data-label="Plant">{{ $i->plant?->name }}{{ $i->plant_size ? ' ('.$i->plant_size.')' : '' }}</td>
        <td data-label="Plant qty" class="r num">{{ $i->plant_qty }}</td>
        <td data-label="Rate (₹)" class="r num">{{ $money($i->rate) }}</td>
        <td data-label="Amount (₹)" class="r num">{{ $money($i->amount()) }}</td>
        <td data-label="Quality">@if ($i->quality)<span class="badge {{ $i->qualityBadge() }}">{{ $i->quality }}</span>@endif</td>
        <td data-label="Gardener">{{ $r->gardener?->name }}</td>
        <td data-label="Return remark">{{ $r->return_remark }}</td>
      </tr>
    @endforeach
    </tbody>
    @php($sum = collect($rs)->sum(fn ($i) => $i->amount() ?? 0))
    <tfoot><tr><td colspan="{{ $showL2 ? 10 : 9 }}" class="r"><b>Total</b></td><td class="r num"><b>{{ $money($sum) }}</b></td><td colspan="3"></td></tr></tfoot>
  </table>
</div>
@endif
