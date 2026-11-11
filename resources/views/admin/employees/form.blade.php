@php
  $isNew = ! $employee->exists;
  $u = auth()->user();
  $canSave = $isNew ? $u->can('masters.add') : $u->can('masters.edit');
  $v = fn ($f) => old($f, $employee->{$f});
  $text = [
    'designation' => ['Designation', 100, 'text'], 'department' => ['Department', 100, 'text'],
    'mobile' => ['Mobile', 20, 'tel'], 'email' => ['Email', 191, 'email'],
  ];
  // Admin > Page setup: which fields show and which are mandatory.
  $ps = app(\App\Services\PageSetupService::class)->form('employee');
@endphp
@extends('layouts.app', ['title' => $isNew ? 'Add employee' : $employee->name, 'crumbs' => [['Admin', route('admin.users.index')], ['Masters', route('admin.masters.index')], ['Employee master', route('admin.employees.index')], [$isNew ? 'Add' : $employee->name, null]]])

@section('content')
<div class="pagehd">
  <h1>{{ $isNew ? 'Add employee' : $employee->name }}</h1>
  @unless ($isNew)<span class="badge {{ $employee->status === 'Active' ? 'b-ok' : 'b-neu' }}">{{ $employee->status }}</span>@endunless
  <span class="sp"></span>
  <a class="btn gh sm" href="{{ route('admin.employees.index') }}"><svg class="ico"><use href="#i-list"/></svg>Employee list</a>
</div>

@if ($errors->any() && ! $errors->has('delete'))
  <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>Check the fields marked in red.</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
@endif

<form method="POST" action="{{ $isNew ? route('admin.employees.store') : route('admin.employees.update', $employee) }}" novalidate data-dirty-guard data-saving>
  @csrf
  @unless ($isNew) @method('PUT') @endunless
  <div class="sectcard">
    <div class="sh"><h2>Employee details</h2><span class="sp"></span><span class="cap muted">Fields marked <span class="req">*</span> are required</span></div>
    <div class="sb">
      <fieldset @disabled(! $canSave) style="border:0;padding:0;margin:0;min-width:0;display:grid;gap:14px">
      <div class="fgrid">
        <div class="field">
          <label for="code">Employee code<span class="req">*</span></label>
          <input class="inp @error('code') err @enderror" id="code" name="code" value="{{ $v('code') }}" maxlength="20" required style="text-transform:uppercase">
          @include('partials.ferr', ['name' => 'code'])
        </div>
        <div class="field">
          <label for="name">Name<span class="req">*</span></label>
          <input class="inp @error('name') err @enderror" id="name" name="name" value="{{ $v('name') }}" maxlength="100" required>
          @include('partials.ferr', ['name' => 'name'])
        </div>
        @foreach ($text as $f => [$label, $max, $type])
          @continue(! $ps->show($f))
          <div class="field">
            <label for="{{ $f }}">{{ $label }}{{ $ps->star($f) }}</label>
            <input class="inp @error($f) err @enderror" id="{{ $f }}" @required($ps->req($f)) name="{{ $f }}" value="{{ $v($f) }}" maxlength="{{ $max }}" @if ($type === 'tel') inputmode="tel" @elseif ($type === 'email') type="email" inputmode="email" @endif>
            @include('partials.ferr', ['name' => $f])
          </div>
        @endforeach
        @if ($ps->show('joining_date'))
        <div class="field">
          <label for="joining_date">Date of joining{{ $ps->star('joining_date') }}</label>
          <input class="inp num @error('joining_date') err @enderror" id="joining_date" @required($ps->req('joining_date')) name="joining_date" value="{{ old('joining_date', $employee->joining_date?->format(config('nursery.date_format'))) }}" placeholder="dd/mm/yyyy" inputmode="numeric">
          @include('partials.ferr', ['name' => 'joining_date'])
        </div>
        @endif
        @if ($ps->show('user_id'))
        <div class="field">
          <label for="user_id">Login user{{ $ps->star('user_id') }}</label>
          <div class="picker" data-picker="field" data-url="{{ route('lookup.login-users') }}" data-placeholder="Type a user name or email">
            <select class="inp @error('user_id') err @enderror" id="user_id" @if ($ps->req('user_id')) aria-required="true" @endif name="user_id">
              <option value="">None</option>
              @foreach ($users as $id => $name)<option value="{{ $id }}" @selected((string) $v('user_id') === (string) $id)>{{ $name }}</option>@endforeach
            </select>
          </div>
          <span class="hint txt">Link the employee's login, so their own follow-ups show in their red pop up.</span>
          @include('partials.ferr', ['name' => 'user_id'])
        </div>
        @endif
        <div class="field">
          {{-- Status as a drop-down instead of the segmented radio buttons (02/11/2026). --}}
          <label for="status">Status<span class="req">*</span></label>
          <select class="inp @error('status') err @enderror" id="status" name="status" aria-required="true">
            @foreach (['Active', 'Inactive'] as $s)<option value="{{ $s }}" @selected($v('status') === $s)>{{ $s }}</option>@endforeach
          </select>
          @include('partials.ferr', ['name' => 'status'])
        </div>
        @if ($ps->show('address'))
        <div class="field" style="grid-column:1/-1">
          <label for="address">Address{{ $ps->star('address') }}</label>
          <textarea class="inp @error('address') err @enderror" id="address" @required($ps->req('address')) name="address" rows="2" maxlength="1000" style="height:auto;padding-top:8px">{{ $v('address') }}</textarea>
          @include('partials.ferr', ['name' => 'address'])
        </div>
        @endif
      </div>
      </fieldset>
      @include('crm.partials.audit', ['m' => $employee])
    </div>
  </div>
  <div class="formfoot">
    @if (! $isNew && $u->can('masters.delete'))
      <button class="btn sec" type="submit" form="delForm" style="color:var(--error-700)"><svg class="ico"><use href="#i-x"/></svg>Delete employee</button>
    @endif
    <span class="sp"></span>
    @if ($canSave)<button class="btn pri" type="submit"><svg class="ico"><use href="#i-check"/></svg>{{ $isNew ? 'Save employee' : 'Save changes' }}</button>@endif
  </div>
</form>
@if (! $isNew && $u->can('masters.delete'))
  <form id="delForm" method="POST" action="{{ route('admin.employees.destroy', $employee) }}" data-confirm="Delete employee {{ $employee->name }}? An employee on a lead, site visit or PO cannot be deleted; make them Inactive instead.">@csrf @method('DELETE')</form>
@endif
@include('crm.partials.history', ['history' => $history])
@endsection
