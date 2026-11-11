{{-- Admin › Masters › Company: printed on tax invoices; the GSTIN state decides CGST + SGST or IGST. --}}
@php
  $canSave = auth()->user()->can('masters.edit');
  $v = fn ($f) => old($f, $company->{$f});
  $ps = app(\App\Services\PageSetupService::class)->form('company');
  $fields = ['gstin' => ['GSTIN', 15, 'Its first two digits are your state: customers in the same state get CGST + SGST, others IGST.'], 'pan' => ['PAN', 10, ''], 'phone' => ['Phone', 30, ''], 'email' => ['Email', 191, '']];
@endphp
@extends('layouts.app', ['title' => 'Company', 'crumbs' => [['Admin', route('admin.users.index')], ['Masters', route('admin.masters.index')], ['Company', null]]])

@section('content')
<div class="pagehd"><h1>Company</h1><span class="sp"></span></div>

@if ($errors->any())
  <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>Check the fields marked in red.</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
@endif
@unless ($company->exists)
  <div class="alert info"><svg class="ico"><use href="#i-info"/></svg><span>Enter your company's details. They are printed on tax invoices.</span></div>
@endunless

<form method="POST" action="{{ route('admin.company.update') }}" enctype="multipart/form-data" novalidate data-dirty-guard data-saving>
  @csrf @method('PUT')
  <div class="sectcard">
    <div class="sh"><h2>Company details</h2></div>
    <div class="sb">
      <fieldset @disabled(! $canSave) style="border:0;padding:0;margin:0;min-width:0;display:grid;gap:14px">
      <div class="fgrid">
        <div class="field">
          <label for="name">Name<span class="req">*</span></label>
          <input class="inp @error('name') err @enderror" id="name" name="name" value="{{ $v('name') }}" maxlength="150" required>
          @include('partials.ferr', ['name' => 'name'])
        </div>
        @foreach ($fields as $f => [$label, $max, $hint])
          @continue(! $ps->show($f))
          <div class="field">
            <label for="{{ $f }}">{{ $label }}{{ $ps->star($f) }}</label>
            <input class="inp @error($f) err @enderror" id="{{ $f }}" name="{{ $f }}" value="{{ $v($f) }}" maxlength="{{ $max }}" @if (in_array($f, ['gstin', 'pan'], true)) style="text-transform:uppercase" @endif @if ($f === 'email') type="email" inputmode="email" @endif @required($ps->req($f))>
            @if ($hint)<span class="hint txt">{{ $hint }}</span>@endif
            @include('partials.ferr', ['name' => $f])
          </div>
        @endforeach
        @if ($ps->show('address'))
        <div class="field" style="grid-column:1/-1">
          <label for="address">Address{{ $ps->star('address') }}</label>
          <textarea class="inp @error('address') err @enderror" id="address" name="address" rows="2" maxlength="1000" style="height:auto;padding-top:8px" @required($ps->req('address'))>{{ $v('address') }}</textarea>
          @include('partials.ferr', ['name' => 'address'])
        </div>
        @endif
        {{-- Company logo (23/10/2026): printed at the top left of the quotation PDF; kept on the private disk. --}}
        <div class="field" style="grid-column:1/-1">
          <label for="logo">Company logo</label>
          @if ($logoUri = $company->logoDataUri(false))
            <div style="margin-bottom:6px"><img src="{{ $logoUri }}" alt="Current company logo" style="max-height:60px;max-width:240px;border:1px solid #e5e7eb;padding:4px;background:#fff"></div>
          @endif
          <input class="inp @error('logo') err @enderror" id="logo" type="file" name="logo" accept="{{ collect(\App\Models\Company::logoTypes())->map(fn ($t) => '.'.$t)->implode(',') }}" data-max-kb="{{ \App\Http\Requests\Admin\CompanyRequest::logoKb() }}" style="padding-top:6px" @error('logo') aria-invalid="true" aria-describedby="logo-err" @enderror>
          <span class="hint">{{ \App\Models\Company::logoTypesLabel() }} image, max {{ \App\Support\UploadLimit::label(\App\Http\Requests\Admin\CompanyRequest::logoKb()) }} and 4000 × 4000 px. Printed at the top left of the quotation PDF, up to 60 px high.@if ($logoUri) Choosing a new file replaces the current logo.@endif</span>
          @include('partials.ferr', ['name' => 'logo'])
          @if ($logoUri)
            <label class="chk" style="margin-top:6px"><input type="checkbox" name="remove_logo" value="1" @checked(old('remove_logo'))> Remove logo</label>
          @endif
        </div>
      </div>
      </fieldset>
    </div>
  </div>
  @if ($canSave)
    <div class="formfoot"><span class="sp"></span><button class="btn pri" type="submit"><svg class="ico"><use href="#i-check"/></svg>Save company details</button></div>
  @endif
</form>
@include('crm.partials.history', ['history' => $history])
@endsection
