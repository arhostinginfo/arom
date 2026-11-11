{{--
  Purchase › GRN entry (PURCHASE tab, prototype screen "grn"): entry bar, GRN no and date (given only when the amount
  matches), the quality bar, the short view (Party name, Invoice type, Invoice no, Invoice date, Invoice amount) with
  "Show all headings" for transporter, vehicle, e-way bill, customer, SO no and remarks, the item lines against open PO
  lines, and the amount match box. No Import, CSV or PDF (prototype page capability table).
--}}
@php
  $u = auth()->user();
  $df = config('nursery.date_format');
  $num = fn ($x, $d = 3) => $x === null || $x === '' ? '' : rtrim(rtrim(number_format((float) $x, $d, '.', ''), '0'), '.');
  $isNew = ! $grn->exists;
  $canSave = $isNew ? $u->can('grn.add') : ($u->can('grn.edit') && $grn->isEditable());
  $ps = app(\App\Services\PageSetupService::class)->form('grn');
  $v = fn ($f) => old($f, $grn->{$f});
  $vd = fn ($f) => old($f, $grn->{$f}?->format($df));
  $matched = $grn->exists && $grn->isMatched();
  $cancelled = $grn->status === \App\Models\Grn::CANCELLED;
  $decided = $grn->exists && $grn->quality_status !== \App\Models\Grn::QA_PENDING;
  $options = $entries->map(fn ($e) => ['id' => $e->id, 'url' => route('purchase.grns.show', $e), 'label' => ($e->grn_no ?? 'Pending match').' · '.($e->party?->name ?? '').' · '.$e->invoice_no, 'on' => $grn->exists && $e->id === $grn->id])->all();
  $buttons = [['href' => route('purchase.grns.list'), 'icon' => 'list', 'label' => 'GRN list', 'nav' => true]];
  $invType = $v('invoice_type') ?: 'Purchase';
  $partyId = $v('party_id');
  $customerId = $v('customer_id');
  $orderMap = collect($orders)->keyBy('id');
  $lineMap = collect($orders)->flatMap(fn ($o) => collect($o['lines'])->map(fn ($l) => $l + ['po' => $o['id']]))->keyBy('id');

  // Lines: after a failed save the rows as typed (posted as one JSON field), otherwise the saved ones.
  $typed = old('lines_json') !== null || old('lines') !== null;
  $oldRows = $typed ? (old('lines') ?: json_decode((string) old('lines_json', '[]'), true)) : null;
  $lines = $typed
    ? collect(is_array($oldRows) ? array_values(array_filter($oldRows, 'is_array')) : [])->map(fn ($l) => (object) [
        'id' => null, 'po' => $l['purchase_order_id'] ?? ($lineMap->get($l['purchase_order_line_id'] ?? 0)['po'] ?? ''), 'pol' => $l['purchase_order_line_id'] ?? '', 'item_id' => $l['item_id'] ?? '',
        'quantity' => $l['quantity'] ?? '', 'rate' => $l['rate'] ?? '', 'total' => null, 'accepted' => null, 'rejected' => null, 'label' => null, 'uom' => null,
      ])->all()
    : $grn->lines->map(fn ($l) => (object) [
        'id' => $l->id, 'po' => $l->purchase_order_id, 'pol' => $l->purchase_order_line_id, 'item_id' => $l->item_id, 'quantity' => $num($l->quantity),
        'rate' => $num($l->rate, 4), 'total' => $l->taxable_value, 'accepted' => $l->accepted_quantity, 'rejected' => $l->rejected_quantity,
        'label' => ($l->item?->item_no ?? '?').' · '.($l->description ?: $l->item?->description), 'uom' => $l->uom?->code, 'po_no' => $l->order?->po_no,
      ])->all();
  if (! $lines) { $lines = [(object) ['id' => null, 'po' => '', 'pol' => '', 'item_id' => '', 'quantity' => '', 'rate' => '', 'total' => null, 'accepted' => null, 'rejected' => null, 'label' => null, 'uom' => null]]; }
  $lineItems = \App\Models\Item::withTrashed()->with(['uom:id,code', 'taxStructure'])->whereIn('id', array_filter(array_map(fn ($l) => (int) $l->item_id, $lines)))->get()->keyBy('id');
  $itemAttr = fn ($it) => $it ? 'data-rate="'.e((string) (float) $it->price).'" data-uom="'.e($it->uom?->code).'" data-c="'.(float) $it->taxStructure?->cgst_rate.'" data-s="'.(float) $it->taxStructure?->sgst_rate.'" data-i="'.(float) $it->taxStructure?->igst_rate.'"' : '';
  $itemsWithTax = $items->isNotEmpty() ? \App\Models\Item::with(['uom:id,code', 'taxStructure'])->whereIn('id', $items->keys())->orderBy('item_no')->get() : collect();
  $itemOpts = function ($val) use ($itemsWithTax, $itemAttr, $lineItems) {
      $h = '<option value="">Select</option>';
      $seen = false;
      foreach ($itemsWithTax as $it) { $on = (string) $val === (string) $it->id; $seen = $seen || $on; $h .= '<option value="'.$it->id.'" '.$itemAttr($it).($on ? ' selected' : '').'>'.e($it->pickerLabel()).'</option>'; }
      if ($val && ! $seen && ($it = $lineItems->get((int) $val))) { $h .= '<option value="'.$it->id.'" '.$itemAttr($it).' selected>'.e($it->pickerLabel()).'</option>'; }
      return $h;
  };
  $poOpts = function ($val) use ($orders, $withoutPo, $invType) {
      $h = '<option value="">'.($withoutPo ? 'No PO' : 'Select').'</option>';
      foreach ($orders as $o) { $h .= '<option value="'.$o['id'].'"'.((string) $val === (string) $o['id'] ? ' selected' : '').'>'.e($o['no']).'</option>'; }
      return $h;
  };
  $polOpts = function ($po, $val, $l) use ($orderMap) {
      $h = '<option value="">'.($po ? 'Select' : 'Choose the PO no first').'</option>';
      $seen = false;
      foreach ($orderMap->get((int) $po)['lines'] ?? [] as $pl) {
          $on = (string) $val === (string) $pl['id']; $seen = $seen || $on;
          $h .= '<option value="'.$pl['id'].'" data-rate="'.$pl['rate'].'" data-pending="'.$pl['pending'].'" data-uom="'.e($pl['uom']).'" data-c="'.$pl['c'].'" data-s="'.$pl['s'].'" data-i="'.$pl['i'].'"'.($on ? ' selected' : '').'>'.e($pl['label']).'</option>';
      }
      if ($val && ! $seen) { $h .= '<option value="'.e($val).'" selected>'.e($l->label ?? 'Saved PO item').'</option>'; }
      return $h;
  };
  $headings = ['transporter_id', 'vehicle_no', 'eway_bill_no', 'eway_bill_date', 'customer_id', 'sales_order_id', 'remarks'];
  $allOpen = $errors->hasAny($headings) || collect($headings)->contains(fn ($f) => $ps->show($f) && $ps->req($f));
  $cur = $currency !== 'INR' ? $currency.' ' : '₹';
  $showQa = $grn->exists && $decided;
  $useQuality = $ps->show('quality_status');
  $canDelete = $grn->exists && ! $matched && ! $cancelled && $u->can('grn.delete');
  $canCancel = $matched && ! $cancelled && $u->can('grn.delete') && (! $decided || ! $grn->isPurchase() || $u->can('grn-quality.edit'));
  $canQuality = $matched && ! $cancelled && ! $decided && $u->can('grn-quality.edit');
@endphp
@extends('layouts.app', ['title' => $grn->grn_no ?? 'GRN entry', 'crumbs' => [['Purchase', route('purchase.grns.index')], ['GRN entry', null]]])

@section('content')
<style>
  /* Item lines fit the screen (no sideways scroll): a table on wide screens, one card per item below 1100 px. */
  #grnTable td.wide .qlock{margin-top:4px}
  #grnMatch .grnno .num{font-size:16px;font-weight:600}
  @media (max-width:1100px){
    .tablecard:has(> table.grncards){overflow:visible!important;border:0!important;background:none!important;box-shadow:none!important}
    table.grncards,table.grncards tbody{display:block;width:100%;min-width:0!important}
    table.grncards thead{display:none}
    table.grncards tbody tr{position:relative;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px 12px;padding:12px 14px;margin-bottom:10px;border:1px solid var(--border);border-radius:12px;background:var(--surface)}
    table.grncards td{display:block;padding:0!important;border:0!important;text-align:left!important;min-width:0;white-space:normal;height:auto!important;position:static!important}
    table.grncards td::before{content:attr(data-label);display:block;font-size:10.5px;letter-spacing:.06em;text-transform:uppercase;font-weight:600;color:var(--text-muted);margin-bottom:4px}
    table.grncards td.wide{grid-column:1/-1;padding-right:34px!important}
    table.grncards td.po{grid-column:span 2;padding-right:34px!important}
    table.grncards td.del{position:absolute!important;top:8px;right:8px;width:auto}
    table.grncards td.del::before{display:none}
    table.grncards .inp{width:100%;min-width:0!important}
  }
  @media (max-width:640px){table.grncards tbody tr{grid-template-columns:repeat(2,minmax(0,1fr))}}
  @media (max-width:380px){table.grncards tbody tr{grid-template-columns:minmax(0,1fr)}table.grncards td.po{grid-column:1/-1}}
</style>

<div class="pagehd">
  <h1>GRN entry</h1>
  <span class="sp"></span>
  <span class="cap muted">Unit: {{ $unit?->name ?? 'none' }}</span>
</div>

<div class="sectcard" id="grnCard">
  <div class="sh">
    <h2>Supplier invoice</h2>
    @if ($grn->exists)<span class="badge {{ $grn->badgeClass() }}">{{ $grn->stateLabel() }}</span>@endif
    <div class="autono"><span>GRN no <b>{{ $grn->grn_no ?? 'on match' }}</b></span><span>GRN date <b>{{ $grn->grn_date?->format($df) ?? 'on match' }}</b></span></div>
    <span class="sp"></span>
    <button class="btn sec sm" type="button" id="grnHeadings" aria-expanded="{{ $allOpen ? 'true' : 'false' }}" aria-controls="allHd">{{ $allOpen ? 'Hide headings' : 'Show all headings' }}</button>
  </div>
  <div class="sb">
    @include('crm.partials.entrybar', [
      'action' => route('purchase.grns.index'), 'lookupUrl' => route('purchase.grns.lookup'), 'q' => $q, 'options' => $options, 'current' => $grn->exists,
      'newLabel' => 'New GRN entry', 'placeholder' => 'GRN no, invoice no or supplier name',
      'addUrl' => $u->can('grn.add') ? route('purchase.grns.create') : null, 'buttons' => $buttons,
    ])

    @if ($errors->any())
      <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>{{ $errors->hasAny(['delete', 'status', 'form', 'quality']) ? 'This was not done.' : 'Check the fields marked in red. Nothing was saved.' }}</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
    @endif

    {{-- Quality bar: match state, quality approval and the next actions for it only. --}}
    <div class="apbar statusbar" id="grnQualityBar" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <span class="cap muted">Match</span>
      <span class="badge {{ $matched ? 'b-ok' : 'b-warn' }}">{{ $matched ? 'Matched' : 'Pending match' }}</span>
      @if ($useQuality || $decided)
        <span class="cap muted">Quality approval</span>
        <span class="badge {{ ['Approved' => 'b-ok', 'Rejected' => 'b-err'][$grn->quality_status] ?? 'b-warn' }}">{{ $grn->quality_status ?? 'Pending' }}</span>
      @endif
      @if ($decided && $grn->quality_at)
        <span class="cap muted">{{ $grn->qualityUser ? 'by '.$grn->qualityUser->name : '' }} on {{ $grn->quality_at->format($df) }}@if ($grn->quality_remarks) · {{ $grn->quality_remarks }}@endif</span>
      @endif
      @if ($cancelled)<span class="badge b-err">Cancelled</span>@if ($grn->status_reason)<span class="cap" style="color:var(--error-700)">Reason: {{ $grn->status_reason }}</span>@endif @endif
      <span class="sp" style="flex:1"></span>
      @if ($isNew || ! $matched)
        <span class="cap muted">Stock is usable only after Approved. Quality approval is given once the amount matches.</span>
      @elseif ($canQuality)
        <button class="btn sec sm" type="button" data-open="#grnQualityLayer" data-result="Rejected" style="color:var(--error-700)"><svg class="ico"><use href="#i-x"/></svg>Reject</button>
        <button class="btn pri sm" type="button" data-open="#grnQualityLayer" data-result="Approved"><svg class="ico"><use href="#i-check"/></svg>Approve quality</button>
      @elseif (! $decided && ! $cancelled)
        <span class="cap muted">Waiting for quality approval (GRN quality approval). Stock is usable only after Approved.</span>
      @elseif ($grn->stock_posted)
        <span class="cap muted">Accepted quantity received into stock at {{ $grn->unit?->name }}</span>
      @endif
    </div>
    @if ($grn->exists && ! $canSave && $u->can('grn.edit'))
      <div class="alert info"><svg class="ico"><use href="#i-lock"/></svg><span>{{ $grn->label() }} is {{ strtolower($grn->stateLabel()) }}, so it cannot be changed.@if (! $cancelled) Cancel it and enter the invoice again to correct it.@endif</span></div>
    @elseif ($grn->exists && ! $u->can('grn.edit'))
      <div class="alert info"><svg class="ico"><use href="#i-lock"/></svg><span>You can view this GRN. Your role cannot change GRN entries.</span></div>
    @endif

    <form method="POST" action="{{ $isNew ? route('purchase.grns.store') : route('purchase.grns.update', $grn) }}" novalidate id="grnForm" data-dirty-guard data-saving
          data-supply="{{ $supply }}" data-currency="{{ $currency }}" data-tolerance="{{ $tolerance }}" data-without-po="{{ $withoutPo ? '1' : '' }}"
          data-orders-url="{{ route('purchase.grns.orders', array_filter(['grn' => $grn->id])) }}" data-info-url="{{ route('purchase.grns.party-info', ['party' => '__ID__']) }}"
          data-so-url="{{ route('purchase.grns.sales-orders') }}" data-items-url="{{ route('purchase.grns.items') }}" data-supplier-items-url="{{ route('purchase.grns.supplier-items') }}" data-check="{{ $canSave ? '1' : '' }}">
      @csrf
      @unless ($isNew) @method('PUT') @endunless
      <script type="application/json" id="grnOrders">@json($orders)</script>
      <fieldset @disabled(! $canSave) style="border:0;padding:0;margin:0;min-width:0;display:grid;gap:14px">
      <div class="fgrid">
        <div class="field">
          <label for="party_id">Party name<span class="req">*</span></label>
          <div class="picker" data-picker="field" data-url="{{ route('purchase.grns.parties') }}" data-placeholder="Type a supplier name or GST no">
            <select class="inp @error('party_id') err @enderror" id="party_id" name="party_id" aria-required="true" @if ($matched) data-locked="1" @endif>
              <option value="">Select</option>
              @foreach ($suppliers as $id => $name)<option value="{{ $id }}" @selected((string) $partyId === (string) $id)>{{ $name }}</option>@endforeach
            </select>
          </div>
          <span class="hint txt">Customers are not listed: a customer party cannot be used on purchase.</span>
          @include('partials.ferr', ['name' => 'party_id'])
        </div>
        <div class="field">
          @if (! $matched)
            {{-- Invoice type as a drop-down instead of the segmented radio buttons; once matched it stays read-only text (02/11/2026). --}}
            <label for="invoice_type">Invoice type<span class="req">*</span></label>
            <select class="inp @error('invoice_type') err @enderror" id="invoice_type" name="invoice_type" aria-required="true">
              @foreach (\App\Models\Grn::TYPES as $t)<option value="{{ $t }}" @selected($invType === $t)>{{ $t }}</option>@endforeach
            </select>
            <span class="hint txt">Purchase = supplier invoice against PO lines. Credit / Debit = supplier credit or debit note (no PO, no stock).</span>
          @else
            <span class="lbl" id="gtLbl" style="font-size:13px;font-weight:500">Invoice type<span class="req">*</span></span>
            <input type="hidden" name="invoice_type" value="{{ $grn->invoice_type }}">
            <div class="kvline"><b>{{ $grn->invoice_type }}</b> · {{ \App\Models\Grn::TITLES[$grn->invoice_type] ?? '' }}</div>
          @endif
          @include('partials.ferr', ['name' => 'invoice_type'])
        </div>
        <div class="field">
          <label for="invoice_no">Invoice no<span class="req">*</span></label>
          <input class="inp @error('invoice_no') err @enderror" id="invoice_no" name="invoice_no" value="{{ $v('invoice_no') }}" maxlength="40" required>
          @include('partials.ferr', ['name' => 'invoice_no'])
        </div>
        <div class="field">
          <label for="invoice_date">Invoice date<span class="req">*</span></label>
          <input class="inp num @error('invoice_date') err @enderror" id="invoice_date" name="invoice_date" value="{{ $vd('invoice_date') }}" placeholder="dd/mm/yyyy" inputmode="numeric" required>
          @include('partials.ferr', ['name' => 'invoice_date'])
        </div>
        <div class="field">
          <label for="invoice_amount">Invoice amount incl. GST (<span data-t="cur">{{ trim($cur) }}</span>)<span class="req">*</span></label>
          <input class="inp num @error('invoice_amount') err @enderror" id="invoice_amount" name="invoice_amount" value="{{ old('invoice_amount', $grn->invoice_amount !== null ? $num($grn->invoice_amount, 2) : '') }}" inputmode="decimal" required>
          <span class="hint txt">As printed on the supplier invoice. The GRN no is generated when the lines total matches it.</span>
          @include('partials.ferr', ['name' => 'invoice_amount'])
        </div>
      </div>

      <div id="allHd" @unless ($allOpen) hidden @endunless>
        <div class="fgrid">
          @if ($ps->show('transporter_id'))
          <div class="field">
            <label for="transporter_id">Transporter{{ $ps->star('transporter_id') }}</label>
            <select class="inp @error('transporter_id') err @enderror" id="transporter_id" name="transporter_id" @required($ps->req('transporter_id'))>
              <option value="">Select</option>
              @foreach ($transporters as $t)<option value="{{ $t->id }}" @selected((string) $v('transporter_id') === (string) $t->id)>{{ $t->name }}</option>@endforeach
            </select>
            @if ($transporters->isEmpty())<span class="hint txt">No transporters yet. Add them in Admin › Masters › Transporter master.</span>@endif
            @include('partials.ferr', ['name' => 'transporter_id'])
          </div>
          @endif
          @if ($ps->show('vehicle_no'))
          <div class="field">
            <label for="vehicle_no">Vehicle no{{ $ps->star('vehicle_no') }}</label>
            <input class="inp @error('vehicle_no') err @enderror" id="vehicle_no" name="vehicle_no" value="{{ $v('vehicle_no') }}" maxlength="20" placeholder="MH 09 CD 4410" @required($ps->req('vehicle_no'))>
            @include('partials.ferr', ['name' => 'vehicle_no'])
          </div>
          @endif
          @if ($ps->show('eway_bill_no'))
          <div class="field">
            <label for="eway_bill_no">E-way bill no{{ $ps->star('eway_bill_no') }}</label>
            <input class="inp num @error('eway_bill_no') err @enderror" id="eway_bill_no" name="eway_bill_no" value="{{ $v('eway_bill_no') }}" maxlength="20" inputmode="numeric" @required($ps->req('eway_bill_no'))>
            @include('partials.ferr', ['name' => 'eway_bill_no'])
          </div>
          @endif
          @if ($ps->show('eway_bill_date'))
          <div class="field">
            <label for="eway_bill_date">E-way bill date{{ $ps->star('eway_bill_date') }}</label>
            <input class="inp num @error('eway_bill_date') err @enderror" id="eway_bill_date" name="eway_bill_date" value="{{ $vd('eway_bill_date') }}" placeholder="dd/mm/yyyy" inputmode="numeric" @required($ps->req('eway_bill_date'))>
            @include('partials.ferr', ['name' => 'eway_bill_date'])
          </div>
          @endif
          @if ($ps->show('customer_id'))
          <div class="field">
            <label for="customer_id">Customer name{{ $ps->star('customer_id') }}</label>
            <div class="picker" data-picker="field" data-url="{{ route('purchase.grns.customers') }}" data-placeholder="Type a customer name">
              <select class="inp @error('customer_id') err @enderror" id="customer_id" name="customer_id" @required($ps->req('customer_id'))>
                <option value="">Select</option>
                @foreach ($customers as $id => $name)<option value="{{ $id }}" @selected((string) $customerId === (string) $id)>{{ $name }}</option>@endforeach
              </select>
            </div>
            <span class="hint txt">Optional: material bought against a customer order.</span>
            @include('partials.ferr', ['name' => 'customer_id'])
          </div>
          @endif
          @if ($ps->show('sales_order_id'))
          <div class="field">
            <label for="sales_order_id">SO no{{ $ps->star('sales_order_id') }}</label>
            <select class="inp @error('sales_order_id') err @enderror" id="sales_order_id" name="sales_order_id" @required($ps->req('sales_order_id'))>
              <option value="">{{ $customerId ? 'Select' : 'Select a customer first' }}</option>
              @foreach ($salesOrders as $so)<option value="{{ $so->id }}" @selected((string) $v('sales_order_id') === (string) $so->id)>{{ $so->so_no }} · {{ $so->po_number }}</option>@endforeach
            </select>
            @include('partials.ferr', ['name' => 'sales_order_id'])
          </div>
          @endif
          @if ($ps->show('remarks'))
          <div class="field" style="grid-column:1/-1">
            <label for="remarks">Remarks{{ $ps->star('remarks') }}</label>
            <input class="inp @error('remarks') err @enderror" id="remarks" name="remarks" value="{{ $v('remarks') }}" maxlength="1000" @required($ps->req('remarks'))>
            @include('partials.ferr', ['name' => 'remarks'])
          </div>
          @endif
        </div>
      </div>
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>Short view by default; "Show all headings" shows transporter, vehicle no, e-way bill, customer name, SO no and remarks.</span></div>

      <div class="lbl">Items<span class="req">*</span></div>
      <div class="tablecard">
        <table class="atable lines grncards" id="grnTable">
          <thead><tr><th style="width:150px">PO no</th><th style="min-width:220px">Item no / Description</th><th class="r" style="width:120px">Invoice qty<span class="req">*</span></th><th style="width:70px">UOM</th><th class="r" style="width:110px">Rate</th><th class="r" style="width:120px">Amount</th>@if ($showQa)<th class="r" style="width:90px">Accepted</th><th class="r" style="width:90px">Rejected</th>@endif<th><span class="sr">Remove</span></th></tr></thead>
          <tbody id="grnLines">
            @foreach ($lines as $i => $l)
              @php($it = $l->item_id ? $lineItems->get((int) $l->item_id) : null)
              @php($pl = $l->pol && $canSave ? $lineMap->get((int) $l->pol) : null)
              @php($noPo = $invType !== 'Purchase' || (! $l->po && ! $l->pol && $withoutPo))
              <tr>
                <td data-label="PO no" class="po">
                  <select class="inp @error("lines.$i.purchase_order_line_id") err @enderror" name="lines[{{ $i }}][purchase_order_id]" data-po aria-label="PO no, line {{ $i + 1 }}" @if ($invType !== 'Purchase') disabled hidden @endif>{!! $poOpts($l->po) !!}</select>
                  @if ($invType !== 'Purchase')<span class="cap muted" data-nopo>No PO</span>@endif
                </td>
                <td data-label="Item" class="wide">
                  <select class="inp @error("lines.$i.purchase_order_line_id") err @enderror" name="lines[{{ $i }}][purchase_order_line_id]" data-pol aria-label="PO item, line {{ $i + 1 }}" @if ($noPo) disabled hidden @endif>{!! $polOpts($l->po, $l->pol, $l) !!}</select>
                  <select class="inp @error("lines.$i.item_id") err @enderror" name="lines[{{ $i }}][item_id]" data-item aria-label="Item, line {{ $i + 1 }}" @unless ($noPo) disabled hidden @endunless>{!! $itemOpts($l->item_id) !!}</select>
                  <span class="qlock" data-max @if (! $pl) hidden @endif><svg class="ico" style="width:11px"><use href="#i-lock"/></svg><span>{{ $pl ? 'Max '.\App\Support\Money::qty($pl['pending']).' (PO qty pending)' : '' }}</span></span>
                  @include('partials.ferr', ['name' => "lines.$i.purchase_order_line_id"])
                  @include('partials.ferr', ['name' => "lines.$i.item_id"])
                </td>
                <td data-label="Invoice qty"><input class="inp num @error("lines.$i.quantity") err @enderror" name="lines[{{ $i }}][quantity]" value="{{ $l->quantity }}" inputmode="decimal" data-calc aria-label="Invoice quantity, line {{ $i + 1 }}">@include('partials.ferr', ['name' => "lines.$i.quantity"])</td>
                <td data-label="UOM" data-uom-cell>{{ $l->uom ?? ($pl['uom'] ?? $it?->uom?->code) }}</td>
                <td data-label="Rate"><input class="inp num @error("lines.$i.rate") err @enderror" name="lines[{{ $i }}][rate]" value="{{ $l->rate }}" inputmode="decimal" data-calc placeholder="{{ $pl ? 'PO rate '.$num($pl['rate'], 4) : 'Item price' }}" aria-label="Rate, line {{ $i + 1 }}">@include('partials.ferr', ['name' => "lines.$i.rate"])</td>
                <td class="r num" data-label="Amount" data-amount>{{ $l->total !== null ? \App\Support\Money::indian($l->total, 2) : '' }}</td>
                @if ($showQa)
                  <td class="r num" data-label="Accepted">{{ \App\Support\Money::qty($l->accepted) }}</td>
                  <td class="r num" data-label="Rejected">{{ (float) $l->rejected ? \App\Support\Money::qty($l->rejected) : '' }}</td>
                @endif
                <td class="del">@if ($canSave)<button class="btn gh icon sm" type="button" data-del-line aria-label="Remove line {{ $i + 1 }}"><svg class="ico"><use href="#i-x"/></svg></button>@endif</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @include('partials.ferr', ['name' => 'lines'])
      @include('partials.ferr', ['name' => 'purchase_order_id'])
      @if ($canSave)<div><button class="btn add sm" type="button" data-add-line="#grnLines"><svg class="ico"><use href="#i-plus"/></svg>Add item</button></div>@endif
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>With a PO no, only that PO's items still pending are listed and the quantity cannot exceed the PO quantity pending. {{ $withoutPo ? 'Without a PO no, the supplier\'s purchase items are listed.' : 'Every Purchase invoice line is received against a PO.' }} Rate left blank is the PO rate (less the PO discount).</span></div>
      </fieldset>

      {{-- Amount match, GRN no and GRN date (PURCHASE A22). --}}
      <div class="sectcard" id="grnMatch" style="margin-top:14px">
        <div class="sh"><h2>Amount match, GRN no and GRN date</h2></div>
        <div class="sb" style="display:grid;gap:12px">
          <div aria-live="polite">
            <dl class="kv num" style="grid-template-columns:1fr auto;max-width:440px">
              <dt>Supplier invoice amount</dt><dd style="text-align:right"><span data-t="cur">{{ $cur }}</span><span data-t="invoice">{{ \App\Support\Money::indian($grn->invoice_amount ?? 0, 2) }}</span></dd>
              <dt>Taxable value (qty × rate)</dt><dd style="text-align:right" data-t="taxable">{{ \App\Support\Money::indian($grn->taxable_value ?? 0, 2) }}</dd>
              <dt>GST</dt><dd style="text-align:right" data-t="gst">{{ \App\Support\Money::indian((float) $grn->cgst_amount + (float) $grn->sgst_amount + (float) $grn->igst_amount, 2) }}</dd>
              <dt>Calculated (qty × rate + GST)</dt><dd style="text-align:right;font-weight:600" data-t="grand">{{ \App\Support\Money::indian($grn->grand_total ?? 0, 2) }}</dd>
              <dt>Difference</dt><dd style="text-align:right" data-t="diff">{{ \App\Support\Money::indian(round((float) $grn->invoice_amount - (float) $grn->grand_total, 2), 2) }}</dd>
            </dl>
            <span class="badge {{ $matched ? 'b-ok' : 'b-err' }}" data-t="badge">{{ $matched ? 'Amount matched' : ($grn->exists ? 'Amount not matched' : 'Enter the lines and the invoice amount') }}</span>
          </div>
          <div class="grnno {{ $matched ? 'ok' : '' }}" id="grnBox">
            <span class="ovl">GRN no · GRN date</span>
            @if ($matched)
              <span class="num" style="color:var(--success-700)">{{ $grn->grn_no }} · {{ $grn->grn_date?->format($df) }}</span>
            @elseif ($grn->exists && ! $cancelled)
              <span class="num" style="color:var(--warning-700)">Pending match · no GRN no yet</span>
            @else
              <span class="num" style="color:var(--text-disabled)">Generated when the amount matches</span>
            @endif
          </div>
          <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>{{ $ps->mandatory() ?: 'Mandatory: Party name, Invoice type, Invoice no, Invoice date and Invoice amount.' }} Match = supplier invoice amount equals Σ(qty × rate) + GST within ±{{ $cur === '₹' ? '₹' : $cur }}{{ $num($tolerance, 2) }}; GST is CGST + SGST from a supplier in your state, IGST from another state, none for an import supplier. If the amount is matched the GRN no and GRN date are generated and the PO received quantities go up; otherwise save as "Pending match" and correct it later. Stock is usable only after quality approval (Approved); only the accepted quantity comes into stock.</span></div>
        </div>
      </div>

      @include('crm.partials.audit', ['m' => $grn])
      <div class="formfoot">
        @if ($canDelete)
          <button class="btn sec" type="submit" form="grnDelForm" style="color:var(--error-700)"><svg class="ico"><use href="#i-x"/></svg>Delete GRN</button>
        @endif
        @if ($canCancel)
          <button class="btn sec" type="button" data-open="#grnCancelLayer" style="color:var(--error-700)"><svg class="ico"><use href="#i-x"/></svg>Cancel GRN</button>
        @endif
        <span class="sp"></span>
        @if ($canSave)
          @if ($matched)
            <button class="btn pri" type="submit" name="intent" value="match"><svg class="ico"><use href="#i-check"/></svg>Save changes</button>
          @else
            <button class="btn sec" type="submit" name="intent" value="pending" id="grnPending">Save as pending match</button>
            <button class="btn pri" type="submit" name="intent" value="match" id="grnSave"><svg class="ico"><use href="#i-check"/></svg>Save and generate GRN no</button>
          @endif
        @endif
      </div>
    </form>

    @if ($canDelete)
      <form id="grnDelForm" method="POST" action="{{ route('purchase.grns.destroy', $grn) }}" data-confirm="Delete this GRN for invoice {{ $grn->invoice_no }}? It has no GRN no yet and nothing was received.">@csrf @method('DELETE')</form>
    @endif

    @include('crm.partials.history', ['history' => $history])
  </div>
</div>

<template id="grnLineTpl">
  <tr>
    <td data-label="PO no" class="po"><select class="inp" name="lines[__i__][purchase_order_id]" data-po aria-label="PO no, line __n__">{!! $poOpts('') !!}</select></td>
    <td data-label="Item" class="wide">
      <select class="inp" name="lines[__i__][purchase_order_line_id]" data-pol aria-label="PO item, line __n__"><option value="">Choose the PO no first</option></select>
      <select class="inp" name="lines[__i__][item_id]" data-item aria-label="Item, line __n__" disabled hidden>{!! $itemOpts('') !!}</select>
      <span class="qlock" data-max hidden><svg class="ico" style="width:11px"><use href="#i-lock"/></svg><span></span></span>
    </td>
    <td data-label="Invoice qty"><input class="inp num" name="lines[__i__][quantity]" inputmode="decimal" data-calc aria-label="Invoice quantity, line __n__"></td>
    <td data-label="UOM" data-uom-cell></td>
    <td data-label="Rate"><input class="inp num" name="lines[__i__][rate]" inputmode="decimal" data-calc placeholder="PO rate" aria-label="Rate, line __n__"></td>
    <td class="r num" data-label="Amount" data-amount></td>
    @if ($showQa)<td class="r num" data-label="Accepted"></td><td class="r num" data-label="Rejected"></td>@endif
    <td class="del"><button class="btn gh icon sm" type="button" data-del-line aria-label="Remove line __n__"><svg class="ico"><use href="#i-x"/></svg></button></td>
  </tr>
</template>

@if ($canQuality)
  <div class="overlay" id="grnQualityLayer" hidden @if ($errors->hasAny(['remarks', 'result', 'quality']) && old('_action') === 'quality') data-autoopen @endif>
    <div class="scrim" data-x></div>
    <form class="modal" role="dialog" aria-modal="true" aria-labelledby="grnQaT" method="POST" action="{{ route('purchase.grns.quality', $grn) }}" data-saving>
      @csrf
      <input type="hidden" name="_action" value="quality">
      <div class="mh"><h3 id="grnQaT">Quality approval · {{ $grn->grn_no }}</h3></div>
      <div class="mb" style="display:grid;gap:12px">
        <div class="field">
          {{-- Result as a drop-down; the Approve / Reject buttons pre-select it (02/11/2026). --}}
          <label for="qa_result">Result<span class="req">*</span></label>
          <select class="inp @error('result') err @enderror" id="qa_result" name="result" aria-required="true">
            @foreach ([\App\Models\Grn::QA_APPROVED, \App\Models\Grn::QA_REJECTED] as $d)<option value="{{ $d }}" @selected(old('result', 'Approved') === $d)>{{ $d }}</option>@endforeach
          </select>
          @include('partials.ferr', ['name' => 'result'])
        </div>
        @if ($grn->isPurchase())
        <div data-qa-lines>
          <div class="lbl">Rejected quantity per item (Approved only; blank = all accepted)</div>
          <div class="tablecard">
            <table class="atable rtable">
              <thead><tr><th>Item</th><th class="r">Invoice qty</th><th class="r" style="width:130px">Rejected qty</th></tr></thead>
              <tbody>
              @foreach ($grn->lines as $gl)
                <tr>
                  <td data-label="Item" class="wide">{{ $gl->item?->item_no }} · {{ $gl->description }}</td>
                  <td data-label="Invoice qty" class="num r">{{ \App\Support\Money::qty($gl->quantity) }} {{ $gl->uom?->code }}</td>
                  <td data-label="Rejected qty"><input class="inp num @error('rejected.'.$gl->id) err @enderror" name="rejected[{{ $gl->id }}]" value="{{ old('rejected.'.$gl->id) }}" inputmode="decimal" aria-label="Rejected quantity of {{ $gl->item?->item_no }}">@include('partials.ferr', ['name' => 'rejected.'.$gl->id])</td>
                </tr>
              @endforeach
              </tbody>
            </table>
          </div>
        </div>
        @endif
        <div class="field">
          <label for="grn_qa_remarks">Remarks <span class="cap muted">(needed to reject)</span></label>
          <textarea class="inp @error('remarks') err @enderror" id="grn_qa_remarks" name="remarks" rows="3" maxlength="500" style="height:auto;padding-top:8px">{{ old('_action') === 'quality' ? old('remarks') : '' }}</textarea>
          @include('partials.ferr', ['name' => 'remarks'])
        </div>
        <div class="small muted">Approved: the accepted quantity comes into stock at {{ $grn->unit?->name }}; rejected quantities go back to the PO as pending. Rejected: nothing comes into stock and the whole quantity goes back to the PO. This cannot be changed later; cancel the GRN to undo it.</div>
      </div>
      <div class="mf" style="display:flex;gap:8px;justify-content:flex-end;padding:12px 20px 18px">
        <button class="btn gh" type="button" data-x>Back</button>
        <button class="btn pri" type="submit">Save quality approval</button>
      </div>
    </form>
  </div>
@endif

@if ($canCancel)
  <div class="overlay" id="grnCancelLayer" hidden @if ($errors->has('remarks') && old('_action') === 'cancel') data-autoopen @endif>
    <div class="scrim" data-x></div>
    <form class="modal" role="dialog" aria-modal="true" aria-labelledby="grnCanT" method="POST" action="{{ route('purchase.grns.cancel', $grn) }}" data-saving>
      @csrf
      <input type="hidden" name="_action" value="cancel">
      <div class="mh"><h3 id="grnCanT">Cancel {{ $grn->grn_no }}</h3></div>
      <div class="mb">
        <div class="field">
          <label for="grn_cancel_remarks">Reason<span class="req">*</span></label>
          <textarea class="inp @error('remarks') err @enderror" id="grn_cancel_remarks" name="remarks" rows="3" maxlength="500" style="height:auto;padding-top:8px" required></textarea>
          @include('partials.ferr', ['name' => 'remarks'])
        </div>
        <div class="small muted">The GRN is kept as Cancelled with its number and the reason. The PO quantities go back to pending{{ $grn->stock_posted ? ', and the stock received is taken out again (only while the unit still holds it)' : '' }}.</div>
      </div>
      <div class="mf" style="display:flex;gap:8px;justify-content:flex-end;padding:12px 20px 18px">
        <button class="btn gh" type="button" data-x>Keep GRN</button>
        <button class="btn pri" type="submit" style="background:var(--error-600)">Cancel GRN</button>
      </div>
    </form>
  </div>
@endif
@endsection

@push('scripts')
<script src="{{ asset('js/crm.js') }}?v={{ filemtime(public_path('js/crm.js')) }}"></script>
<script src="{{ asset('js/grn.js') }}?v={{ filemtime(public_path('js/grn.js')) }}"></script>
@endpush
