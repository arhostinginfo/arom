{{--
  Sales (SALES tab): tax invoice and sales order on one page (A24). The Tax invoice card comes first; the Sales order card
  is closed by default (D5) and opens from the menu's Sales Order item. Each card has its own entry picker, form and error
  bag ("invoice", "order"), so a mistake on one card never shows on the other.
--}}
@php
  $u = auth()->user();
  $df = config('nursery.date_format');
  $num = fn ($x, $d = 3) => $x === null || $x === '' ? '' : rtrim(rtrim(number_format((float) $x, $d, '.', ''), '0'), '.');
  $ie = $errors->getBag('invoice');
  $oe = $errors->getBag('order');

  /* ---- Tax invoice ---- */
  $inv = $invoice;
  $invNew = ! $inv->exists;
  $invLocked = $inv->exists && $inv->isCancelled();
  $invCanSave = ! $invLocked && ($invNew ? $u->can('tax-invoice.add') : $u->can('tax-invoice.edit'));
  $ips = app(\App\Services\PageSetupService::class)->form('tax-invoice');
  $iv = fn ($f) => $old('invoice', $f, $inv->{$f});
  $invType = $iv('invoice_type') ?: 'Sales';
  $invTitle = \App\Models\SalesInvoice::TITLES[$invType] ?? 'Tax invoice';
  $invOptions = $invEntries->map(fn ($e) => ['id' => $e->id, 'url' => route('sales.invoices.show', $e), 'label' => $e->invoice_no.' · '.($e->party?->name ?? ''), 'on' => $inv->exists && $e->id === $inv->id])->all();
  $invButtons = array_values(array_filter([
    ['href' => route('sales.invoices.list'), 'icon' => 'list', 'label' => 'Invoice list', 'nav' => true],
    ['href' => route('sales.invoices.export'), 'icon' => 'download', 'label' => 'Download CSV', 'kind' => 'csv'],
    $inv->exists ? ['href' => route('sales.invoices.pdf', $inv), 'icon' => 'file', 'label' => 'Generate PDF', 'kind' => 'pdf'] : null,
  ]));
  // Lines: after a failed save the rows as typed (they are posted as one JSON field, UX review M4), otherwise the saved ones.
  $typed = function (string $form) {
      if (old('_form') !== $form) { return null; }
      $l = old('lines') ?: json_decode((string) old('lines_json', '[]'), true);
      return array_values(array_filter(is_array($l) ? $l : [], 'is_array'));
  };
  // What a PO line still allows on this document: the balance (tax invoice), invoiced less credited (credit note) or
  // invoiced (debit note), plus what this invoice already holds on it when it is edited.
  $held = $inv->exists ? $inv->lines->groupBy('sales_order_line_id')->map(fn ($g) => (float) $g->sum('quantity')) : collect();
  $poMax = fn ($ol) => $ol ? round(match ($invType) { 'Sales' => $ol->balance(), 'Credit' => $ol->creditRoom(), default => (float) $ol->invoiced_quantity }
      + ($invType === 'Debit' ? 0 : ($held[$ol->id] ?? 0)), 3) : null;
  $invOld = $typed('invoice');
  $oldOrderLines = $invOld ? \App\Models\SalesOrderLine::with('item')->whereIn('id', array_filter(array_column($invOld, 'sales_order_line_id')))->get()->keyBy('id') : collect();
  $oldItems = $invOld ? \App\Models\Item::whereIn('id', array_filter(array_column($invOld, 'item_id')))->get()->keyBy('id') : collect();
  $invLines = $invOld !== null ? collect($invOld)->map(fn ($l) => (object) [
      'sales_order_line_id' => $l['sales_order_line_id'] ?? null, 'item_id' => $l['item_id'] ?? null, 'quantity' => $l['quantity'] ?? '', 'rate' => $l['rate'] ?? '',
      'label' => ! empty($l['sales_order_line_id']) ? $oldOrderLines->get($l['sales_order_line_id'])?->item?->pickerLabel() : (! empty($l['item_id']) ? $oldItems->get($l['item_id'])?->pickerLabel() : null),
      'max' => ! empty($l['sales_order_line_id']) ? $poMax($oldOrderLines->get($l['sales_order_line_id'])) : null,
    ])->all()
    : $inv->lines->map(fn ($l) => (object) [
      'sales_order_line_id' => $l->sales_order_line_id, 'item_id' => $l->item_id, 'quantity' => $num($l->quantity), 'rate' => $num($l->rate, 4),
      'label' => $l->item?->pickerLabel(),
      'max' => $poMax($l->orderLine),
    ])->all();
  // GST rates of the parts on screen, for the totals worked out while typing (UX review M1).
  $rateItems = \App\Models\Item::with(['taxStructure', 'currency:id,code'])->whereIn('id', array_filter(array_merge(array_column(array_map(fn ($l) => (array) $l, $invLines), 'item_id'))))->get()->keyBy('id');
  $gstAttrs = function ($itemId) use ($rateItems) {
      $i = $itemId ? $rateItems->get($itemId) : null;
      return $i ? collect(\App\Http\Controllers\Sales\SalesInvoiceController::itemData($i))->map(fn ($v, $k) => 'data-'.$k.'="'.e($v).'"')->implode(' ') : '';
  };
  $invPartyModel = $iv('party_id') ? \App\Models\Party::with('currency')->find($iv('party_id')) : null;
  $invSupply = $invPartyModel && $company->gstin ? \App\Support\Gst::supplyType($invPartyModel, $company) : ($inv->supply_type ?? '');
  $invCurrency = $invPartyModel?->currency?->code ?? 'INR';
  $invPo = $iv('sales_order_id');
  $invParty = $iv('party_id');
  $itemsUrl = fn ($party) => route('sales.items.lookup', array_filter(['party' => $party]));

  /* ---- Sales order ---- */
  $so = $order;
  $soNew = ! $so->exists;
  $soCanSave = $soNew ? $u->can('sales-order.add') : ($u->can('sales-order.edit') && $so->isEditableBy($u));
  $sps = app(\App\Services\PageSetupService::class)->form('sales-order');
  $sv = fn ($f) => $old('order', $f, $so->{$f});
  $sd = fn ($f) => $old('order', $f, $so->{$f}?->format($df));
  $soOptions = $soEntries->map(fn ($e) => ['id' => $e->id, 'url' => route('sales.orders.show', $e), 'label' => $e->so_no.' · '.($e->party?->name ?? '').' · PO '.$e->po_number, 'on' => $so->exists && $e->id === $so->id])->all();
  $soButtons = array_values(array_filter([
    ['href' => route('sales.orders.list'), 'icon' => 'list', 'label' => 'Sales order list', 'nav' => true],
    $u->can('sales-order.add') ? ['open' => '#soImport', 'icon' => 'upload', 'label' => 'Import', 'kind' => 'import'] : null,
    ['href' => route('sales.orders.export'), 'icon' => 'download', 'label' => 'Download CSV', 'kind' => 'csv'],
  ]));
  $soOld = $typed('order');
  $soOldLines = $soOld ? \App\Models\SalesOrderLine::whereIn('id', array_filter(array_column($soOld, 'id')))->get()->keyBy('id') : collect();
  $soOldItems = $soOld ? \App\Models\Item::whereIn('id', array_filter(array_column($soOld, 'item_id')))->get()->keyBy('id') : collect();
  $soLines = $soOld !== null ? collect($soOld)->map(fn ($l) => (object) [
      'id' => $l['id'] ?? null, 'item_id' => $l['item_id'] ?? null, 'quantity' => $l['quantity'] ?? '', 'rate' => $l['rate'] ?? '', 'delivery_date' => $l['delivery_date'] ?? '',
      'label' => ! empty($l['item_id']) ? $soOldItems->get($l['item_id'])?->pickerLabel() : null,
      'invoiced' => ! empty($l['id']) ? (float) $soOldLines->get($l['id'])?->invoiced_quantity : null,
    ])->all()
    : $so->lines->map(fn ($l) => (object) [
      'id' => $l->id, 'item_id' => $l->item_id, 'quantity' => $num($l->quantity), 'rate' => $num($l->rate, 4), 'delivery_date' => $l->delivery_date?->format($df),
      'label' => $l->item?->pickerLabel(), 'invoiced' => (float) $l->invoiced_quantity,
    ])->all();
  if (! $soLines) { $soLines = [(object) ['id' => null, 'item_id' => null, 'quantity' => '', 'rate' => '', 'delivery_date' => '', 'label' => null, 'invoiced' => null]]; }
  $soParty = $sv('party_id');
  $soCurrency = $soParty ? (\App\Models\Party::with('currency')->find($soParty)?->currency?->code ?? 'INR') : 'INR';
  $soRateItems = \App\Models\Item::with('currency:id,code')->whereIn('id', array_filter(array_column(array_map(fn ($l) => (array) $l, $soLines), 'item_id')))->get()->keyBy('id');
  $soOpen = $open === 'order' || $oe->any();
@endphp
@extends('layouts.app', ['title' => $open === 'order' ? 'Sales order' : 'Tax invoice', 'crumbs' => [['Sales', route('sales.invoices.index')], [$open === 'order' ? 'Sales order' : 'Tax invoice', null]]])

@section('content')
<div class="pagehd">
  <h1>Sales</h1>
  <span class="sp"></span>
  <span class="cap muted">Unit: {{ $unit?->name ?? 'none' }} · Tax invoice and sales order on one page</span>
  @can('sales-order.view')<a class="btn sec sm" href="#soCard" data-go-so><svg class="ico"><use href="#i-down"/></svg>Sales order</a>@endcan
</div>
@unless ($company->exists && $company->gstin)
  @can('masters.edit')
    <div class="alert info"><svg class="ico"><use href="#i-info"/></svg><span>Enter your company's GSTIN in <a href="{{ route('admin.company.edit') }}">Admin › Masters › Company</a>. It decides CGST + SGST or IGST and is printed on invoices.</span></div>
  @else
    {{-- 02/11/2026: without the GSTIN no invoice can be saved (so there is no PDF either); say so to every user. --}}
    @can('tax-invoice.add')
      <div class="alert info"><svg class="ico"><use href="#i-info"/></svg><span>Your company's GSTIN is not entered yet, so invoices cannot be saved. Ask an Admin to enter it in Admin › Masters › Company.</span></div>
    @endcan
  @endcan
@endunless

{{-- ================= Tax invoice ================= --}}
@can('tax-invoice.view')
<div class="sectcard" id="invCard">
  <div class="sh">
    <h2>{{ $invTitle }}</h2>
    @if ($inv->exists)<span class="badge {{ $invLocked ? 'b-err' : 'b-ok' }}">{{ $invLocked ? 'Cancelled' : $inv->supply_type }}</span>@endif
    <span class="sp"></span>
    <div class="autono"><span>{{ $invTitle }} no <b>{{ $inv->invoice_no ?? 'on save' }}</b></span></div>
  </div>
  <div class="sb">
    @include('crm.partials.entrybar', [
      'action' => route('sales.invoices.index'), 'lookupUrl' => route('sales.invoices.lookup'), 'q' => $q, 'options' => $invOptions, 'current' => $inv->exists,
      'newLabel' => 'New tax invoice', 'placeholder' => 'Invoice no or party name',
      'addUrl' => $u->can('tax-invoice.add') ? route('sales.invoices.create') : null, 'buttons' => $invButtons,
      'open' => null, {{-- 02/11/2026: the page's own $open ('invoice' or 'order') must not reach partials.iobtn, which then drew the PDF and CSV links as buttons that open nothing ("Unable to generate tax invoice pdf") --}}
    ])

    @if ($ie->any())
      <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>{{ $ie->has('form') ? 'Nothing was saved.' : 'Check the fields marked in red. Nothing was saved.' }}</b><ul>@foreach ($ie->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
    @endif
    @if ($invLocked)
      <div class="alert info"><svg class="ico"><use href="#i-lock"/></svg><span>{{ $inv->invoice_no }} is cancelled{{ $inv->cancel_reason ? ': '.$inv->cancel_reason : '' }}. Its number stays in the series; it is left out of reports.</span></div>
    @elseif (! $invCanSave && $inv->exists)
      <div class="alert info"><svg class="ico"><use href="#i-lock"/></svg><span>You can view this invoice. Your role cannot change invoices.</span></div>
    @endif

    <form method="POST" action="{{ $invNew ? route('sales.invoices.store') : route('sales.invoices.update', $inv) }}" novalidate id="invoiceForm" data-dirty-guard data-saving data-lines-form="#invLines" data-supply="{{ $invSupply }}" data-currency="{{ $invCurrency }}" data-type="{{ $invType }}">
      @csrf
      @unless ($invNew) @method('PUT') @endunless
      <fieldset @disabled(! $invCanSave) style="border:0;padding:0;margin:0;min-width:0;display:grid;gap:14px">
      <div class="fgrid">
        <div class="field">
          @if ($invNew)
            {{-- 02/11/2026: a dropdown instead of the Sales / Credit / Debit radio buttons. --}}
            <label for="invoice_type">Invoice type<span class="req">*</span></label>
            <select class="inp @error('invoice_type', 'invoice') err @enderror" id="invoice_type" name="invoice_type" aria-required="true">
              @foreach (\App\Models\SalesInvoice::TYPES as $t)<option value="{{ $t }}" @selected($invType === $t)>{{ $t }}</option>@endforeach
            </select>
            <span class="hint txt">Sales = tax invoice (INV), Credit = credit note (CN), Debit = debit note (DN).</span>
          @else
            <span class="lbl" id="itLbl" style="font-size:13px;font-weight:500">Invoice type<span class="req">*</span></span>
            <div class="kvline"><b>{{ $inv->invoice_type }}</b> · {{ $invTitle }}</div>
          @endif
          @include('partials.ferr', ['name' => 'invoice_type', 'bag' => 'invoice'])
        </div>
        <div class="field">
          <label for="inv_party_id">Party name<span class="req">*</span></label>
          <div class="picker" data-picker="field" data-url="{{ route('sales.parties.lookup') }}" data-placeholder="Type a customer name or GST no">
            <select class="inp @error('party_id', 'invoice') err @enderror" id="inv_party_id" name="party_id" aria-required="true"
              data-info-url="{{ route('sales.parties.info', ['party' => '__ID__']) }}">
              <option value="">Select</option>
              @foreach ($invCustomers as $id => $name)<option value="{{ $id }}" @selected((string) $invParty === (string) $id)>{{ $name }}</option>@endforeach
            </select>
          </div>
          @include('partials.ferr', ['name' => 'party_id', 'bag' => 'invoice'])
        </div>
        <div class="field">
          <label for="invoice_date">Invoice date<span class="req">*</span></label>
          {{-- 02/11/2026: filled in automatically (today for a new invoice); only an Admin can change it (a past date in the year). --}}
          @php($invDateAdmin = $u->isAdmin())
          @php($invYear = $inv->exists ? ($inv->financialYear ?? \App\Support\WorkContext::financialYear()) : \App\Support\WorkContext::financialYear())
          <input class="inp num @if (! $invDateAdmin) ro @endif @error('invoice_date', 'invoice') err @enderror" id="invoice_date" name="invoice_date"
            value="{{ $invDateAdmin ? $old('invoice', 'invoice_date', ($inv->invoice_date ?? today())->format($df)) : ($inv->invoice_date ?? today())->format($df) }}"
            placeholder="dd/mm/yyyy" inputmode="numeric" required aria-describedby="invoiceDateHint" @if (! $invDateAdmin) readonly @endif>
          <span class="hint txt" id="invoiceDateHint">@if ($invDateAdmin)Today by default. As Admin you can enter a past date in {{ $invYear->label() }}, not after today.@elseif ($invNew)Today's date, set automatically. Only an Admin can change it.@else As saved. Only an Admin can change it.@endif</span>
          @include('partials.ferr', ['name' => 'invoice_date', 'bag' => 'invoice'])
        </div>
        @if ($ips->show('sales_order_id'))
        <div class="field">
          <label for="inv_sales_order_id">PO no{{ $ips->star('sales_order_id') }}</label>
          <select class="inp @error('sales_order_id', 'invoice') err @enderror" id="inv_sales_order_id" name="sales_order_id" data-lines-url="{{ route('sales.orders.po-lines', ['order' => '__ID__']) }}" @if ($ips->req('sales_order_id')) aria-required="true" @endif>
            <option value="">{{ $invParty && $poOptions->isEmpty() ? 'No approved sales order for this party.' : 'No PO selected' }}</option>
            @foreach ($poOptions as $id => $label)<option value="{{ $id }}" @selected((string) $invPo === (string) $id)>{{ $label }}</option>@endforeach
          </select>
          <span class="hint txt">The party's approved sales orders. With a PO, only its parts are billed, at its rate, up to its balance.</span>
          {{-- 02/11/2026: say why the list is empty; the script shows or hides it when the party changes. --}}
          <style>#invPoNone[hidden]{display:none!important}</style>
          <span class="hint txt" id="invPoNone" @if (! $invParty || $poOptions->isNotEmpty()) hidden @endif>No approved sales order for this party. Make one in Sales › Sales order, or from a customer PO (Create Sales Order).</span>
          @include('partials.ferr', ['name' => 'sales_order_id', 'bag' => 'invoice'])
        </div>
        @endif
        <div class="field" style="grid-column:1/-1">
          <label for="shipping_address">Shipping address<span class="req">*</span></label>
          <textarea class="inp @error('shipping_address', 'invoice') err @enderror" id="shipping_address" name="shipping_address" rows="2" maxlength="1000" style="height:auto;padding-top:8px" required>{{ $iv('shipping_address') }}</textarea>
          <span class="hint txt">Filled in from the party's shipping address; change it for another delivery place.</span>
          @include('partials.ferr', ['name' => 'shipping_address', 'bag' => 'invoice'])
        </div>
        @if ($ips->show('mode_of_transport'))
        <div class="field">
          <label for="mode_of_transport">Mode of transport{{ $ips->star('mode_of_transport') }}</label>
          <select class="inp @error('mode_of_transport', 'invoice') err @enderror" id="mode_of_transport" name="mode_of_transport" @if ($ips->req('mode_of_transport')) aria-required="true" @endif>
            <option value="">Select</option>
            @foreach (\App\Models\SalesInvoice::MODES as $m)<option @selected($iv('mode_of_transport') === $m)>{{ $m }}</option>@endforeach
          </select>
          @include('partials.ferr', ['name' => 'mode_of_transport', 'bag' => 'invoice'])
        </div>
        @endif
        @if ($ips->show('transporter_id'))
        <div class="field">
          <label for="transporter_id">Transporter{{ $ips->star('transporter_id') }}</label>
          <select class="inp @error('transporter_id', 'invoice') err @enderror" id="transporter_id" name="transporter_id" @if ($ips->req('transporter_id')) aria-required="true" @endif>
            <option value="">Select</option>
            @foreach ($transporters as $t)<option value="{{ $t->id }}" @selected((string) $iv('transporter_id') === (string) $t->id)>{{ $t->name }}</option>@endforeach
          </select>
          @if ($transporters->isEmpty())<span class="hint txt">No transporters yet. Add them in Admin › Masters › Transporter master.</span>@endif
          @include('partials.ferr', ['name' => 'transporter_id', 'bag' => 'invoice'])
        </div>
        @endif
        @if ($ips->show('vehicle_no'))
        <div class="field">
          <label for="vehicle_no">Vehicle no{{ $ips->star('vehicle_no') }}</label>
          <input class="inp @error('vehicle_no', 'invoice') err @enderror" id="vehicle_no" name="vehicle_no" value="{{ $iv('vehicle_no') }}" maxlength="20" placeholder="e.g. MH 14 AB 1234" style="text-transform:uppercase" @required($ips->req('vehicle_no'))>
          @include('partials.ferr', ['name' => 'vehicle_no', 'bag' => 'invoice'])
        </div>
        @endif
        @if ($ips->show('distance_km'))
        <div class="field">
          <label for="distance_km">Distance (km){{ $ips->star('distance_km') }}</label>
          <input class="inp num @error('distance_km', 'invoice') err @enderror" id="distance_km" name="distance_km" value="{{ $old('invoice', 'distance_km', $num($inv->distance_km, 1)) }}" inputmode="decimal" @required($ips->req('distance_km'))>
          @include('partials.ferr', ['name' => 'distance_km', 'bag' => 'invoice'])
        </div>
        @endif
        @if ($ips->show('pf_charges'))
        <div class="field">
          <label for="pf_charges">P&amp;F charges{{ $ips->star('pf_charges') }}</label>
          <input class="inp num @error('pf_charges', 'invoice') err @enderror" id="pf_charges" name="pf_charges" value="{{ $old('invoice', 'pf_charges', (float) $inv->pf_charges ? $num($inv->pf_charges, 2) : '') }}" inputmode="decimal" @required($ips->req('pf_charges'))>
          <span class="hint txt">Packing &amp; forwarding: taxed at the highest GST rate of the parts.</span>
          @include('partials.ferr', ['name' => 'pf_charges', 'bag' => 'invoice'])
        </div>
        @endif
      </div>

      <div class="lbl">Items<span class="req">*</span></div>
      <div class="tablecard" style="overflow-x:auto">
        <table class="atable lines rtable">
          <thead><tr><th style="min-width:260px">Item no / Description</th><th class="r" style="min-width:120px">Quantity<span class="req">*</span></th><th class="r" style="min-width:110px">Rate</th><th class="r" style="min-width:120px">Amount</th><th style="min-width:130px">PO balance</th><th><span class="sr">Remove</span></th></tr></thead>
          <tbody id="invLines" data-mode="{{ $invPo ? 'po' : 'free' }}" data-items-url="{{ $itemsUrl($invParty) }}">
            @foreach ($invLines ?: [(object) ['sales_order_line_id' => null, 'item_id' => null, 'quantity' => '', 'rate' => '', 'label' => null, 'max' => null]] as $i => $l)
              <tr {!! $gstAttrs($l->item_id) !!} @if ($l->max !== null) data-max="{{ $l->max }}" @endif>
                <td data-label="Item" class="wide">
                  @if ($l->sales_order_line_id)
                    <input type="hidden" name="lines[{{ $i }}][sales_order_line_id]" value="{{ $l->sales_order_line_id }}">
                    <input type="hidden" name="lines[{{ $i }}][item_id]" value="{{ $l->item_id }}">
                    <span>{{ $l->label }}</span>
                  @else
                    <div class="picker" data-picker="field" data-url="{{ $itemsUrl($invParty) }}" data-placeholder="Type an item no or description">
                      <select class="inp @error("lines.$i.item_id", 'invoice') err @enderror" id="inv_item_{{ $i }}" name="lines[{{ $i }}][item_id]" aria-label="Item {{ $i + 1 }}">
                        <option value="">Select</option>
                        @foreach ($invItems as $id => $label)<option value="{{ $id }}" @selected((string) $l->item_id === (string) $id)>{{ $label }}</option>@endforeach
                        @if ($l->item_id && ! $invItems->has($l->item_id))<option value="{{ $l->item_id }}" selected>{{ $l->label }}</option>@endif
                      </select>
                    </div>
                  @endif
                  @include('partials.ferr', ['name' => "lines.$i.item_id", 'bag' => 'invoice'])
                </td>
                <td data-label="Quantity"><input class="inp num @error("lines.$i.quantity", 'invoice') err @enderror" name="lines[{{ $i }}][quantity]" value="{{ $l->quantity }}" inputmode="decimal" data-calc aria-label="Quantity, item {{ $i + 1 }}" @if ($l->max !== null) placeholder="Max {{ \App\Support\Money::qty($l->max) }}" @endif>@include('partials.ferr', ['name' => "lines.$i.quantity", 'bag' => 'invoice'])</td>
                <td data-label="Rate">@if ($l->sales_order_line_id)<input class="inp num ro" name="lines[{{ $i }}][rate]" value="{{ $l->rate }}" inputmode="decimal" data-calc readonly aria-label="Rate from the sales order, item {{ $i + 1 }}"><span class="hint txt">From the sales order</span>@else<input class="inp num @error("lines.$i.rate", 'invoice') err @enderror" name="lines[{{ $i }}][rate]" value="{{ $l->rate }}" inputmode="decimal" data-calc aria-label="Rate, item {{ $i + 1 }}" placeholder="Item price">@include('partials.ferr', ['name' => "lines.$i.rate", 'bag' => 'invoice'])@endif</td>
                <td class="r num" data-label="Amount" data-amount></td>
                <td data-label="PO balance" class="cap muted">@if ($l->max !== null)<svg class="ico" style="width:12px" aria-hidden="true"><use href="#i-lock"/></svg> Max {{ \App\Support\Money::qty($l->max) }}@endif</td>
                <td class="del">@if ($invCanSave && ! $l->sales_order_line_id)<button class="btn gh icon sm" type="button" data-del-line aria-label="Remove item {{ $i + 1 }}"><svg class="ico"><use href="#i-x"/></svg></button>@endif</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @include('partials.ferr', ['name' => 'lines', 'bag' => 'invoice'])
      @if ($invCanSave)<div style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn add sm" type="button" data-add-line="#invLines" id="invAdd" @if ($invPo) hidden @endif><svg class="ico"><use href="#i-plus"/></svg>Add item</button><button class="btn gh sm" type="button" id="invFill" hidden><svg class="ico"><use href="#i-check"/></svg>Fill every PO balance</button></div>@endif

      {{-- Totals while typing (UX review M1); the server works them out again on save. --}}
      @php($cur = $invCurrency !== 'INR' ? $invCurrency.' ' : '₹')
      <div class="card" id="invTotals" style="background:var(--surface-sunken);max-width:440px;width:100%;margin-left:auto" aria-live="polite">
        <div class="cb2" style="padding:12px 16px">
          <dl class="kv num" style="grid-template-columns:1fr auto">
            <dt>Taxable value <span data-pf-note @if (! (float) $inv->pf_charges) hidden @endif>(with P&amp;F)</span></dt><dd style="text-align:right" data-t="taxable">{{ \App\Support\Money::indian($inv->taxable_value ?? 0, 2) }}</dd>
            <dt data-row="intra">CGST</dt><dd style="text-align:right" data-row="intra" data-t="cgst">{{ \App\Support\Money::indian($inv->cgst_amount ?? 0, 2) }}</dd>
            <dt data-row="intra">SGST</dt><dd style="text-align:right" data-row="intra" data-t="sgst">{{ \App\Support\Money::indian($inv->sgst_amount ?? 0, 2) }}</dd>
            <dt data-row="inter">IGST</dt><dd style="text-align:right" data-row="inter" data-t="igst">{{ \App\Support\Money::indian($inv->igst_amount ?? 0, 2) }}</dd>
            <dt data-row="export">GST</dt><dd style="text-align:right" data-row="export">Nil (export under LUT)</dd>
            <dt style="font-weight:600;color:var(--text-primary)">Grand total</dt><dd style="text-align:right;font-size:16px;font-weight:600"><span data-t="cur">{{ $cur }}</span><span data-t="grand">{{ \App\Support\Money::indian($inv->grand_total ?? 0, 2) }}</span></dd>
          </dl>
          <div class="cap muted" data-t="note">{{ $inv->exists ? 'As saved.' : 'Choose the party to see GST.' }} Worked out again on save.</div>
        </div>
      </div>
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>With a PO, only its parts are listed, at the sales order rate, and a Sales invoice cannot bill more than the PO balance (a credit or debit note: not more than invoiced). Without a PO, all of the customer's parts can be billed, at the rate typed or the Item master price (when the part is priced in the customer's currency). A credit note cannot give back more than was invoiced less earlier credit notes. GST: CGST + SGST in your state, IGST in another state (by GSTIN), none for export customers.</span></div>
      </fieldset>
      <input type="hidden" name="_form" value="invoice">
      @include('crm.partials.audit', ['m' => $inv])
      <div class="formfoot">
        @if ($inv->exists && ! $invLocked && $u->can('tax-invoice.delete'))
          <button class="btn sec" type="button" data-open="#cancelLayer" style="color:var(--error-700)"><svg class="ico"><use href="#i-x"/></svg>Cancel {{ strtolower($invTitle) }}</button>
        @endif
        <span class="sp"></span>
        @if ($inv->exists)@include('partials.iobtn', ['kind' => 'pdf', 'label' => 'Generate PDF', 'href' => route('sales.invoices.pdf', $inv), 'open' => null])@elseif ($invCanSave)<span class="hint txt" id="invPdfLater">Save the invoice to generate its PDF.</span>@endif
        @if ($invCanSave)<button class="btn pri" type="submit"><svg class="ico"><use href="#i-check"/></svg>{{ $invNew ? 'Save '.strtolower($invTitle) : 'Save changes' }}</button>@endif
      </div>
    </form>
  </div>
</div>

<template id="invLineTpl">
  <tr>
    <td data-label="Item" class="wide">
      <div class="picker" data-picker="field" data-url="__URL__" data-placeholder="Type an item no or description">
        <select class="inp" id="inv_item___i__" name="lines[__i__][item_id]" aria-label="Item __n__"><option value="">Select</option></select>
      </div>
    </td>
    <td data-label="Quantity"><input class="inp num" name="lines[__i__][quantity]" inputmode="decimal" data-calc aria-label="Quantity, item __n__"></td>
    <td data-label="Rate"><input class="inp num" name="lines[__i__][rate]" inputmode="decimal" data-calc placeholder="Item price" aria-label="Rate, item __n__"></td>
    <td class="r num" data-label="Amount" data-amount></td>
    <td data-label="PO balance" class="cap muted"></td>
    <td class="del"><button class="btn gh icon sm" type="button" data-del-line aria-label="Remove item __n__"><svg class="ico"><use href="#i-x"/></svg></button></td>
  </tr>
</template>

@if ($inv->exists && ! $invLocked && $u->can('tax-invoice.delete'))
  <div class="overlay" id="cancelLayer" hidden @if ($ie->has('cancel_reason')) data-autoopen @endif>
    <div class="scrim" data-x></div>
    <form class="modal" role="dialog" aria-modal="true" aria-labelledby="cancelT" method="POST" action="{{ route('sales.invoices.cancel', $inv) }}" data-saving>
      @csrf
      <div class="mh"><h3 id="cancelT">Cancel {{ $inv->invoice_no }}</h3></div>
      <div class="mb">
        <div class="field">
          <label for="cancel_reason">Reason<span class="req">*</span></label>
          <textarea class="inp @error('cancel_reason', 'invoice') err @enderror" id="cancel_reason" name="cancel_reason" rows="3" maxlength="500" style="height:auto;padding-top:8px" required>{{ old('cancel_reason') }}</textarea>
          @include('partials.ferr', ['name' => 'cancel_reason', 'bag' => 'invoice'])
        </div>
        <div class="small muted">The number stays in the series and the invoice is kept as Cancelled. Quantities billed go back to the PO, and the stock it moved is returned.</div>
      </div>
      <div class="mf" style="display:flex;gap:8px;justify-content:flex-end;padding:12px 20px 18px">
        <button class="btn gh" type="button" data-x>Keep invoice</button>
        <button class="btn pri" type="submit" style="background:var(--error-600)">Cancel invoice</button>
      </div>
    </form>
  </div>
@endif
@include('crm.partials.history', ['history' => $invHistory])
@endcan

{{-- ================= Sales order (closed by default, D5); approvers see it read-only with Approve / Reject ================= --}}
@canany(['sales-order.view', 'sales-order-approval.view'])
<details class="sectcard soCard" id="soCard" @if ($soOpen) open @endif @if ($open === 'order' && ! $ie->any()) data-focus @endif style="margin-top:16px">
  <summary class="sh" style="cursor:pointer;list-style:none">
    <h2>Sales order</h2>
    @if ($so->exists)<span class="badge {{ $so->badgeClass() }}">{{ $so->status }}</span>@endif
    <span class="sp"></span>
    <div class="autono"><span>SO no <b>{{ $so->so_no ?? 'on save' }}</b></span></div>
    <span class="btn sec sm" aria-hidden="true"><svg class="ico chev"><use href="#i-down"/></svg><span class="when-open">Hide</span><span class="when-shut">Show</span></span>
  </summary>
  <div class="sb">
    @include('crm.partials.entrybar', [
      'action' => route('sales.orders.index'), 'lookupUrl' => route('sales.orders.lookup'), 'q' => $q, 'options' => $soOptions, 'current' => $so->exists,
      'newLabel' => 'New sales order', 'placeholder' => 'SO no, PO number or party name',
      'addUrl' => $u->can('sales-order.add') ? route('sales.orders.create') : null, 'buttons' => $soButtons,
      'open' => null, {{-- 02/11/2026: as on the invoice card, so Download CSV is a link --}}
    ])

    @if ($oe->any())
      <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>{{ $oe->hasAny(['delete', 'status']) ? 'This was not done.' : 'Check the fields marked in red. Nothing was saved.' }}</b><ul>@foreach ($oe->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
    @endif

    @if ($so->exists)
      {{-- Approval (D3): the next actions for the current status only. --}}
      <div class="statusbar" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px">
        <span class="badge {{ $so->badgeClass() }}">{{ $so->status }}</span>
        @if ($so->status_reason)<span class="cap" style="color:var(--error-700)">{{ $so->status_reason }}</span>@endif
        {{-- Create Sales Order from a customer PO (02/11/2026): the PO it was made from, linked when the user can open it. --}}
        @if ($so->customerPo)
          @php($poUrl = $so->customerPo->pageUrlFor($u))
          <span class="cap" id="soFromPo">From PO @if ($poUrl)<a href="{{ $poUrl }}"><b>{{ $so->customerPo->po_no }}</b></a>@else<b>{{ $so->customerPo->po_no }}</b>@endif{{ $so->customerPo->trashed() ? ' (deleted)' : '' }}</span>
        @endif
        <span class="sp" style="flex:1"></span>
        @if (in_array($so->status, [\App\Models\SalesOrder::DRAFT, \App\Models\SalesOrder::REJECTED], true) && $u->can('sales-order.edit'))
          <form method="POST" action="{{ route('sales.orders.submit', $so) }}" data-saving @if ($ask = \App\Support\ApprovalNotice::message($rule, (float) $so->total_value, $so->party?->currency?->code, 'sales order')) data-confirm-title="Send {{ $so->so_no }} for approval" data-confirm="{{ $ask }} Save any unsaved changes first." @endif>@csrf
            <button class="btn pri sm" type="submit"><svg class="ico"><use href="#i-check"/></svg>Send for approval</button>
          </form>
        @endif
        @if ($so->status === \App\Models\SalesOrder::PENDING)
          @can('sales-order-approval.edit')
            <button class="btn sec sm" type="button" data-open="#soRejectLayer" style="color:var(--error-700)"><svg class="ico"><use href="#i-x"/></svg>Reject</button>
            <form method="POST" action="{{ route('sales.orders.approve', $so) }}" data-saving>@csrf<button class="btn pri sm" type="submit"><svg class="ico"><use href="#i-check"/></svg>Approve</button></form>
          @else
            <span class="cap muted">Waiting for an approver.</span>
          @endcan
        @endif
        @if ($so->status === \App\Models\SalesOrder::APPROVED && $u->can('tax-invoice.add'))
          <a class="btn pri sm" href="{{ route('sales.invoices.create', ['order' => $so->id]) }}"><svg class="ico"><use href="#i-file"/></svg>Make tax invoice</a>
        @endif
      </div>
      @if (! $soCanSave && $u->can('sales-order.edit'))
        <div class="alert info"><svg class="ico"><use href="#i-lock"/></svg><span>{{ $so->so_no }} is {{ $so->status }}, so only an Admin can change it.</span></div>
      @endif
    @endif

    <form method="POST" action="{{ $soNew ? route('sales.orders.store') : route('sales.orders.update', $so) }}" novalidate id="orderForm" data-dirty-guard data-saving data-lines-form="#soLines" data-currency="{{ $soCurrency }}">
      @csrf
      @unless ($soNew) @method('PUT') @endunless
      <fieldset @disabled(! $soCanSave) style="border:0;padding:0;margin:0;min-width:0;display:grid;gap:14px">
      <div class="fgrid">
        <div class="field">
          <label for="so_party_id">Party name<span class="req">*</span></label>
          <div class="picker" data-picker="field" data-url="{{ route('sales.parties.lookup') }}" data-placeholder="Type a customer name or GST no">
            <select class="inp @error('party_id', 'order') err @enderror" id="so_party_id" name="party_id" aria-required="true">
              <option value="">Select</option>
              @foreach ($soCustomers as $id => $name)<option value="{{ $id }}" @selected((string) $soParty === (string) $id)>{{ $name }}</option>@endforeach
            </select>
          </div>
          @include('partials.ferr', ['name' => 'party_id', 'bag' => 'order'])
        </div>
        <div class="field">
          <label for="po_number">PO number<span class="req">*</span></label>
          <input class="inp @error('po_number', 'order') err @enderror" id="po_number" name="po_number" value="{{ $sv('po_number') }}" maxlength="60" required>
          @include('partials.ferr', ['name' => 'po_number', 'bag' => 'order'])
        </div>
        <div class="field">
          <label for="start_date">Start date<span class="req">*</span></label>
          <input class="inp num @error('start_date', 'order') err @enderror" id="start_date" name="start_date" value="{{ $sd('start_date') }}" placeholder="dd/mm/yyyy" inputmode="numeric" required>
          @include('partials.ferr', ['name' => 'start_date', 'bag' => 'order'])
        </div>
        @if ($sps->show('end_date'))
        <div class="field">
          <label for="end_date">End date{{ $sps->star('end_date') }}</label>
          <input class="inp num @error('end_date', 'order') err @enderror" id="end_date" name="end_date" value="{{ $sd('end_date') }}" placeholder="dd/mm/yyyy" inputmode="numeric" @required($sps->req('end_date'))>
          @include('partials.ferr', ['name' => 'end_date', 'bag' => 'order'])
        </div>
        @endif
        @if ($sps->show('revision'))
        <div class="field">
          <label for="revision_no">Rev no{{ $sps->star('revision') }}</label>
          <input class="inp num @error('revision_no', 'order') err @enderror" id="revision_no" name="revision_no" value="{{ $sv('revision_no') }}" maxlength="10" @required($sps->req('revision'))>
          <span class="hint txt">The customer PO's revision.</span>
          @include('partials.ferr', ['name' => 'revision_no', 'bag' => 'order'])
        </div>
        <div class="field">
          <label for="revision_date">Rev date{{ $sps->star('revision') }}</label>
          <input class="inp num @error('revision_date', 'order') err @enderror" id="revision_date" name="revision_date" value="{{ $sd('revision_date') }}" placeholder="dd/mm/yyyy" inputmode="numeric" @required($sps->req('revision'))>
          @include('partials.ferr', ['name' => 'revision_date', 'bag' => 'order'])
        </div>
        @endif
      </div>

      <div class="lbl">Items<span class="req">*</span></div>
      <div class="tablecard" style="overflow-x:auto">
        <table class="atable lines rtable">
          <thead><tr><th style="min-width:260px">Item no / Description / IPN</th><th class="r" style="min-width:110px">Quantity<span class="req">*</span></th><th class="r" style="min-width:110px">Rate</th><th style="min-width:130px">Delivery date<span class="req">*</span></th><th class="r" style="min-width:110px">Amount</th><th class="r">Invoiced</th><th><span class="sr">Remove</span></th></tr></thead>
          <tbody id="soLines" data-items-url="{{ $itemsUrl($soParty) }}">
            @foreach ($soLines as $i => $l)
              @php($ri = $l->item_id ? $soRateItems->get($l->item_id) : null)
              <tr @if ($ri) data-price="{{ (float) $ri->price }}" data-cur="{{ $ri->currency?->code ?? 'INR' }}" @endif>
                <td data-label="Item" class="wide">
                  @if ($l->id)<input type="hidden" name="lines[{{ $i }}][id]" value="{{ $l->id }}">@endif
                  <div class="picker" data-picker="field" data-url="{{ $itemsUrl($soParty) }}" data-placeholder="Type an item no or description">
                    <select class="inp @error("lines.$i.item_id", 'order') err @enderror" id="so_item_{{ $i }}" name="lines[{{ $i }}][item_id]" aria-label="Item {{ $i + 1 }}">
                      <option value="">Select</option>
                      @foreach ($soItems as $id => $label)<option value="{{ $id }}" @selected((string) $l->item_id === (string) $id)>{{ $label }}</option>@endforeach
                      @if ($l->item_id && ! $soItems->has($l->item_id))<option value="{{ $l->item_id }}" selected>{{ $l->label }}</option>@endif
                    </select>
                  </div>
                  @include('partials.ferr', ['name' => "lines.$i.item_id", 'bag' => 'order'])
                </td>
                <td data-label="Quantity"><input class="inp num @error("lines.$i.quantity", 'order') err @enderror" name="lines[{{ $i }}][quantity]" value="{{ $l->quantity }}" inputmode="decimal" data-calc aria-label="Quantity, item {{ $i + 1 }}">@include('partials.ferr', ['name' => "lines.$i.quantity", 'bag' => 'order'])</td>
                <td data-label="Rate"><input class="inp num @error("lines.$i.rate", 'order') err @enderror" name="lines[{{ $i }}][rate]" value="{{ $l->rate }}" inputmode="decimal" data-calc placeholder="Item price" aria-label="Rate, item {{ $i + 1 }}">@include('partials.ferr', ['name' => "lines.$i.rate", 'bag' => 'order'])</td>
                <td data-label="Delivery date"><input class="inp num @error("lines.$i.delivery_date", 'order') err @enderror" name="lines[{{ $i }}][delivery_date]" value="{{ $l->delivery_date }}" placeholder="dd/mm/yyyy" inputmode="numeric" aria-label="Delivery date, item {{ $i + 1 }}">@include('partials.ferr', ['name' => "lines.$i.delivery_date", 'bag' => 'order'])</td>
                <td class="r num" data-label="Amount" data-amount></td>
                <td class="r num" data-label="Invoiced">{{ $l->invoiced ? \App\Support\Money::qty($l->invoiced) : '' }}</td>
                <td class="del">@if ($soCanSave && ! $l->invoiced)<button class="btn gh icon sm" type="button" data-del-line aria-label="Remove item {{ $i + 1 }}"><svg class="ico"><use href="#i-x"/></svg></button>@endif</td>
              </tr>
            @endforeach
          </tbody>
            <tfoot><tr class="grand"><td colspan="4" class="r" data-label=""><b>Order total</b> <span class="cap muted">(worked out again on save)</span></td><td class="r num" data-label="Order total"><b id="soTotal">{{ \App\Support\Money::indian($so->total_value ?? 0, 2) }}</b></td><td colspan="2" data-label=""></td></tr></tfoot>
        </table>
      </div>
      @include('partials.ferr', ['name' => 'lines', 'bag' => 'order'])
      @if ($soCanSave)<div><button class="btn add sm" type="button" data-add-line="#soLines"><svg class="ico"><use href="#i-plus"/></svg>Add item</button></div>@endif
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>{{ $sps->mandatory() }} Each item needs a quantity and delivery date; leave Rate blank for the Item master price. Upload the customer PO below after saving. An invoiced item stays on the order and cannot go below what was invoiced.</span></div>
      </fieldset>
      <input type="hidden" name="_form" value="order">
      @include('crm.partials.audit', ['m' => $so])
      <div class="formfoot">
        @if ($so->exists && $u->can('sales-order.delete') && $so->isEditableBy($u))
          <button class="btn sec" type="submit" form="soDelForm" style="color:var(--error-700)"><svg class="ico"><use href="#i-x"/></svg>Delete sales order</button>
        @endif
        <span class="sp"></span>
        @if ($soCanSave)<button class="btn pri" type="submit"><svg class="ico"><use href="#i-check"/></svg>{{ $soNew ? 'Save sales order' : 'Save changes' }}</button>@endif
      </div>
    </form>

    @if ($so->exists && $u->can('sales-order.delete') && $so->isEditableBy($u))
      <form id="soDelForm" method="POST" action="{{ route('sales.orders.destroy', $so) }}" data-confirm="Delete sales order {{ $so->so_no }}? An order with invoices cannot be deleted.">@csrf @method('DELETE')</form>
    @endif

    @if ($so->exists)
      @include('crm.partials.documents', ['type' => 'sales_order', 'record' => $so, 'docs' => $soDocs, 'canEdit' => $u->can('sales-order.edit') && $so->isEditableBy($u),
        'title' => 'Upload PO', 'default' => 'Customer PO', 'hint' => 'The customer PO: PDF, JPG or PNG, up to 10 MB.'])
      @if ($approvals->isNotEmpty())
        <div class="sectcard">
          <div class="sh"><h2>Approval history</h2></div>
          <div class="sb">
            @foreach ($approvals as $a)
              <div class="timeline"><div class="ev"><span></span><div><b>{{ $a->action }}</b> · total {{ \App\Support\Money::indian($a->amount, 2) }}@if ($a->remarks) · {{ $a->remarks }}@endif<div class="cap muted">{{ $a->actor?->name ?? 'System' }} · {{ $a->acted_at->format(config('nursery.datetime_format')) }}</div></div></div></div>
            @endforeach
          </div>
        </div>
      @endif
      @include('crm.partials.history', ['history' => $soHistory])
    @endif
  </div>
</details>

<template id="soLineTpl">
  <tr>
    <td data-label="Item" class="wide">
      <div class="picker" data-picker="field" data-url="__URL__" data-placeholder="Type an item no or description">
        <select class="inp" id="so_item___i__" name="lines[__i__][item_id]" aria-label="Item __n__"><option value="">Select</option></select>
      </div>
    </td>
    <td data-label="Quantity"><input class="inp num" name="lines[__i__][quantity]" inputmode="decimal" data-calc aria-label="Quantity, item __n__"></td>
    <td data-label="Rate"><input class="inp num" name="lines[__i__][rate]" inputmode="decimal" data-calc placeholder="Item price" aria-label="Rate, item __n__"></td>
    <td data-label="Delivery date"><input class="inp num" name="lines[__i__][delivery_date]" placeholder="dd/mm/yyyy" inputmode="numeric" aria-label="Delivery date, item __n__"></td>
    <td class="r num" data-label="Amount" data-amount></td>
    <td class="r num" data-label="Invoiced"></td>
    <td class="del"><button class="btn gh icon sm" type="button" data-del-line aria-label="Remove item __n__"><svg class="ico"><use href="#i-x"/></svg></button></td>
  </tr>
</template>

@if ($so->exists && $so->status === \App\Models\SalesOrder::PENDING && $u->can('sales-order-approval.edit'))
  <div class="overlay" id="soRejectLayer" hidden @if ($oe->has('remarks')) data-autoopen @endif>
    <div class="scrim" data-x></div>
    <form class="modal" role="dialog" aria-modal="true" aria-labelledby="soRejT" method="POST" action="{{ route('sales.orders.reject', $so) }}" data-saving>
      @csrf
      <div class="mh"><h3 id="soRejT">Reject {{ $so->so_no }}</h3></div>
      <div class="mb">
        <div class="field">
          <label for="so_remarks">Reason<span class="req">*</span></label>
          <textarea class="inp @error('remarks', 'order') err @enderror" id="so_remarks" name="remarks" rows="3" maxlength="500" style="height:auto;padding-top:8px" required></textarea>
          @include('partials.ferr', ['name' => 'remarks', 'bag' => 'order'])
        </div>
      </div>
      <div class="mf" style="display:flex;gap:8px;justify-content:flex-end;padding:12px 20px 18px">
        <button class="btn gh" type="button" data-x>Back</button>
        <button class="btn pri" type="submit" style="background:var(--error-600)">Reject</button>
      </div>
    </form>
  </div>
@endif

@can('sales-order.add')
  @include('crm.partials.import', [
    'id' => 'soImport', 'title' => 'Import sales orders', 'action' => route('sales.orders.import'), 'template' => route('sales.orders.template'),
    'help' => '<p>One row per item. Rows with the same <b>Group</b> value become one sales order; its header (party, PO number, dates, Rev no) is read from the group\'s first row. Each order gets a new SO number in the unit on the top bar and starts as a Draft.</p><p><b>Party name</b> must be an active customer, <b>Item no</b> one of its sales items; Quantity and Delivery date are needed, Rate blank = the Item master price. A PO number cannot repeat for the same party.</p><p>The whole file is checked first. If anything is wrong, nothing is saved and the rows to fix are listed.</p>',
  ])
@endcan
@endcanany
@endsection

@push('scripts')
<script src="{{ asset('js/crm.js') }}?v={{ filemtime(public_path('js/crm.js')) }}"></script>
<script src="{{ asset('js/sales.js') }}?v={{ filemtime(public_path('js/sales.js')) }}"></script>
@endpush
