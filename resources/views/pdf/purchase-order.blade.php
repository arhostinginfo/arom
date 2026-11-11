<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $po->po_no }}</title>
<style>
  @page{margin:28px 32px}
  body{font-family:"DejaVu Sans",sans-serif;font-size:9.5px;color:#1f2937}
  h1{font-size:17px;margin:0 0 2px;color:#065f46}
  .muted{color:#6b7280}
  table{width:100%;border-collapse:collapse}
  .head td{vertical-align:top;padding:2px 0}
  .box{border:1px solid #d1d5db;padding:6px 8px;vertical-align:top}
  .box b.t{display:block;color:#065f46;font-size:8.5px;text-transform:uppercase;letter-spacing:.03em;margin-bottom:3px}
  .items{margin-top:10px}
  .items th{background:#ecfdf5;color:#065f46;text-align:left;font-size:8px;text-transform:uppercase;letter-spacing:.02em;padding:5px;border-bottom:1px solid #a7f3d0}
  .items td{padding:5px;border-bottom:1px solid #e5e7eb;vertical-align:top}
  .r{text-align:right}
  .tot td{padding:3px 5px}
  .tot tr.grand td{font-weight:bold;font-size:11px;border-top:1px solid #a7f3d0}
  .stamp{color:#991b1b;font-weight:bold;font-size:13px}
  .foot{margin-top:16px;font-size:8.5px;color:#6b7280}
</style>
</head>
<body>
@php
  $df = config('nursery.date_format');
  $m = fn ($v) => \App\Support\Money::indian($v, 2);
  $n = fn ($v, $d = 4) => rtrim(rtrim(number_format((float) $v, $d, '.', ''), '0'), '.');
  $cur = $po->currencyCode() !== 'INR' ? $po->currencyCode() : null;
  $intra = $po->supply_type === \App\Support\Gst::INTRA;
  $inter = $po->supply_type === \App\Support\Gst::INTER;
  $disc = $po->lines->contains(fn ($l) => (float) $l->discount_percent > 0);
  $draft = ! in_array($po->status, [\App\Models\PurchaseOrder::APPROVED, \App\Models\PurchaseOrder::PARTLY_RECEIVED, \App\Models\PurchaseOrder::RECEIVED, \App\Models\PurchaseOrder::CLOSED], true);
@endphp
{{-- Company logo from Admin > Masters > Company at the top left, as on the quotation PDF; embedded, so no public URL. --}}
@php($logo = $company->pdfLogo())
<table class="head">
  <tr>
    @if ($logo)<td style="width:{{ $logo['width'] }}px;padding-right:12px"><img src="{{ $logo['src'] }}" width="{{ $logo['width'] }}" height="{{ $logo['height'] }}" alt=""></td>@endif
    <td>
      <h1>Purchase order</h1>
      <div><b>{{ $company->name }}</b></div>
      @if ($company->address)<div class="muted">{{ $company->address }}</div>@endif
      <div class="muted">@if ($company->gstin)GSTIN {{ $company->gstin }}@endif @if ($company->pan) · PAN {{ $company->pan }}@endif</div>
      <div class="muted">{{ $company->phone }}@if ($company->phone && $company->email) · @endif{{ $company->email }}</div>
    </td>
    <td class="r">
      @if ($po->status === \App\Models\PurchaseOrder::CANCELLED)<div class="stamp">CANCELLED</div>@elseif ($draft)<div class="stamp">{{ strtoupper($po->status) }} · NOT APPROVED</div>@endif
      <div><b>PO no</b> {{ $po->po_no }}</div>
      <div><b>PO date</b> {{ $po->po_date?->format($df) }}</div>
      @if ($po->delivery_date)<div><b>Delivery date</b> {{ $po->delivery_date->format($df) }}</div>@endif
      <div><b>Supply</b> {{ $po->supply_type }}</div>
      <div class="muted">{{ $po->unit?->name }}</div>
    </td>
  </tr>
</table>

<table style="margin-top:8px">
  <tr>
    <td class="box" style="width:50%"><b class="t">Supplier</b><b>{{ $po->party?->name }}</b><br>{!! nl2br(e($po->party?->billing_address)) !!}@if ($po->party?->gstin)<br>GSTIN {{ $po->party->gstin }}@endif</td>
    <td class="box"><b class="t">Bill to</b>{!! nl2br(e($po->billing_address ?: $company->address)) !!}</td>
  </tr>
  <tr>
    <td class="box"><b class="t">Ship to</b>{!! nl2br(e($po->shipping_address ?: $company->address)) !!}</td>
    <td class="box"><b class="t">Terms</b>Payment terms: {{ $po->payment_terms ?: '-' }}<br>Currency: {{ $po->currencyCode() }}<br>Transporter: {{ $po->transporter?->name ?: '-' }}</td>
  </tr>
</table>

<table class="items">
  <thead><tr>
    <th>#</th><th>Item no / Description</th><th>HSN/SAC</th><th class="r">Qty</th><th>UOM</th><th class="r">Rate</th>@if ($disc)<th class="r">Disc %</th>@endif<th class="r">Taxable value</th>
    @if ($intra)<th class="r">CGST</th><th class="r">SGST</th>@elseif ($inter)<th class="r">IGST</th>@endif
    <th class="r">Total</th><th>Delivery</th>
  </tr></thead>
  <tbody>
  @foreach ($po->lines as $l)
    <tr>
      <td>{{ $l->line_no }}</td>
      <td><b>{{ $l->item?->item_no }}</b> {{ $l->description ?: $l->item?->description }}</td>
      <td>{{ $l->hsn_code }}</td>
      <td class="r">{{ \App\Support\Money::indian($l->quantity, 3) }}</td>
      <td>{{ $l->uom?->code }}</td>
      <td class="r">{{ $n($l->rate) }}</td>
      @if ($disc)<td class="r">{{ (float) $l->discount_percent ? $n($l->discount_percent, 2) : '' }}</td>@endif
      <td class="r">{{ $m($l->taxable_value) }}</td>
      @if ($intra)
        <td class="r">{{ $m($l->cgst_amount) }}<br><span class="muted">{{ $n($l->cgst_rate, 2) }}%</span></td>
        <td class="r">{{ $m($l->sgst_amount) }}<br><span class="muted">{{ $n($l->sgst_rate, 2) }}%</span></td>
      @elseif ($inter)
        <td class="r">{{ $m($l->igst_amount) }}<br><span class="muted">{{ $n($l->igst_rate, 2) }}%</span></td>
      @endif
      <td class="r">{{ $m($l->total) }}</td>
      <td>{{ $l->delivery_date?->format($df) }}</td>
    </tr>
  @endforeach
  </tbody>
</table>

<table style="margin-top:10px">
  <tr>
    <td style="width:55%;vertical-align:top">
      @if ($po->isImport())<div class="muted">Import: GST is not charged on this purchase order (customs duty and IGST are paid on the bill of entry).</div>@endif
      <div style="margin-top:8px"><b>Amount in words:</b> {{ $cur ? \App\Support\Money::words($po->grand_total, $cur, 'cents') : \App\Support\Money::words($po->grand_total) }}</div>
      @if ($po->remarks)<div style="margin-top:8px"><b>Remarks:</b> {{ $po->remarks }}</div>@endif
    </td>
    <td style="vertical-align:top">
      <table class="tot">
        <tr><td>Taxable value</td><td class="r">{{ $m($po->taxable_value) }}</td></tr>
        @if ($intra)
          <tr><td>CGST</td><td class="r">{{ $m($po->cgst_amount) }}</td></tr>
          <tr><td>SGST</td><td class="r">{{ $m($po->sgst_amount) }}</td></tr>
        @elseif ($inter)
          <tr><td>IGST</td><td class="r">{{ $m($po->igst_amount) }}</td></tr>
        @endif
        <tr class="grand"><td>Total</td><td class="r">{{ $cur ? $cur.' ' : '₹' }}{{ $m($po->grand_total) }}</td></tr>
      </table>
    </td>
  </tr>
</table>

<div class="foot">
  @if ($po->approved_at && ! $draft)Approved{{ $po->approver ? ' by '.$po->approver->name : '' }} on {{ $po->approved_at->format($df) }}<br>@endif
  Please quote the PO no on your invoice and delivery challan.
</div>
<div class="foot">For {{ $company->name }}<br><br><br>Authorised signatory</div>
</body>
</html>
