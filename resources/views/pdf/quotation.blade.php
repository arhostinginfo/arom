<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $q->quotation_no }} Rev {{ $q->revision_no }}</title>
<style>
  @page{margin:28px 32px}
  body{font-family:"DejaVu Sans",sans-serif;font-size:10px;color:#1f2937}
  h1{font-size:18px;margin:0 0 2px;color:#065f46}
  .muted{color:#6b7280}
  table{width:100%;border-collapse:collapse}
  .head td{vertical-align:top;padding:2px 0}
  .kv td{padding:3px 8px 3px 0;vertical-align:top}
  .kv td.k{color:#6b7280;width:110px}
  .items{margin-top:14px}
  .items th{background:#ecfdf5;color:#065f46;text-align:left;font-size:9px;text-transform:uppercase;letter-spacing:.03em;padding:6px;border-bottom:1px solid #a7f3d0}
  .items td{padding:6px;border-bottom:1px solid #e5e7eb;vertical-align:top}
  .items tfoot td{border-bottom:0;padding:4px 6px}
  .items tfoot tr.grand td{font-weight:bold;font-size:11px;border-top:1px solid #a7f3d0}
  .r{text-align:right}
  .terms{margin-top:16px}
  .terms td{padding:3px 8px 3px 0;vertical-align:top}
  .terms td.k{color:#6b7280;width:150px}
  .foot{margin-top:18px;font-size:9px;color:#6b7280}
</style>
</head>
<body>
@php($df = config('nursery.date_format'))
@php($cur = $q->currency?->code)
{{-- Cost details are internal and are never printed (CRM changes). --}}
{{-- Company logo from Admin > Masters > Company at the top left (23/10/2026); embedded, so no public URL. No logo: as before. --}}
@php($logo = \App\Models\Company::current()->pdfLogo())
<table class="head">
  <tr>
    @if ($logo)<td style="width:{{ $logo['width'] }}px;padding-right:12px"><img src="{{ $logo['src'] }}" width="{{ $logo['width'] }}" height="{{ $logo['height'] }}" alt=""></td>@endif
    <td><h1>Quotation</h1><div class="muted">{{ $q->unit?->name }}</div></td>
    <td class="r">
      <div><b>Quotation no</b> {{ $q->quotation_no }} &nbsp; <b>Rev no</b> {{ $q->revision_no }}</div>
      <div><b>Date</b> {{ ($q->quotation_date ?? $q->enquiry_receipt_date)?->format($df) }}@if ($q->valid_until) &nbsp; <b>Valid until</b> {{ $q->valid_until->format($df) }}@endif</div>
      <div><b>Enquiry no</b> {{ $q->enquiry_no }}</div>
    </td>
  </tr>
</table>

<table class="kv" style="margin-top:12px">
  <tr><td class="k">Client</td><td><b>{{ $q->customerName() }}</b>
    @if ($q->prospect && ! $q->party && $q->prospect->address)<br>{{ $q->prospect->address }}@endif
  </td>
  <td class="k">Site</td><td>{{ $q->site }}</td></tr>
  <tr><td class="k">Contact</td><td>{{ $q->contact_name }}@if ($ph = $q->enquiry?->clientPhone()){{ $q->contact_name ? ', ' : '' }}{{ $ph }}@endif</td><td class="k">Prepared by</td><td>{{ $q->preparer?->name ?? $q->salesPerson?->name }}</td></tr>
  <tr><td class="k">Sales person</td><td>{{ $q->salesPerson?->name }}</td><td class="k">Currency</td><td>{{ $cur }}</td></tr>
</table>

<table class="items">
  <thead><tr><th>Item no</th><th>Item description</th><th class="r">Qty</th><th>Unit</th><th class="r">Rate</th><th class="r">Discount</th><th class="r">Tax</th><th class="r">Total ({{ $cur }})</th></tr></thead>
  <tbody>
  @foreach ($q->lines as $l)
    <tr>
      <td>{{ $l->item_no ?: $l->line_no }}</td>
      <td>{{ $l->description }}</td>
      <td class="r">{{ $l->qty !== null ? rtrim(rtrim(number_format((float) $l->qty, 3, '.', ''), '0'), '.') : '' }}</td>
      <td>{{ $l->uom }}</td>
      <td class="r">{{ $l->rate !== null ? \App\Support\Money::indian($l->rate, 2) : '' }}</td>
      <td class="r">{{ (float) $l->discount_percent ? rtrim(rtrim((string) $l->discount_percent, '0'), '.').'%' : '' }}</td>
      <td class="r">{{ (float) $l->tax_percent ? rtrim(rtrim((string) $l->tax_percent, '0'), '.').'%' : '' }}</td>
      <td class="r">{{ \App\Support\Money::indian($l->amount, 2) }}</td>
    </tr>
  @endforeach
  </tbody>
  <tfoot>
    <tr><td colspan="7" class="r">Items total</td><td class="r">{{ \App\Support\Money::indian($q->subtotal, 2) }}</td></tr>
    @if ((float) $q->discount_amount)<tr><td colspan="7" class="r">Discount</td><td class="r">- {{ \App\Support\Money::indian($q->discount_amount, 2) }}</td></tr>@endif
    <tr class="grand"><td colspan="7" class="r">Total ({{ $cur }})</td><td class="r">{{ \App\Support\Money::indian($q->grand_total, 2) }}</td></tr>
  </tfoot>
</table>

<table class="terms">
  @if ($q->payment_terms)<tr><td class="k">Payment terms</td><td>{{ $q->payment_terms }}</td></tr>@endif
  @if ($q->delivery_terms)<tr><td class="k">Delivery / service terms</td><td>{{ $q->delivery_terms }}</td></tr>@endif
  {{-- GST / tax (23/10/2026): tax of the items by Tax master row; an old quotation without one prints its saved text. --}}
  @php($taxSum = $q->lines->filter(fn ($l) => (float) $l->tax_percent)->groupBy(fn ($l) => $l->taxStructure?->name ?? 'Tax '.rtrim(rtrim((string) $l->tax_percent, '0'), '.').'%')->map(fn ($g) => $g->sum(fn ($l) => round((float) $l->qty * (float) $l->rate * (1 - (float) $l->discount_percent / 100) * (float) $l->tax_percent / 100, 2))))
  @if ($taxSum->isNotEmpty())<tr><td class="k">GST / tax</td><td>@foreach ($taxSum as $k => $amt){{ $k }}: {{ $cur }} {{ \App\Support\Money::indian($amt, 2) }}@if (! $loop->last) · @endif @endforeach{{ ' (included in item totals)' }}</td></tr>
  {{-- Lines have a tax row but no tax is charged (e.g. an export customer): say so instead of naming a rate (23/10/2026). --}}
  @elseif ($q->lines->contains(fn ($l) => $l->tax_structure_id))<tr><td class="k">GST / tax</td><td>No GST charged on these items</td></tr>
  @elseif ($q->taxStructure)<tr><td class="k">GST / tax</td><td>{{ $q->taxStructure->name }}</td></tr>
  @elseif ($q->tax_terms)<tr><td class="k">GST / tax</td><td>{{ $q->tax_terms }}</td></tr>@endif
  @if ($q->notes)<tr><td class="k">Notes</td><td>{!! nl2br(e($q->notes)) !!}</td></tr>@endif
</table>

<div class="foot">Printed on {{ now()->format(config('nursery.datetime_format')) }}. Revision {{ $q->revision_no }} of {{ $q->quotation_no }}.</div>
</body>
</html>
