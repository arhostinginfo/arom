{{-- AMC › AMC Employee master: one employee with the photo. A new photo replaces the old one. --}}
@php
  $isNew = ! $e->exists;
  $u = auth()->user();
  $canSave = $isNew ? $u->can('amc-employee.add') : $u->can('amc-employee.edit');
  $v = fn ($f) => old($f, $e->{$f});
@endphp
@extends('layouts.app', ['title' => $isNew ? 'Add AMC employee' : $e->name])

@section('content')
@include('amc.partials.assets')
@include('amc.partials.master-tabs', ['on' => 'employees'])
<div class="pagehd">
  <h1>{{ $isNew ? 'Add AMC employee' : $e->code.' · '.$e->name }}</h1>
  @unless ($isNew)<span class="badge {{ $e->status === 'Active' ? 'b-ok' : 'b-neu' }}">{{ $e->status }}</span>@endunless
  <span class="sp"></span>
  @if (! $isNew && $u->can('amc-employee.add'))<a class="btn add sm" href="{{ route('amc.employees.create') }}"><svg class="ico"><use href="#i-plus"/></svg>Add new</a>@endif
  <a class="btn gh sm" href="{{ route('amc.employees.index') }}"><svg class="ico"><use href="#i-list"/></svg>Employee list</a>
</div>

@include('amc.partials.errors')

<form method="POST" action="{{ $isNew ? route('amc.employees.store') : route('amc.employees.update', $e) }}" enctype="multipart/form-data" novalidate data-dirty-guard data-saving>
  @csrf
  @unless ($isNew) @method('PUT') @endunless
  <div class="sectcard">
    <div class="sh"><h2>Employee details</h2></div>
    <div class="sb">
      <fieldset @disabled(! $canSave) style="border:0;padding:0;margin:0;min-width:0;display:grid;gap:14px">
      <div class="fgrid">
        <div class="field">
          <label for="code">Code</label>
          <input class="inp @error('code') err @enderror" id="code" name="code" value="{{ $v('code') }}" maxlength="20" style="text-transform:uppercase" placeholder="{{ $isNew ? 'Blank = '.$nextCode : '' }}">
          <span class="hint txt">Leave blank for the next number.</span>
          @include('partials.ferr', ['name' => 'code'])
        </div>
        <div class="field">
          <label for="name">Name<span class="req">*</span></label>
          <input class="inp @error('name') err @enderror" id="name" name="name" value="{{ $v('name') }}" maxlength="100" required>
          @include('partials.ferr', ['name' => 'name'])
        </div>
        <div class="field">
          <label for="role">Role<span class="req">*</span></label>
          <select class="inp @error('role') err @enderror" id="role" name="role" aria-required="true">
            @foreach (config('amc.employee_roles') as $o)<option @selected($v('role') === $o)>{{ $o }}</option>@endforeach
          </select>
          @include('partials.ferr', ['name' => 'role'])
        </div>
        <div class="field">
          <label for="mobile">Mobile</label>
          <input class="inp num @error('mobile') err @enderror" id="mobile" name="mobile" value="{{ $v('mobile') }}" maxlength="10" inputmode="numeric" placeholder="10 digits">
          @include('partials.ferr', ['name' => 'mobile'])
        </div>
        <div class="field">
          <label for="status">Status<span class="req">*</span></label>
          <select class="inp @error('status') err @enderror" id="status" name="status" aria-required="true">
            @foreach (['Active', 'Inactive'] as $o)<option @selected($v('status') === $o)>{{ $o }}</option>@endforeach
          </select>
          <span class="hint txt">Inactive employees are not offered on new contracts.</span>
          @include('partials.ferr', ['name' => 'status'])
        </div>
        <div class="field">
          <label for="photo">Photo</label>
          <div style="display:flex;gap:10px;align-items:center;min-width:0">
            <span id="photoPreview" style="display:inline-flex;flex:none">@include('amc.partials.photo', ['e' => $isNew ? null : $e, 'lg' => true])</span>
            <input class="inp @error('photo') err @enderror" id="photo" type="file" name="photo" accept=".jpg,.jpeg,.png" data-max-kb="{{ \App\Http\Requests\Amc\AmcEmployeeRequest::PHOTO_KB }}" data-photo-preview="#photoPreview" style="padding-top:6px;min-width:0">
          </div>
          <span class="hint">JPG or PNG, max 5 MB. {{ $e->photo_path ? 'A new photo replaces the current one.' : '' }}</span>
          @include('partials.ferr', ['name' => 'photo'])
        </div>
      </div>
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>Fields marked * are mandatory. Code and name cannot repeat. Operation managers and gardeners are picked on the AMC Tracker page by their role.</span></div>
      </fieldset>
    </div>
  </div>
  @include('crm.partials.audit', ['m' => $e])
  <div class="formfoot">
    @if (! $isNew && $u->can('amc-employee.delete'))
      <button class="btn sec" type="submit" form="empDelForm" style="color:var(--error-700)"><svg class="ico"><use href="#i-x"/></svg>Delete employee</button>
    @endif
    <span class="sp"></span>
    @if ($canSave)<button class="btn {{ $isNew ? 'add' : 'pri' }}" type="submit"><svg class="ico"><use href="#i-{{ $isNew ? 'plus' : 'check' }}"/></svg>Submit</button>@endif
  </div>
</form>
@if (! $isNew && $u->can('amc-employee.delete'))
  <form id="empDelForm" method="POST" action="{{ route('amc.employees.destroy', $e) }}" data-confirm="Delete {{ $e->label() }}? An employee on a client, a contract or a Return & Replace entry cannot be deleted.">@csrf @method('DELETE')</form>
@endif
@include('crm.partials.history', ['history' => $history])
@endsection
