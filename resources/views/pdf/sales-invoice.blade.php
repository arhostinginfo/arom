{{--
  Tax invoice / credit note / debit note PDF, standard GST layout on A4 portrait (02/11/2026, client sheet: "keep header
  fixed, footer fixed at bottom, GST & total amount on last page"; 11/11/2026: "header on every page, the totals fixed as
  the footer of the last page").
  - #pdf-header is position:fixed inside the top @page margin, so dompdf repeats it on every page.
  - Bill to / Ship to / Transport print once, on the first page under the header.
  - The item table's head repeats on each page (thead); a row never splits across pages.
  - The totals block (HSN summary, amount in words, bank, payment terms, declaration, totals, signatory) is on the last
    page only, at its bottom: an invisible copy (#pdf-totals-space) in the flow after the last item keeps its room, or
    moves to a new page when it does not fit, and the visible copy (#pdf-totals, absolute, bottom:0) is drawn at the
    bottom of that page.
  - The footer line ("computer generated", company name, address, contact) is not in this view: dompdf repeats a fixed
    element on every page, so SalesInvoiceController::invoicePdf() draws it in the bottom @page margin of the last page.
    It also draws the invoice no and "Page X of Y" at the bottom right of every page.
  Only JPG logos are drawn where PHP's GD extension is off ($logo is null otherwise, and the PDF prints without it).
--}}
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $inv->invoice_no }}</title>
@php
  $df = config('nursery.date_format');
  $m = fn ($v) => \App\Support\Money::indian($v, 2);
  $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
  $cur = $inv->currency && $inv->currency->code !== 'INR' ? $inv->currency->code : null;
  $intra = $inv->supply_type === \App\Support\Gst::INTRA;
  $inter = $inv->supply_type === \App\Support\Gst::INTER;
  $export = $inv->supply_type === \App\Support\Gst::EXPORT;
  $title = strtoupper($inv->title());
  $so = $inv->salesOrder;
  $poDate = $so ? (rescue(fn () => $so->customerPo?->po_date, null, false) ?? $so->start_date) : null;
  $companyState = \App\Support\Gst::stateLabel($company->stateCode());
  $partyState = \App\Support\Gst::stateLabel($inv->party?->stateCode());
  $placeOfSupply = $export ? 'Outside India (export)' : ($partyState ?? $companyState ?? '-');
  // HSN-wise summary of taxable value and GST, as a tax invoice shows it. (03/11/2026) One row per HSN and rate: parts of
  // one HSN can carry different GST rates (the rate comes from the part), so grouping by HSN alone printed a wrong rate.
  $hsn = $inv->lines->groupBy(fn ($l) => $l->hsn_code.'|'.(float) $l->cgst_rate.'|'.(float) $l->sgst_rate.'|'.(float) $l->igst_rate)->values()->map(fn ($g) => [
      'code' => $g->first()->hsn_code,
      'taxable' => $g->sum('taxable_value'), 'cgst' => $g->sum('cgst_amount'), 'sgst' => $g->sum('sgst_amount'), 'igst' => $g->sum('igst_amount'),
      'cr' => (float) $g->first()->cgst_rate, 'sr' => (float) $g->first()->sgst_rate, 'ir' => (float) $g->first()->igst_rate]);
  $lineTax = $inv->lines->sum(fn ($l) => (float) $l->cgst_amount + (float) $l->sgst_amount + (float) $l->igst_amount);
  $gstTotal = (float) $inv->cgst_amount + (float) $inv->sgst_amount + (float) $inv->igst_amount;
  $pfTax = $gstTotal - $lineTax;
  $roundOff = round((float) $inv->grand_total - (float) $inv->taxable_value - $gstTotal, 2);
  $bank = array_filter(['Bank' => $company->getAttribute('bank_name'), 'A/c no' => $company->getAttribute('bank_account_no'),
      'IFSC' => $company->getAttribute('bank_ifsc'), 'Branch' => $company->getAttribute('bank_branch')]);
  $contact = trim(($company->phone ? 'Ph '.$company->phone : '').($company->phone && $company->email ? ' · ' : '').($company->email ?? ''));
@endphp
<style>
  /* 192pt / 168pt: room for the header on every page, with a logo and a long company address (03/11/2026). The bottom
     margin keeps room for the footer line (last page) and the page number (every page). */
  @page{margin:192pt 28pt 50pt 28pt}
  body{font-family:"DejaVu Sans",sans-serif;font-size:7.6pt;color:#1f2937;line-height:1.3}
  table{width:100%;border-collapse:collapse}
  td,th{vertical-align:top}
  .r{text-align:right}
  .c{text-align:center}
  .muted{color:#6b7280}
  #pdf-header{position:fixed;top:-176pt;left:0;right:0;height:168pt}
  .co td{padding:0}
  .co .name{font-size:12.5pt;font-weight:bold;color:#065f46}
  .ttl{border:1px solid #065f46;text-align:center;padding:4pt 6pt}
  .ttl .t{font-size:12pt;font-weight:bold;color:#065f46;letter-spacing:.04em}
  .meta{margin-top:5pt;border:1px solid #9ca3af}
  .meta td{padding:2.5pt 5pt;border:1px solid #d1d5db;width:25%}
  .meta b{color:#374151}
  .cancel{color:#991b1b;font-weight:bold;font-size:10pt}
  .box{border:1px solid #9ca3af;padding:4pt 6pt}
  .box .h{display:block;color:#065f46;font-size:6.8pt;font-weight:bold;text-transform:uppercase;letter-spacing:.03em;margin-bottom:2pt}
  .items{margin-top:6pt}
  .items thead{display:table-header-group}
  .items tr{page-break-inside:avoid}
  .items th{background:#ecfdf5;color:#065f46;font-size:6.6pt;text-transform:uppercase;padding:3pt 2.5pt;border:1px solid #9ca3af}
  .items td{padding:2.5pt;border:1px solid #d1d5db}
  .sum th{background:#f3f4f6;font-size:6.6pt;padding:2.5pt;border:1px solid #9ca3af;text-transform:uppercase}
  .sum td{padding:2.5pt;border:1px solid #d1d5db}
  .tot td{padding:2.5pt 5pt;border-bottom:1px solid #e5e7eb}
  .tot tr.grand td{font-weight:bold;font-size:9.5pt;border-top:1.5px solid #065f46;border-bottom:1.5px solid #065f46;background:#ecfdf5}
  #pdf-totals-space{margin-top:8pt;page-break-inside:avoid;visibility:hidden}
  #pdf-totals{position:absolute;left:0;right:0;bottom:0}
  .sign{border:1px solid #9ca3af;padding:5pt 6pt;text-align:right;height:62pt}
</style>
</head>
<body>

{{-- ===== Header: every page (fixed in the top margin) ===== --}}
<div id="pdf-header">
  <table class="co">
    <tr>
      @if ($logo)
        <td style="width:{{ $logo['width'] + 8 }}pt"><img src="{{ $logo['src'] }}" width="{{ $logo['width'] }}" height="{{ $logo['height'] }}" alt=""></td>
      @endif
      <td>
        <div class="name">{{ $company->name }}</div>
        @if ($company->address)<div>{{ \Illuminate\Support\Str::limit(preg_replace('/\s*\n\s*/', ', ', $company->address), 120) }}</div>@endif
        <div>@if ($company->gstin)<b>GSTIN</b> {{ $company->gstin }}@endif @if ($companyState) &nbsp;<b>State</b> {{ $companyState }}@endif @if ($company->pan) &nbsp;<b>PAN</b> {{ $company->pan }}@endif</div>
        @if ($contact)<div class="muted">{{ $contact }}</div>@endif
      </td>
      <td style="width:150pt">
        <div class="ttl">
          <div class="t">{{ $title }}</div>
          <div class="muted">Original for recipient</div>
          @if ($inv->isCancelled())<div class="cancel">CANCELLED</div>@endif
        </div>
      </td>
    </tr>
  </table>
  <table class="meta">
    <tr>
      <td><b>{{ $inv->title() }} no</b><br>{{ $inv->invoice_no }}</td>
      <td><b>{{ $inv->title() }} date</b><br>{{ $inv->invoice_date?->format($df) }}</td>
      <td><b>PO no</b><br>{{ $so ? $so->po_number.' · '.$so->so_no : '-' }}</td>
      <td><b>PO date</b><br>{{ $poDate ? \Illuminate\Support\Carbon::parse($poDate)->format($df) : '-' }}</td>
    </tr>
    <tr>
      <td><b>Place of supply</b><br>{{ $placeOfSupply }}</td>
      <td><b>Reverse charge</b><br>No</td>
      <td><b>Supply</b><br>{{ $inv->supply_type }}{{ $cur ? ' · '.$cur : '' }}</td>
      <td><b>Unit</b><br>{{ $inv->unit?->name ?? '-' }}</td>
    </tr>
  </table>
</div>

{{-- ===== First page: parties and transport ===== --}}
<table>
  <tr>
    <td class="box" style="width:50%">
      <span class="h">Bill to (recipient)</span>
      <b>{{ $inv->party?->name }}</b><br>
      {!! nl2br(e($inv->party?->billing_address)) !!}
      <br><b>GSTIN</b> {{ $inv->party?->gstin ?: ($export ? 'Not applicable (export)' : 'Unregistered') }}
      @if ($partyState)<br><b>State</b> {{ $partyState }}@endif
    </td>
    <td class="box">
      <span class="h">Ship to (consignee)</span>
      <b>{{ $inv->party?->name }}</b><br>
      {!! nl2br(e($inv->shipping_address)) !!}
      @if ($inv->party?->gstin)<br><b>GSTIN</b> {{ $inv->party->gstin }}@endif
      @if ($partyState)<br><b>State</b> {{ $partyState }}@endif
    </td>
  </tr>
  <tr>
    <td class="box" colspan="2">
      <span class="h">Transport</span>
      <b>Mode</b> {{ $inv->mode_of_transport ?: '-' }} &nbsp; · &nbsp; <b>Transporter</b> {{ $inv->transporter?->name ?: '-' }} &nbsp; · &nbsp;
      <b>Vehicle no</b> {{ $inv->vehicle_no ?: '-' }} &nbsp; · &nbsp; <b>Distance</b> {{ $inv->distance_km !== null ? rtrim(rtrim((string) $inv->distance_km, '0'), '.').' km' : '-' }}
    </td>
  </tr>
</table>

{{-- ===== Items: the head repeats on each page; rows do not split ===== --}}
<table class="items" id="pdf-items">
  <thead>
    <tr>
      <th class="c" style="width:16pt">Sr</th>
      <th>Description of goods</th>
      <th style="width:36pt">HSN/SAC</th>
      <th class="r" style="width:40pt">Qty</th>
      <th style="width:24pt">UOM</th>
      <th class="r" style="width:40pt">Rate</th>
      <th class="r" style="width:52pt">Taxable value</th>
      @if ($intra)
        <th class="r" style="width:22pt">CGST %</th><th class="r" style="width:42pt">CGST</th>
        <th class="r" style="width:22pt">SGST %</th><th class="r" style="width:42pt">SGST</th>
      @elseif ($inter)
        <th class="r" style="width:22pt">IGST %</th><th class="r" style="width:46pt">IGST</th>
      @endif
      <th class="r" style="width:54pt">Total</th>
    </tr>
  </thead>
  <tbody>
  @foreach ($inv->lines as $l)
    <tr class="ln">
      <td class="c">{{ $l->line_no }}</td>
      <td><b>{{ $l->item?->item_no }}</b> {{ $l->item?->description }}</td>
      <td>{{ $l->hsn_code }}</td>
      <td class="r">{{ \App\Support\Money::indian($l->quantity, 3) }}</td>
      <td>{{ $l->item?->uom?->code }}</td>
      <td class="r">{{ rtrim(rtrim(number_format((float) $l->rate, 4, '.', ''), '0'), '.') }}</td>
      <td class="r">{{ $m($l->taxable_value) }}</td>
      @if ($intra)
        <td class="r">{{ $pct($l->cgst_rate) }}</td><td class="r">{{ $m($l->cgst_amount) }}</td>
        <td class="r">{{ $pct($l->sgst_rate) }}</td><td class="r">{{ $m($l->sgst_amount) }}</td>
      @elseif ($inter)
        <td class="r">{{ $pct($l->igst_rate) }}</td><td class="r">{{ $m($l->igst_amount) }}</td>
      @endif
      <td class="r">{{ $m($l->total) }}</td>
    </tr>
  @endforeach
  </tbody>
</table>

{{-- ===== Last page only, at its bottom: totals, GST, amount in words, HSN summary, bank, declaration, signatory ===== --}}
@php ob_start(); @endphp
  <table>
    <tr>
      <td style="width:58%;padding-right:8pt">
        @if (! $export)
          <table class="sum">
            <thead><tr>
              <th>HSN/SAC</th><th class="r">Taxable value</th>
              @if ($intra)<th class="r">CGST %</th><th class="r">CGST</th><th class="r">SGST %</th><th class="r">SGST</th>@else<th class="r">IGST %</th><th class="r">IGST</th>@endif
              <th class="r">Total GST</th>
            </tr></thead>
            <tbody>
            @foreach ($hsn as $h)
              <tr>
                <td>{{ $h['code'] ?: '-' }}</td><td class="r">{{ $m($h['taxable']) }}</td>
                @if ($intra)<td class="r">{{ $pct($h['cr']) }}</td><td class="r">{{ $m($h['cgst']) }}</td><td class="r">{{ $pct($h['sr']) }}</td><td class="r">{{ $m($h['sgst']) }}</td>
                @else<td class="r">{{ $pct($h['ir']) }}</td><td class="r">{{ $m($h['igst']) }}</td>@endif
                <td class="r">{{ $m($h['cgst'] + $h['sgst'] + $h['igst']) }}</td>
              </tr>
            @endforeach
            </tbody>
          </table>
          @if ($pfTax > 0.004)<div class="muted" style="margin-top:2pt">P&amp;F charges of {{ $m($inv->pf_charges) }} are taxed at the highest rate: GST {{ $m($pfTax) }}, in the totals.</div>@endif
        @else
          <div class="box">Supply meant for export under LUT without payment of integrated tax.</div>
        @endif
        <div class="box" style="margin-top:5pt"><b>Amount in words:</b> {{ $cur ? \App\Support\Money::words($inv->grand_total, $cur, 'cents') : \App\Support\Money::words($inv->grand_total) }}</div>
        @if ($bank)
          <div class="box" style="margin-top:5pt"><span class="h">Bank details</span>@foreach ($bank as $k => $v)<b>{{ $k }}</b> {{ $v }}@if (! $loop->last) &nbsp;·&nbsp; @endif @endforeach</div>
        @endif
        @if ($inv->party?->payment_terms)<div class="box" style="margin-top:5pt"><b>Payment terms:</b> {{ $inv->party->payment_terms }}</div>@endif
        <div style="margin-top:5pt;font-size:6.8pt" class="muted"><b>Declaration:</b> We declare that this {{ strtolower($inv->title()) }} shows the actual price of the goods described and that all particulars are true and correct.</div>
      </td>
      <td>
        <table class="tot">
          <tr><td>Items</td><td class="r">{{ $m($inv->lines->sum('taxable_value')) }}</td></tr>
          @if ((float) $inv->pf_charges)<tr><td>P&amp;F charges</td><td class="r">{{ $m($inv->pf_charges) }}</td></tr>@endif
          <tr><td><b>Taxable value</b></td><td class="r"><b>{{ $m($inv->taxable_value) }}</b></td></tr>
          @if ($intra)
            <tr><td>CGST</td><td class="r">{{ $m($inv->cgst_amount) }}</td></tr>
            <tr><td>SGST</td><td class="r">{{ $m($inv->sgst_amount) }}</td></tr>
          @elseif ($inter)
            <tr><td>IGST</td><td class="r">{{ $m($inv->igst_amount) }}</td></tr>
          @else
            <tr><td>GST</td><td class="r">Nil (LUT)</td></tr>
          @endif
          @if (! $export)<tr><td>Total GST</td><td class="r">{{ $m($gstTotal) }}</td></tr>@endif
          @if (abs($roundOff) > 0.004)<tr><td>Round off</td><td class="r">{{ $m($roundOff) }}</td></tr>@endif
          <tr class="grand"><td>Grand total</td><td class="r">{{ $cur ? $cur.' ' : '₹' }}{{ $m($inv->grand_total) }}</td></tr>
        </table>
        <div class="sign" style="margin-top:8pt">
          For <b>{{ $company->name }}</b>
          <div style="height:36pt"></div>
          Authorised signatory
        </div>
      </td>
    </tr>
  </table>
@php $totals = ob_get_clean(); @endphp
{{-- The same block twice: an invisible copy in the flow keeps its room after the last item (or moves to a new page when
     it does not fit), and the visible copy is placed at the bottom of that page. --}}
<div id="pdf-totals-space">{!! $totals !!}</div>
<div id="pdf-totals">{!! $totals !!}</div>

{{-- ===== Footer line: last page only, drawn by SalesInvoiceController::invoicePdf() ===== --}}
</body>
</html>
