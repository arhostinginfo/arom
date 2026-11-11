{{-- Admin › Masters › Party master: one party as a form. Which fields show and which are mandatory: Admin › Page setup. --}}
@php
  $isNew = ! $party->exists;
  $u = auth()->user();
  $canSave = $isNew ? $u->can('masters.add') : $u->can('masters.edit');
  $v = fn ($f) => old($f, $party->{$f});
  $ps = app(\App\Services\PageSetupService::class)->form('party');
  $title = $isNew ? 'Add party' : $party->name;
@endphp
@extends('layouts.app', ['title' => $title, 'crumbs' => [['Admin', route('admin.users.index')], ['Masters', route('admin.masters.index')], ['Party master', route('admin.parties.index')], [$isNew ? 'Add' : $party->name, null]]])

@section('content')
<div class="pagehd">
  <h1>{{ $title }}</h1>
  @unless ($isNew)<span class="badge {{ $party->status === 'Active' ? 'b-ok' : 'b-neu' }}">{{ $party->status }}</span>@endunless
  <span class="sp"></span>
  <a class="btn gh sm" href="{{ route('admin.parties.index') }}"><svg class="ico"><use href="#i-list"/></svg>Party list</a>
</div>

@if ($errors->any() && ! $errors->has('delete'))
  <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>Check the fields marked in red.</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
@endif
@error('delete')<div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><span>{{ $message }}</span></div>@enderror

<form method="POST" action="{{ $isNew ? route('admin.parties.store') : route('admin.parties.update', $party) }}" novalidate data-dirty-guard data-saving>
  @csrf
  @unless ($isNew) @method('PUT') @endunless
  <div class="sectcard">
    <div class="sh"><h2>Party details</h2></div>
    <div class="sb">
      <fieldset @disabled(! $canSave) style="border:0;padding:0;margin:0;min-width:0;display:grid;gap:14px">
      <div class="fgrid">
        <div class="field">
          <label for="name">Name<span class="req">*</span></label>
          <input class="inp @error('name') err @enderror" id="name" name="name" value="{{ $v('name') }}" maxlength="150" required>
          @include('partials.ferr', ['name' => 'name'])
        </div>
        @if ($ps->show('gstin'))
        <div class="field">
          <label for="gstin">GST no{{ $ps->star('gstin') }}</label>
          <input class="inp @error('gstin') err @enderror" id="gstin" name="gstin" value="{{ $v('gstin') }}" maxlength="15" style="text-transform:uppercase" @required($ps->req('gstin'))>
          <span class="hint txt">The first two digits are the state: same state as the company = CGST + SGST, another state = IGST.</span>
          @include('partials.ferr', ['name' => 'gstin'])
        </div>
        @endif
        <div class="field">
          {{-- Type as a drop-down instead of the segmented radio buttons (02/11/2026). --}}
          <label for="type">Type<span class="req">*</span></label>
          <select class="inp @error('type') err @enderror" id="type" name="type" aria-required="true">
            @foreach (\App\Models\Party::TYPES as $t)<option value="{{ $t }}" @selected($v('type') === $t)>{{ $t }}</option>@endforeach
          </select>
          @include('partials.ferr', ['name' => 'type'])
        </div>
        @if ($ps->show('payment_terms'))
        <div class="field">
          <label for="payment_terms">Payment terms{{ $ps->star('payment_terms') }}</label>
          <input class="inp @error('payment_terms') err @enderror" id="payment_terms" name="payment_terms" value="{{ $v('payment_terms') }}" maxlength="100" placeholder="e.g. 30 days" @required($ps->req('payment_terms'))>
          @include('partials.ferr', ['name' => 'payment_terms'])
        </div>
        @endif
        @if ($ps->show('billing_address'))
        <div class="field" style="grid-column:1/-1">
          <label for="billing_address">Billing address{{ $ps->star('billing_address') }}</label>
          <textarea class="inp @error('billing_address') err @enderror" id="billing_address" name="billing_address" rows="2" maxlength="1000" style="height:auto;padding-top:8px" @required($ps->req('billing_address'))>{{ $v('billing_address') }}</textarea>
          @include('partials.ferr', ['name' => 'billing_address'])
        </div>
        @endif
        @if ($ps->show('shipping_address'))
        <div class="field" style="grid-column:1/-1">
          <label for="shipping_address">Shipping address{{ $ps->star('shipping_address') }}</label>
          <textarea class="inp @error('shipping_address') err @enderror" id="shipping_address" name="shipping_address" rows="2" maxlength="1000" style="height:auto;padding-top:8px" @required($ps->req('shipping_address'))>{{ $v('shipping_address') }}</textarea>
          <span class="hint txt">Filled in on new tax invoices for this party.</span>
          @include('partials.ferr', ['name' => 'shipping_address'])
        </div>
        @endif
        @if ($ps->show('opening'))
        <div class="field">
          <label for="opening_balance">Opening balance{{ $ps->star('opening') }}</label>
          <input class="inp num @error('opening_balance') err @enderror" id="opening_balance" name="opening_balance" value="{{ $party->exists || old('opening_balance') ? old('opening_balance', rtrim(rtrim((string) $party->opening_balance, '0'), '.')) : '' }}" inputmode="decimal" @required($ps->req('opening'))>
          @include('partials.ferr', ['name' => 'opening_balance'])
        </div>
        <div class="field">
          {{-- DR / CR as a drop-down (02/11/2026). --}}
          <label for="opening_balance_type">DR / CR</label>
          <select class="inp" id="opening_balance_type" name="opening_balance_type">
            @foreach (['DR', 'CR'] as $t)<option value="{{ $t }}" @selected(($v('opening_balance_type') ?: 'DR') === $t)>{{ $t }}</option>@endforeach
          </select>
        </div>
        @endif
        @if ($ps->show('currency_id'))
        <div class="field">
          <label for="currency_id">Currency{{ $ps->star('currency_id') }}</label>
          <select class="inp @error('currency_id') err @enderror" id="currency_id" name="currency_id" @if ($ps->req('currency_id')) aria-required="true" @endif>
            <option value="">Select</option>
            @foreach ($currencies as $c)<option value="{{ $c->id }}" @selected((string) $v('currency_id') === (string) $c->id)>{{ $c->code }} · {{ $c->country }}</option>@endforeach
          </select>
          <span class="hint txt">A currency other than INR makes this an export customer: invoiced without GST, under LUT.</span>
          @include('partials.ferr', ['name' => 'currency_id'])
        </div>
        @endif
        <div class="field">
          {{-- Status as a drop-down (02/11/2026). --}}
          <label for="status">Status<span class="req">*</span></label>
          <select class="inp @error('status') err @enderror" id="status" name="status" aria-required="true">
            @foreach (['Active', 'Inactive'] as $s)<option value="{{ $s }}" @selected($v('status') === $s)>{{ $s }}</option>@endforeach
          </select>
          @include('partials.ferr', ['name' => 'status'])
        </div>
      </div>
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>{{ $ps->mandatory() }} A party in use cannot be deleted; make it Inactive to hide it from lists.</span></div>
      </fieldset>
      @include('crm.partials.audit', ['m' => $party])
    </div>
  </div>
  <div class="formfoot">
    @if (! $isNew && $u->can('masters.delete'))
      <button class="btn sec" type="submit" form="delForm" style="color:var(--error-700)"><svg class="ico"><use href="#i-x"/></svg>Delete party</button>
    @endif
    <span class="sp"></span>
    @if (! $isNew)<a class="btn gh" href="{{ route('admin.items.index', ['party' => $party->id]) }}">Items of this party</a>@endif
    @if ($canSave)<button class="btn pri" type="submit"><svg class="ico"><use href="#i-check"/></svg>{{ $isNew ? 'Save party' : 'Save changes' }}</button>@endif
  </div>
</form>
@if (! $isNew && $u->can('masters.delete'))
  <form id="delForm" method="POST" action="{{ route('admin.parties.destroy', $party) }}" data-confirm="Delete party {{ $party->name }}? A party in use cannot be deleted; make it Inactive instead.">@csrf @method('DELETE')</form>
@endif
@include('crm.partials.history', ['history' => $history])
@endsection
