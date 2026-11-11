@php($canSave = auth()->user()->can('approval.edit'))
@extends('layouts.app', ['title' => 'Approval', 'crumbs' => [['Admin', route('admin.users.index')], ['Approval', null]]])

@section('content')
<div class="pagehd"><h1>Approval</h1><span class="sp"></span></div>

@if ($errors->any())
  <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>Check the fields marked in red.</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
@endif

<form method="POST" action="{{ route('admin.approval.update') }}" novalidate data-dirty-guard data-saving>
  @csrf @method('PUT')
  <div class="sectcard">
    <div class="sh"><h2>Quotation</h2><span class="sp"></span><span class="cap muted">{{ implode(' + ', array_keys(array_filter(['Costing' => \App\Support\Modules::on('costing'), 'BOQ' => \App\Support\Modules::on('boq'), 'quotation (with Rev no)' => true]))) }}</span></div>
    <div class="sb">
      <fieldset @disabled(! $canSave) style="border:0;padding:0;margin:0;min-width:0">
      <div class="fgrid">
        <div class="field">
          {{-- Yes / No as a drop-down instead of the segmented radio buttons (02/11/2026). --}}
          @php($arVal = (string) (old('_so') || old('_st') || old('_po') ? (int) $rule->is_required : old('is_required', (int) $rule->is_required)))
          <label for="is_required">Approval required<span class="req">*</span></label>
          <select class="inp" id="is_required" name="is_required" aria-required="true">
            <option value="1" @selected($arVal === '1')>Yes</option>
            <option value="0" @selected($arVal === '0')>No</option>
          </select>
        </div>
        <div class="field">
          <label for="approve_above_amount">Approve only above (₹)</label>
          <input class="inp num @error('approve_above_amount') err @enderror" id="approve_above_amount" name="approve_above_amount" value="{{ old('_so') || old('_st') || old('_po') ? ($rule->approve_above_amount !== null ? rtrim(rtrim((string) $rule->approve_above_amount, '0'), '.') : '') : old('approve_above_amount', $rule->approve_above_amount !== null ? rtrim(rtrim((string) $rule->approve_above_amount, '0'), '.') : '') }}" inputmode="decimal" placeholder="Blank = every quotation">
          <span class="hint">At or below this total in rupees, a submitted quotation is approved at once. A quotation in USD or another currency always goes to an approver.</span>
          @include('partials.ferr', ['name' => 'approve_above_amount'])
        </div>
        <div class="field">
          <span class="lbl" style="font-size:13px;font-weight:500">Approvers</span>
          <div class="kvline">{{ $approverRoles->implode(', ') }}</div>
          <span class="hint">Roles with Quotation approval · Edit in Access rights.</span>
        </div>
      </div>
      </fieldset>
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>Quotations go Draft → Internal Approval → Approved, or back to Draft with the reason when rejected. Approve and Reject show on the quotation for the approver.</span></div>
    </div>
  </div>
  @if ($canSave)<div class="formfoot"><span class="sp"></span><button class="btn pri" type="submit"><svg class="ico"><use href="#i-check"/></svg>Save approval rule</button></div>@endif
</form>
<form method="POST" action="{{ route('admin.approval.sales-order') }}" novalidate data-dirty-guard data-saving style="margin-top:16px">
  @csrf @method('PUT')
  <div class="sectcard">
    <div class="sh"><h2>Sales order</h2><span class="sp"></span><span class="cap muted">Sales order with its items</span></div>
    <div class="sb">
      @if ($errors->sales_order->any())
        <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>Check the fields marked in red.</b><ul>@foreach ($errors->sales_order->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
      @endif
      <fieldset @disabled(! $canSave) style="border:0;padding:0;margin:0;min-width:0">
      <div class="fgrid">
        <div class="field">
          {{-- Yes / No as a drop-down (02/11/2026). --}}
          @php($soArVal = (string) (old('_so') ? old('is_required') : (int) $soRule->is_required))
          <label for="so_is_required">Approval required<span class="req">*</span></label>
          <select class="inp" id="so_is_required" name="is_required" aria-required="true">
            <option value="1" @selected($soArVal === '1')>Yes</option>
            <option value="0" @selected($soArVal === '0')>No</option>
          </select>
        </div>
        <div class="field">
          <label for="so_approve_above_amount">Approve only above (₹)</label>
          <input type="hidden" name="_so" value="1">
          <input class="inp num @error('approve_above_amount', 'sales_order') err @enderror" id="so_approve_above_amount" name="approve_above_amount" value="{{ old('_so') ? old('approve_above_amount') : ($soRule->approve_above_amount !== null ? rtrim(rtrim((string) $soRule->approve_above_amount, '0'), '.') : '') }}" inputmode="decimal" placeholder="Blank = every sales order">
          <span class="hint">At or below this order total in rupees, a sales order sent for approval is approved at once.</span>
          @error('approve_above_amount', 'sales_order')<span class="ferr"><svg class="ico"><use href="#i-alert"/></svg>{{ $message }}</span>@enderror
        </div>
        <div class="field">
          <span class="lbl" style="font-size:13px;font-weight:500">Approvers</span>
          <div class="kvline">{{ $soApproverRoles->implode(', ') }}</div>
          <span class="hint">Roles with Sales order approval · Edit in Access rights.</span>
        </div>
      </div>
      </fieldset>
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>Sales orders go Draft → Pending approval → Approved, or Rejected with the reason; a rejected order is corrected and sent again. Only an Approved order can be invoiced against.</span></div>
    </div>
  </div>
  @if ($canSave)<div class="formfoot"><span class="sp"></span><button class="btn pri" type="submit"><svg class="ico"><use href="#i-check"/></svg>Save sales order rule</button></div>@endif
</form>
<form method="POST" action="{{ route('admin.approval.stock-transfer') }}" novalidate data-dirty-guard data-saving style="margin-top:16px">
  @csrf @method('PUT')
  <input type="hidden" name="_st" value="1">
  <div class="sectcard">
    <div class="sh"><h2>Stock transfer</h2><span class="sp"></span><span class="cap muted">Stock transfer between units · 1 level</span></div>
    <div class="sb">
      @if ($errors->stock_transfer->any())
        <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>Check the fields marked in red.</b><ul>@foreach ($errors->stock_transfer->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
      @endif
      <fieldset @disabled(! $canSave) style="border:0;padding:0;margin:0;min-width:0">
      <div class="fgrid">
        <div class="field">
          {{-- Yes / No as a drop-down (02/11/2026). --}}
          @php($stArVal = (string) (old('_st') ? old('is_required') : (int) $stRule->is_required))
          <label for="st_is_required">Approval required<span class="req">*</span></label>
          <select class="inp" id="st_is_required" name="is_required" aria-required="true">
            <option value="1" @selected($stArVal === '1')>Yes</option>
            <option value="0" @selected($stArVal === '0')>No</option>
          </select>
          <span class="hint">A transfer has no value, so there is no amount limit. No = the stock moves as soon as the transfer is sent.</span>
        </div>
        <div class="field">
          <span class="lbl" style="font-size:13px;font-weight:500">Approvers</span>
          <div class="kvline">{{ $stApproverRoles->implode(', ') }}</div>
          <span class="hint">Roles with Stock transfer approval · Edit in Access rights.</span>
        </div>
      </div>
      </fieldset>
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>Stock transfers go Draft → Pending approval → Approved, or Rejected with the reason. Stock is usable only after Approved: it then goes out of the source unit and into the destination unit.</span></div>
    </div>
  </div>
  @if ($canSave)<div class="formfoot"><span class="sp"></span><button class="btn pri" type="submit"><svg class="ico"><use href="#i-check"/></svg>Save stock transfer rule</button></div>@endif
</form>
<form method="POST" action="{{ route('admin.approval.purchase-order') }}" novalidate data-dirty-guard data-saving style="margin-top:16px">
  @csrf @method('PUT')
  <input type="hidden" name="_po" value="1">
  <div class="sectcard">
    <div class="sh"><h2>Purchase order</h2><span class="sp"></span><span class="cap muted">Purchase order with its items</span></div>
    <div class="sb">
      @if ($errors->purchase_order->any())
        <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>Check the fields marked in red.</b><ul>@foreach ($errors->purchase_order->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
      @endif
      <fieldset @disabled(! $canSave) style="border:0;padding:0;margin:0;min-width:0">
      <div class="fgrid">
        <div class="field">
          {{-- Yes / No as a drop-down (02/11/2026). --}}
          @php($poArVal = (string) (old('_po') ? old('is_required') : (int) $poRule->is_required))
          <label for="po_is_required">Approval required<span class="req">*</span></label>
          <select class="inp" id="po_is_required" name="is_required" aria-required="true">
            <option value="1" @selected($poArVal === '1')>Yes</option>
            <option value="0" @selected($poArVal === '0')>No</option>
          </select>
        </div>
        <div class="field">
          <label for="po_approve_above_amount">Approve only above (₹)</label>
          <input class="inp num @error('approve_above_amount', 'purchase_order') err @enderror" id="po_approve_above_amount" name="approve_above_amount" value="{{ old('_po') ? old('approve_above_amount') : ($poRule->approve_above_amount !== null ? rtrim(rtrim((string) $poRule->approve_above_amount, '0'), '.') : '') }}" inputmode="decimal" placeholder="Blank = every purchase order">
          <span class="hint">At or below this value (before GST) in rupees, a purchase order sent for approval is approved at once. A PO in another currency always goes to an approver.</span>
          @error('approve_above_amount', 'purchase_order')<span class="ferr"><svg class="ico"><use href="#i-alert"/></svg>{{ $message }}</span>@enderror
        </div>
        <div class="field">
          <span class="lbl" style="font-size:13px;font-weight:500">Approvers</span>
          <div class="kvline">{{ $poApproverRoles->implode(', ') }}</div>
          <span class="hint">Roles with Purchase order approval · Edit in Access rights.</span>
        </div>
      </div>
      </fieldset>
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>Purchase orders go Draft → Pending approval → Approved, or Rejected with the reason; a rejected PO is corrected and sent again. Goods can be received (GRN) only against an Approved PO.</span></div>
    </div>
  </div>
  @if ($canSave)<div class="formfoot"><span class="sp"></span><button class="btn pri" type="submit"><svg class="ico"><use href="#i-check"/></svg>Save purchase order rule</button></div>@endif
</form>
@endsection
