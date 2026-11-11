@php
  $isNew = ! $quotation->exists;
  $u = auth()->user();
  $readOnly = $quotation->exists && ! $quotation->is_latest;
  $locked = $quotation->exists && ! $quotation->status->isEditable();
  $canSave = ! $readOnly && ! $locked && ($isNew ? $u->can('quotation.add') : $u->can('quotation.edit'));
  $df = config('nursery.date_format');
  $title = $isNew ? 'Quotation' : $quotation->quotation_no.' Rev '.$quotation->revision_no;
  $num = fn ($x) => $x === null ? '' : rtrim(rtrim((string) $x, '0'), '.');
  $options = $entries->map(fn ($e) => [
    'id' => $e->id,
    'url' => route('crm.quotations.show', ['quotation' => $e, 'q' => $q ?: null]),
    'label' => $e->quotation_no.' · Rev '.$e->revision_no.' · '.($e->customerName() ?: 'No customer'),
    'on' => $quotation->exists && ($e->id === $quotation->id || ($readOnly && $e->quotation_no === $quotation->quotation_no)),
  ])->all();
  $buttons = array_values(array_filter([
    ['href' => route('crm.quotations.list'), 'icon' => 'list', 'label' => 'Quotation list', 'nav' => true],
    $u->can('quotation.add') ? ['open' => '#importLayer', 'icon' => 'upload', 'label' => 'Import', 'kind' => 'import'] : null,
    ['href' => route('crm.quotations.export'), 'icon' => 'download', 'label' => 'Download CSV', 'kind' => 'csv'],
    $quotation->exists ? ['href' => route('crm.quotations.pdf', $quotation), 'icon' => 'file', 'label' => 'Generate PDF', 'kind' => 'pdf'] : null,
  ]));
  // UOM / Tax drop-downs from the masters (23/10/2026). Tax rate = the structure's rate for this quotation's supply type
  // (as on the tax invoice). A line saved before keeps its Unit text or Tax % as an extra "(old)" option.
  $rateTxt = fn ($r) => rtrim(rtrim(number_format((float) $r, 2, '.', ''), '0'), '.');
  $oldTax = function ($l) use ($taxStructures, $supply) {
    if ($l->tax_structure_id) { return (string) $l->tax_structure_id; }
    if (! (float) $l->tax_percent) { return ''; }
    $match = $taxStructures->first(fn ($t) => $t->is_active && abs($t->rateFor($supply) - (float) $l->tax_percent) < 0.001);
    return $match ? (string) $match->id : 'old:'.rtrim(rtrim((string) $l->tax_percent, '0'), '.');
  };
  $uomOpts = function ($val) use ($uoms) {
    $val = trim((string) $val);
    $hit = $val !== '' ? $uoms->first(fn ($o) => strcasecmp($o->code, $val) === 0) : null;
    $h = '<option value="">Select</option>';
    foreach ($uoms as $o) { $h .= '<option value="'.e($o->code).'"'.($hit && $hit->id === $o->id ? ' selected' : '').($o->description ? ' title="'.e($o->description).'"' : '').'>'.e($o->code).'</option>'; }
    if ($val !== '' && ! $hit) { $h .= '<option value="'.e($val).'" selected>'.e($val).' (old)</option>'; }
    return $h;
  };
  $taxOpts = function ($val) use ($taxStructures, $supply, $rateTxt) {
    $val = (string) $val;
    $h = '<option value="" data-rate="0">Select</option>';
    foreach ($taxStructures as $t) {
      $nominal = $rateTxt($t->rateFor(\App\Support\Gst::INTRA));
      $label = str_contains($t->name, $nominal.'%') ? $t->name : $t->name.' ('.$nominal.'%)';
      $h .= '<option value="'.$t->id.'" data-rate="'.$rateTxt($t->rateFor($supply)).'"'.($val === (string) $t->id ? ' selected' : '').'>'.e($label).'</option>';
    }
    if (str_starts_with($val, 'old:') && is_numeric($r = substr($val, 4))) { $h .= '<option value="'.e($val).'" data-rate="'.e($r).'" selected>'.e($r).'% (old)</option>'; }
    return $h;
  };
  $lines = old('lines', $quotation->exists
    ? $quotation->lines->map(fn ($l) => [
        'item_no' => $l->item_no, 'description' => $l->description,
        'qty' => $num($l->qty ?? $l->annual_volume), 'uom' => $l->uom, 'rate' => $num($l->rate),
        'discount_percent' => $num($l->discount_percent), 'tax_structure_id' => $oldTax($l),
      ])->all()
    : [[]]);
  if (! $lines) { $lines = [[]]; }
  $cost = $quotation->cost;
  $v = fn ($f) => old($f, $quotation->{$f} instanceof \BackedEnum ? $quotation->{$f}->value : $quotation->{$f});
  $st = $quotation->status;
  $client = $quotation->enquiry?->clientName() ?: $quotation->customerName();
  // Admin > Page setup: which header fields show and which are mandatory.
  $ps = app(\App\Services\PageSetupService::class)->form('quotation');
@endphp
@extends('layouts.app', ['title' => $title, 'crumbs' => [['CRM', route('crm.enquiries.index')], ['Quotation', null]]])

@section('content')
<div class="pagehd">
  <h1>Quotation</h1>
  @if ($quotation->exists)<span class="badge {{ $st->badgeClass() }}">{{ $st->value }}</span>@endif
  <span class="sp"></span>
  <span class="cap muted">Unit: {{ $unit?->name ?? 'none' }}</span>
</div>

@include('crm.partials.entrybar', [
  'action' => route('crm.quotations.index'), 'lookupUrl' => route('crm.quotations.lookup'), 'q' => $q, 'options' => $options, 'current' => $quotation->exists,
  'newLabel' => 'New quotation', 'placeholder' => 'Quotation or enquiry no, client name or dd/mm/yyyy',
  'addUrl' => $u->can('quotation.add') ? route('crm.quotations.create') : null, 'buttons' => $buttons,
])

@if ($errors->has('form'))
  <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>Nothing was saved.</b><ul><li>{{ $errors->first('form') }}</li></ul></div></div>
@elseif ($errors->any() && ! $errors->has('delete'))
  <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>{{ $errors->has('file') ? 'The file was not imported. Nothing was saved.' : 'Check the fields marked in red.' }}</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
@endif
@if ($readOnly)
  <div class="alert info"><svg class="ico"><use href="#i-lock"/></svg><span>Rev {{ $quotation->revision_no }} is an earlier revision and is read-only. <a href="{{ route('crm.quotations.show', $revisions->firstWhere('is_latest', true) ?? $quotation) }}">Open the current revision</a> to make changes.</span></div>
@elseif ($locked)
  <div class="alert info"><svg class="ico"><use href="#i-lock"/></svg><span>Rev {{ $quotation->revision_no }} is {{ $st->value }}, so it cannot be changed. Use Revise to make a new revision for changes.</span></div>
@elseif (! $canSave && $quotation->exists)
  <div class="alert info"><svg class="ico"><use href="#i-lock"/></svg><span>You can view this quotation. Your role cannot change quotations.</span></div>
@elseif (! $quotation->exists && ! $u->can('quotation.add'))
  <div class="alert info"><svg class="ico"><use href="#i-lock"/></svg><span>There are no quotations for this unit and year yet, and your role cannot add them.</span></div>
@endif

@if ($quotation->exists && ! $readOnly)
  @include('crm.quotations.status', ['quotation' => $quotation, 'rule' => $rule, 'customerStatuses' => $customerStatuses])
@endif

<form method="POST" action="{{ $isNew ? route('crm.quotations.store') : route('crm.quotations.update', $quotation) }}" novalidate id="quotationForm" data-dirty-guard data-saving>
  @csrf
  @unless ($isNew) @method('PUT') @endunless
  <div class="sectcard">
    <div class="sh">
      <h2>Quotation</h2><span class="sp"></span>
      <div class="autono">
        <span>Enquiry no <b>{{ $quotation->enquiry_no ?? 'from enquiry' }}</b></span>
        <span>Quotation no <b>{{ $quotation->quotation_no ?? 'on save' }}</b></span>
        <span>Rev no <b>{{ $quotation->revision_no ?? 0 }}</b></span>
      </div>
      @if ($quotation->exists && $quotation->is_latest && $u->can('quotation.edit'))
        <button class="btn sec sm" type="submit" form="reviseForm"><svg class="ico"><use href="#i-refresh"/></svg>Revise</button>
      @endif
    </div>
    <div class="sb">
      @if ($revisions->count() > 1)
        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center"><span class="small muted">Revisions</span>
          @foreach ($revisions as $r)
            <a class="badge {{ $r->id === $quotation->id ? 'b-pri' : 'b-neu' }}" href="{{ route('crm.quotations.show', $r) }}" style="text-decoration:none">Rev {{ $r->revision_no }} · {{ $r->status->value }}{{ $r->is_latest ? ' · current' : '' }}</a>
          @endforeach
        </div>
      @endif

      <fieldset @disabled(! $canSave) style="border:0;padding:0;margin:0;min-width:0;display:grid;gap:14px">
      <div class="fgrid">
        <div class="field" style="grid-column:1/-1">
          @if ($isNew)
            <label for="enquiry_id">Enquiry<span class="req">*</span></label>
            <div class="picker" data-picker="field" data-url="{{ route('crm.enquiries.lookup') }}" data-placeholder="Type an enquiry no or client name">
              <select class="inp @error('enquiry_id') err @enderror" id="enquiry_id" name="enquiry_id" aria-required="true">
                <option value="">Select</option>
                @foreach ($enquiryOptions as $id => $label)<option value="{{ $id }}" @selected((string) $v('enquiry_id') === (string) $id)>{{ $label }}</option>@endforeach
              </select>
            </div>
            <span class="hint txt">The client and enquiry no come from the enquiry. Open a quotation from the enquiry page to fill contact and site too.</span>
            @include('partials.ferr', ['name' => 'enquiry_id'])
          @else
            <span class="lbl" style="font-size:13px;font-weight:500">Client</span>
            <div class="kvline"><b>{{ $client ?: 'Not given' }}</b>
              @if ($quotation->enquiry_id && $u->can('enquiry.view')) · <a href="{{ route('crm.enquiries.show', $quotation->enquiry_id) }}">Enquiry {{ $quotation->enquiry_no }}</a>@endif
            </div>
          @endif
        </div>
        @if ($ps->show('contact_name'))
        <div class="field">
          <label for="contact_name">Contact{{ $ps->star('contact_name') }}</label>
          <input class="inp @error('contact_name') err @enderror" id="contact_name" name="contact_name" value="{{ $v('contact_name') }}" maxlength="150" @required($ps->req('contact_name'))>
          @include('partials.ferr', ['name' => 'contact_name'])
          @if (($ph = $quotation?->enquiry?->clientPhone()))<span class="hint txt">Phone {{ $ph }} (from the Prospect)</span>@endif

        </div>
        @endif
        @if ($ps->show('site'))
        <div class="field">
          <label for="site">Site{{ $ps->star('site') }}</label>
          <input class="inp @error('site') err @enderror" id="site" name="site" value="{{ $v('site') }}" maxlength="255" @required($ps->req('site'))>
          @include('partials.ferr', ['name' => 'site'])
        </div>
        @endif
        <div class="field">
          <label for="quotation_date">Date<span class="req">*</span></label>
          <input class="inp num @error('quotation_date') err @enderror" id="quotation_date" name="quotation_date" value="{{ old('quotation_date', $quotation->quotation_date?->format($df)) }}" placeholder="dd/mm/yyyy" inputmode="numeric" required>
          @include('partials.ferr', ['name' => 'quotation_date'])
        </div>
        @if ($ps->show('valid_until'))
        <div class="field">
          <label for="valid_until">Valid until{{ $ps->star('valid_until') }}</label>
          <input class="inp num @error('valid_until') err @enderror" id="valid_until" name="valid_until" value="{{ old('valid_until', $quotation->valid_until?->format($df)) }}" placeholder="dd/mm/yyyy" inputmode="numeric" @required($ps->req('valid_until'))>
          @include('partials.ferr', ['name' => 'valid_until'])
        </div>
        @endif
        <div class="field">
          <label for="sales_person_id">Sales person<span class="req">*</span></label>
          <div class="picker" data-picker="field" data-url="{{ route('lookup.sales-people') }}" data-placeholder="Type a sales person's name">
            <select class="inp @error('sales_person_id') err @enderror" id="sales_person_id" name="sales_person_id" aria-required="true">
              <option value="">Select</option>
              @foreach ($salesPeople as $id => $name)<option value="{{ $id }}" @selected((string) $v('sales_person_id') === (string) $id)>{{ $name }}</option>@endforeach
            </select>
          </div>
          <span class="hint txt">{{ ! $hasSalesPeople ? 'No active user has the Sales role yet. Add one in Admin › Users list.' : 'Active users with the Sales role' }}</span>
          @include('partials.ferr', ['name' => 'sales_person_id'])
        </div>
        <div class="field">
          <span class="lbl" style="font-size:13px;font-weight:500">Prepared by</span>
          <div class="kvline">{{ $quotation->preparer?->name ?? $u->name }}</div>
        </div>
        <div class="field">
          <label for="currency_id">Currency<span class="req">*</span></label>
          <select class="inp @error('currency_id') err @enderror" id="currency_id" name="currency_id" required>
            @foreach ($currencies as $c)<option value="{{ $c->id }}" @selected((string) $v('currency_id') === (string) $c->id)>{{ $c->code }} · {{ $c->country }}</option>@endforeach
          </select>
          @include('partials.ferr', ['name' => 'currency_id'])
        </div>
        @if ($ps->show('payment_terms'))
        <div class="field">
          <label for="payment_terms">Payment terms{{ $ps->star('payment_terms') }}</label>
          <input class="inp @error('payment_terms') err @enderror" id="payment_terms" name="payment_terms" value="{{ $v('payment_terms') }}" maxlength="255" placeholder="e.g. 50% advance, balance on installation" @required($ps->req('payment_terms'))>
          @include('partials.ferr', ['name' => 'payment_terms'])
        </div>
        @endif
        @if ($ps->show('delivery_terms'))
        <div class="field">
          <label for="delivery_terms">Delivery / service terms{{ $ps->star('delivery_terms') }}</label>
          <input class="inp @error('delivery_terms') err @enderror" id="delivery_terms" name="delivery_terms" value="{{ $v('delivery_terms') }}" maxlength="255" @required($ps->req('delivery_terms'))>
          @include('partials.ferr', ['name' => 'delivery_terms'])
        </div>
        @endif
        {{-- GST / tax from the Tax master (23/10/2026): fills the Tax of items that have none and of new items. --}}
        @if ($ps->show('tax_terms'))
        <div class="field">
          <label for="tax_structure_id">GST / tax{{ $ps->star('tax_terms') }}</label>
          <select class="inp @error('tax_structure_id') err @enderror" id="tax_structure_id" name="tax_structure_id" @required($ps->req('tax_terms'))>{!! $taxOpts((string) $v('tax_structure_id')) !!}</select>
          <span class="hint txt">Sets the Tax of items that have none; an item can still have its own.</span>
          @if ($quotation->tax_terms)<span class="hint txt">Saved before as text: {{ $quotation->tax_terms }}</span>@endif
          @include('partials.ferr', ['name' => 'tax_structure_id'])
        </div>
        @endif
        @if ($ps->show('notes'))
        <div class="field" style="grid-column:1/-1">
          <label for="notes">Notes{{ $ps->star('notes') }}</label>
          <textarea class="inp @error('notes') err @enderror" id="notes" name="notes" rows="2" maxlength="2000" style="height:auto;padding-top:8px" @required($ps->req('notes'))>{{ $v('notes') }}</textarea>
          @include('partials.ferr', ['name' => 'notes'])
        </div>
        @endif
      </div>

      <div class="lbl">Items<span class="req">*</span></div>
      <div class="tablecard" style="overflow-x:auto">
        <table class="atable lines" id="qTable">
          <thead><tr><th style="min-width:100px">Item no</th><th style="min-width:200px">Item description<span class="req">*</span></th><th class="r" style="min-width:80px">Qty<span class="req">*</span></th><th style="min-width:80px">Unit</th><th class="r" style="min-width:100px">Rate<span class="req">*</span></th><th class="r" style="min-width:76px">Discount %</th><th style="min-width:120px">Tax</th><th class="r" style="min-width:110px">Total</th><th></th></tr></thead>
          <tbody id="qLines">
            @foreach (array_values($lines) as $i => $l)
              <tr>
                <td data-label="Item no"><input class="inp" name="lines[{{ $i }}][item_no]" value="{{ $l['item_no'] ?? '' }}" maxlength="50" aria-label="Item no, item {{ $i + 1 }}"></td>
                <td data-label="Item description" class="wide"><input class="inp @error("lines.$i.description") err @enderror" name="lines[{{ $i }}][description]" value="{{ $l['description'] ?? '' }}" maxlength="255" aria-label="Item description, item {{ $i + 1 }}"></td>
                <td data-label="Qty"><input class="inp num @error("lines.$i.qty") err @enderror" name="lines[{{ $i }}][qty]" value="{{ $l['qty'] ?? '' }}" inputmode="decimal" data-calc aria-label="Qty, item {{ $i + 1 }}"></td>
                <td data-label="Unit"><select class="inp @error("lines.$i.uom") err @enderror" name="lines[{{ $i }}][uom]" aria-label="Unit, item {{ $i + 1 }}">{!! $uomOpts($l['uom'] ?? '') !!}</select></td>
                <td data-label="Rate"><input class="inp num @error("lines.$i.rate") err @enderror" name="lines[{{ $i }}][rate]" value="{{ $l['rate'] ?? '' }}" inputmode="decimal" data-calc aria-label="Rate, item {{ $i + 1 }}"></td>
                <td data-label="Discount %"><input class="inp num @error("lines.$i.discount_percent") err @enderror" name="lines[{{ $i }}][discount_percent]" value="{{ $l['discount_percent'] ?? '' }}" inputmode="decimal" data-calc aria-label="Discount %, item {{ $i + 1 }}"></td>
                <td data-label="Tax"><select class="inp @error("lines.$i.tax_structure_id") err @enderror" name="lines[{{ $i }}][tax_structure_id]" data-calc aria-label="Tax, item {{ $i + 1 }}">{!! $taxOpts($l['tax_structure_id'] ?? '') !!}</select></td>
                <td class="r num" data-label="Total" data-line-total>{{ isset($quotation->lines[$i]) && ! old('lines') ? \App\Support\Money::indian($quotation->lines[$i]->amount, 2) : '' }}</td>
                <td class="del">@if ($canSave)<button class="btn gh icon sm" type="button" data-del-line aria-label="Remove item {{ $i + 1 }}"><svg class="ico"><use href="#i-x"/></svg></button>@endif</td>
              </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr><td colspan="7" class="r">Items total</td><td class="r num" id="qSubtotal" aria-live="polite">{{ \App\Support\Money::indian($quotation->subtotal, 2) }}</td><td></td></tr>
            <tr><td colspan="7" class="r"><label for="discount_amount">Discount (amount)</label></td>
              <td class="r"><input class="inp num @error('discount_amount') err @enderror" id="discount_amount" name="discount_amount" value="{{ old('discount_amount', (float) $quotation->discount_amount ? $num($quotation->discount_amount) : '') }}" inputmode="decimal" data-calc style="text-align:right"></td><td></td></tr>
            <tr class="grand"><td colspan="7" class="r"><b>Total</b></td><td class="r num"><b id="qTotal">{{ \App\Support\Money::indian($quotation->grand_total, 2) }}</b></td><td></td></tr>
          </tfoot>
        </table>
      </div>
      @include('partials.ferr', ['name' => 'lines'])
      @include('partials.ferr', ['name' => 'discount_amount'])
      @if ($canSave)<div><button class="btn add sm" type="button" id="qAdd"><svg class="ico"><use href="#i-plus"/></svg>Add item</button></div>@endif
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>Total = Qty × Rate, less Discount %, plus Tax %. Unit and Tax come from the UOM and Tax masters. Tax %: CGST + SGST in your state, IGST for a customer in another state (by GSTIN), none for an export customer (this quotation: {{ $supply }}). Totals are worked out again when the quotation is saved.</span></div>

      @module('costing')
      <div class="costbox" id="costBox">
        <div class="costhd"><h3>Cost details</h3><span class="badge b-warn"><svg class="ico"><use href="#i-lock"/></svg>Internal · not printed</span></div>
        <div class="fgrid">
          @foreach (\App\Models\QuotationCost::COSTS as $f => $label)
            <div class="field">
              <label for="cost_{{ $f }}">{{ $label }}</label>
              <input class="inp num @error('cost.'.$f) err @enderror" id="cost_{{ $f }}" name="cost[{{ $f }}]" value="{{ old('cost.'.$f, $cost && (float) $cost->{$f} ? $num($cost->{$f}) : '') }}" inputmode="decimal" data-cost>
              @include('partials.ferr', ['name' => 'cost.'.$f])
            </div>
          @endforeach
          <div class="field">
            <span class="lbl" style="font-size:13px;font-weight:500">Total cost</span>
            <div class="kvline num" id="costTotal">{{ \App\Support\Money::indian(($cost->total_cost ?? 0), 2) }}</div>
          </div>
          <div class="field">
            <label for="cost_margin_percent">Margin %</label>
            <input class="inp num @error('cost.margin_percent') err @enderror" id="cost_margin_percent" name="cost[margin_percent]" value="{{ old('cost.margin_percent', $cost && (float) $cost->margin_percent ? $num($cost->margin_percent) : '') }}" inputmode="decimal" data-cost>
            @include('partials.ferr', ['name' => 'cost.margin_percent'])
          </div>
          <div class="field">
            <span class="lbl" style="font-size:13px;font-weight:500">Margin amount</span>
            <div class="kvline num" id="costMargin">{{ \App\Support\Money::indian(($cost->margin_amount ?? 0), 2) }}</div>
          </div>
          <div class="field">
            <span class="lbl" style="font-size:13px;font-weight:500">Proposed selling price</span>
            <div class="kvline num"><b id="costPrice">{{ \App\Support\Money::indian(($cost->proposed_selling_price ?? 0), 2) }}</b></div>
          </div>
        </div>
        <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>Total cost is the sum of the costs; Margin amount = Total cost × Margin %; Proposed selling price = Total cost + Margin amount. Cost details are optional.</span></div>
      </div>
      @endmodule
      </fieldset>
      @include('crm.partials.audit', ['m' => $quotation])
    </div>
  </div>

  {{-- Thu 22/10/2026: no Delete quotation button on this page (the destroy route stays). --}}
  <div class="formfoot">
    <span class="sp"></span>
    @if ($quotation->exists)@include('partials.iobtn', ['kind' => 'pdf', 'label' => 'Generate PDF', 'href' => route('crm.quotations.pdf', $quotation)])@endif
    @if ($canSave)<button class="btn pri" type="submit"><svg class="ico"><use href="#i-check"/></svg>{{ $isNew ? 'Save quotation' : 'Save changes' }}</button>@endif
  </div>
</form>

<template id="qLineTpl">
  <tr>
    <td data-label="Item no"><input class="inp" name="lines[__i__][item_no]" maxlength="50" aria-label="Item no, item __n__"></td>
    <td data-label="Item description" class="wide"><input class="inp" name="lines[__i__][description]" maxlength="255" aria-label="Item description, item __n__"></td>
    <td data-label="Qty"><input class="inp num" name="lines[__i__][qty]" inputmode="decimal" data-calc aria-label="Qty, item __n__"></td>
    <td data-label="Unit"><select class="inp" name="lines[__i__][uom]" aria-label="Unit, item __n__">{!! $uomOpts('') !!}</select></td>
    <td data-label="Rate"><input class="inp num" name="lines[__i__][rate]" inputmode="decimal" data-calc aria-label="Rate, item __n__"></td>
    <td data-label="Discount %"><input class="inp num" name="lines[__i__][discount_percent]" inputmode="decimal" data-calc aria-label="Discount %, item __n__"></td>
    <td data-label="Tax"><select class="inp" name="lines[__i__][tax_structure_id]" data-calc aria-label="Tax, item __n__">{!! $taxOpts('') !!}</select></td>
    <td class="r num" data-label="Total" data-line-total></td>
    <td class="del"><button class="btn gh icon sm" type="button" data-del-line aria-label="Remove item __n__"><svg class="ico"><use href="#i-x"/></svg></button></td>
  </tr>
</template>

@if ($quotation->exists && $quotation->is_latest && $u->can('quotation.edit'))
  <form id="reviseForm" method="POST" action="{{ route('crm.quotations.revise', $quotation) }}" data-confirm="Make Rev {{ $quotation->revision_no + 1 }} of {{ $quotation->quotation_no }} as a new draft? Rev {{ $quotation->revision_no }} will be kept read-only. Save any unsaved changes first.">@csrf</form>
@endif

@if ($quotation->exists)
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
  @include('crm.partials.documents', ['type' => 'quotation', 'record' => $quotation, 'docs' => $docs, 'canEdit' => $u->can('quotation.edit'), 'deletable' => false]
    + (\App\Support\Modules::on('boq')
      ? ['title' => 'BOQ and attachments', 'default' => 'BOQ', 'hint' => 'BOQ is optional. BOQ files are attached to the quotation email with the PDF.']
      : ['title' => 'Attachments', 'default' => 'Quotation']))
@endif

@include('crm.partials.history', ['history' => $history])

@can('quotation.add')
  @include('crm.partials.import', [
    'id' => 'importLayer', 'title' => 'Import quotations', 'action' => route('crm.quotations.import'), 'template' => route('crm.quotations.template'),
    'help' => '<p>One row per item. Rows with the same <b>Group</b> value become one quotation; its header (enquiry no, dates, sales person and the rest) is read from the group\'s first row. Each quotation gets a new number in the unit chosen on the top bar and starts as a Draft.</p><p><b>Enquiry no</b> must be an enquiry of this unit; the client comes from it. Sales person is the login email of an active Sales user, Currency is a code such as INR, and every item needs a description, Qty and Rate.</p><p>The whole file is checked first. If anything is wrong, nothing is saved and the rows to fix are listed.</p>',
  ])
@endcan
@endsection

@push('scripts')
<script src="{{ asset('js/crm.js') }}?v={{ filemtime(public_path('js/crm.js')) }}"></script>
@endpush
