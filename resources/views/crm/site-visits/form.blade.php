@php
  $isNew = ! $visit->exists;
  $u = auth()->user();
  $canSave = $isNew ? $u->can('site-visit.add') : $u->can('site-visit.edit');
  $df = config('nursery.date_format');
  $options = $entries->map(fn ($e) => ['id' => $e->id, 'url' => route('crm.site-visits.show', ['site_visit' => $e, 'q' => $q ?: null]),
    'label' => $e->visit_at->format(config('nursery.datetime_format')).' · '.($e->enquiry?->enquiry_no ?? '').' · '.($e->enquiry?->clientName() ?? ''), 'on' => $visit->exists && $e->id === $visit->id])->all();
  $buttons = [['href' => route('crm.site-visits.list'), 'icon' => 'list', 'label' => 'Site visit list', 'nav' => true]];
  $v = fn ($f) => old($f, $visit->{$f});
  $text = [
    'area_measurements' => ['Area / measurements', 255, 'e.g. Lobby 30 × 20 ft'],
    'floors' => ['Floors', 100, 'e.g. Ground + 2'],
    'indoor_outdoor_area' => ['Indoor / outdoor area', 255, 'e.g. Indoor 600 sq ft, terrace 1,200 sq ft'],
    'lighting_condition' => ['Lighting condition', 150, 'e.g. Bright indirect light'],
    'water_availability' => ['Water availability', 150, 'e.g. Tap on each floor'],
    'access_restrictions' => ['Access restrictions', 255, 'e.g. Service lift only after 7 pm'],
  ];
  $long = ['existing_plants' => 'Existing plants', 'existing_planters' => 'Existing planters', 'customer_requirement' => 'Customer requirement', 'remarks' => 'Remarks'];
  // Admin > Page setup: which fields show and which are mandatory.
  $ps = app(\App\Services\PageSetupService::class)->form('site-visit');
@endphp
@extends('layouts.app', ['title' => $isNew ? 'Site visit' : 'Site visit '.$visit->visit_at->format($df), 'crumbs' => [['CRM', route('crm.enquiries.index')], ['Site visit', null]]])

@section('content')
<div class="pagehd">
  <h1>Site visit</h1>
  @if ($visit->exists)<span class="badge {{ $visit->status === 'Done' ? 'b-ok' : 'b-warn' }}">{{ $visit->status }}</span>@endif
  <span class="sp"></span>
</div>

@include('crm.partials.entrybar', [
  'action' => route('crm.site-visits.index'), 'lookupUrl' => route('crm.site-visits.lookup'), 'q' => $q, 'options' => $options, 'current' => $visit->exists,
  'newLabel' => 'New site visit', 'placeholder' => 'Enquiry no, client name or contact no',
  'addUrl' => $u->can('site-visit.add') ? route('crm.site-visits.create') : null, 'buttons' => $buttons,
])

@if ($errors->any() && ! $errors->has('delete'))
  <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>Check the fields marked in red.</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
@endif
@if (! $canSave && ($visit->exists || ! $u->can('site-visit.add')))
  <div class="alert info"><svg class="ico"><use href="#i-lock"/></svg><span>{{ $visit->exists ? 'You can view this site visit. Your role cannot change site visits.' : 'There are no site visits yet, and your role cannot add them.' }}</span></div>
@endif

<form method="POST" action="{{ $isNew ? route('crm.site-visits.store') : route('crm.site-visits.update', $visit) }}" novalidate id="visitForm" data-dirty-guard data-saving>
  @csrf
  @unless ($isNew) @method('PUT') @endunless
  <div class="sectcard">
    <div class="sh"><h2>Site visit / requirement gathering</h2><span class="sp"></span>
      @if ($visit->enquiry && $u->can('enquiry.view'))<a class="btn gh sm" href="{{ route('crm.enquiries.show', $visit->enquiry_id) }}"><svg class="ico"><use href="#i-link"/></svg>{{ $visit->enquiry->enquiry_no }}</a>@endif
    </div>
    <div class="sb">
      <fieldset @disabled(! $canSave) style="border:0;padding:0;margin:0;min-width:0;display:grid;gap:14px">
      <div class="fgrid">
        <div class="field" style="grid-column:1/-1">
          <label for="enquiry_id">Client (enquiry)<span class="req">*</span></label>
          <div class="picker" data-picker="field" data-url="{{ route('crm.enquiries.lookup') }}" data-placeholder="Type an enquiry no or client name">
            <select class="inp @error('enquiry_id') err @enderror" id="enquiry_id" name="enquiry_id" aria-required="true">
              <option value="">Select</option>
              @foreach ($enquiryOptions as $id => $label)<option value="{{ $id }}" @selected((string) $v('enquiry_id') === (string) $id)>{{ $label }}</option>@endforeach
            </select>
          </div>
          @include('partials.ferr', ['name' => 'enquiry_id'])
        </div>
        <div class="field">
          <label for="visit_date">Visit date<span class="req">*</span></label>
          <input class="inp num @error('visit_date') err @enderror" id="visit_date" name="visit_date" value="{{ old('visit_date', $visit->visit_at?->format($df)) }}" placeholder="dd/mm/yyyy" inputmode="numeric" required>
          @include('partials.ferr', ['name' => 'visit_date'])
        </div>
        @if ($ps->show('visit_time'))
        <div class="field">
          <label for="visit_time">Visit time{{ $ps->star('visit_time') }}</label>
          <input class="inp num @error('visit_time') err @enderror" id="visit_time" name="visit_time" value="{{ old('visit_time', $visit->visit_at?->format('H:i')) }}" placeholder="HH:MM" inputmode="numeric" @required($ps->req('visit_time'))>
          @include('partials.ferr', ['name' => 'visit_time'])
        </div>
        @endif
        <div class="field">
          <label for="status">Status<span class="req">*</span></label>
          <select class="inp @error('status') err @enderror" id="status" name="status" required>
            @foreach (\App\Models\SiteVisit::STATUSES as $s)<option value="{{ $s }}" @selected(($v('status') ?: \App\Models\SiteVisit::STATUSES[0]) === $s)>{{ $s }}</option>@endforeach
          </select>
          @include('partials.ferr', ['name' => 'status'])
        </div>
        @if ($ps->show('poc'))
        <div class="field">
          <label for="poc">POC (person on site){{ $ps->star('poc') }}</label>
          <input class="inp @error('poc') err @enderror" id="poc" name="poc" value="{{ $v('poc') }}" maxlength="150" @required($ps->req('poc'))>
          @include('partials.ferr', ['name' => 'poc'])
          @if (($ph = $visit?->enquiry?->clientPhone()))<span class="hint txt">Phone {{ $ph }} (from the Prospect)</span>@endif

        </div>
        @endif
        @if ($ps->show('technical_employee_id'))
        <div class="field">
          <label for="technical_employee_id">Technical person{{ $ps->star('technical_employee_id') }}</label>
          <div class="picker" data-picker="field" data-url="{{ route('lookup.employees') }}" data-placeholder="Type an employee name">
            <select class="inp @error('technical_employee_id') err @enderror" id="technical_employee_id" name="technical_employee_id" @if ($ps->req('technical_employee_id')) aria-required="true" @endif>
              <option value="">Select</option>
              @foreach ($employees as $id => $name)<option value="{{ $id }}" @selected((string) $v('technical_employee_id') === (string) $id)>{{ $name }}</option>@endforeach
            </select>
          </div>
          @include('partials.ferr', ['name' => 'technical_employee_id'])
        </div>
        @endif
        @if ($ps->show('site_address'))
        <div class="field" style="grid-column:1/-1">
          <label for="site_address">Site address{{ $ps->star('site_address') }}</label>
          <textarea class="inp @error('site_address') err @enderror" id="site_address" name="site_address" rows="2" maxlength="1000" style="height:auto;padding-top:8px" @required($ps->req('site_address'))>{{ $v('site_address') }}</textarea>
          @include('partials.ferr', ['name' => 'site_address'])
        </div>
        @endif
        @foreach ($text as $f => [$label, $max, $ph])
          @continue(! $ps->show($f))
          <div class="field">
            <label for="{{ $f }}">{{ $label }}{{ $ps->star($f) }}</label>
            <input class="inp @error($f) err @enderror" id="{{ $f }}" name="{{ $f }}" value="{{ $v($f) }}" maxlength="{{ $max }}" placeholder="{{ $ph }}" @required($ps->req($f))>
            @include('partials.ferr', ['name' => $f])
          </div>
        @endforeach
        @foreach ($long as $f => $label)
          @continue(! $ps->show($f))
          <div class="field" style="grid-column:1/-1">
            <label for="{{ $f }}">{{ $label }}{{ $ps->star($f) }}</label>
            <textarea class="inp @error($f) err @enderror" id="{{ $f }}" name="{{ $f }}" rows="2" maxlength="2000" style="height:auto;padding-top:8px" @required($ps->req($f))>{{ $v($f) }}</textarea>
            @include('partials.ferr', ['name' => $f])
          </div>
        @endforeach
        @if ($ps->show('next_action'))
        <div class="field" style="grid-column:1/-1">
          <label for="next_action">Next action{{ $ps->star('next_action') }}</label>
          <input class="inp @error('next_action') err @enderror" id="next_action" name="next_action" value="{{ $v('next_action') }}" maxlength="255" placeholder="e.g. Prepare the quotation" @required($ps->req('next_action'))>
          @include('partials.ferr', ['name' => 'next_action'])
        </div>
        @endif
      </div>
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>{{ $ps->mandatory() }} Add site photos below after saving.</span></div>
      </fieldset>
      @include('crm.partials.audit', ['m' => $visit])
    </div>
  </div>

  {{-- Thu 22/10/2026: no Delete site visit button on this page (the destroy route stays). --}}
  <div class="formfoot">
    <span class="sp"></span>
    @if ($visit->exists && $visit->enquiry_id && $u->can('quotation.add'))
      <a class="btn sec" href="{{ route('crm.quotations.create', ['enquiry' => $visit->enquiry_id]) }}"><svg class="ico"><use href="#i-file"/></svg>Make quotation</a>
    @endif
    @if ($canSave)<button class="btn pri" type="submit"><svg class="ico"><use href="#i-check"/></svg>{{ $isNew ? 'Save site visit' : 'Save changes' }}</button>@endif
  </div>
</form>

@if ($visit->exists)
  @include('crm.partials.documents', ['type' => 'site_visit', 'record' => $visit, 'docs' => $docs, 'canEdit' => $u->can('site-visit.edit'),
    'title' => 'Site photos', 'hint' => 'On a phone, the file button can take a photo with the camera.', 'camera' => true, 'deletable' => false])
@endif

@include('crm.partials.history', ['history' => $history])
@endsection

@push('scripts')
<script src="{{ asset('js/crm.js') }}?v={{ filemtime(public_path('js/crm.js')) }}"></script>
@endpush
