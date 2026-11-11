@php
  $isNew = ! $enquiry->exists;
  $u = auth()->user();
  $canSave = $isNew ? $u->can('enquiry.add') : $u->can('enquiry.edit');
  $df = config('nursery.date_format');
  $title = $isNew ? 'Enquiry' : $enquiry->enquiry_no;
  $options = $entries->map(fn ($e) => ['id' => $e->id, 'url' => route('crm.enquiries.show', ['enquiry' => $e, 'q' => $q ?: null]), 'label' => $e->enquiry_no.' · '.($e->clientName() ?: 'No client'), 'on' => $enquiry->exists && $e->id === $enquiry->id])->all();
  $buttons = [['href' => route('crm.enquiries.list'), 'icon' => 'list', 'label' => 'Enquiry list', 'nav' => true]];
  $v = fn ($f) => old($f, $enquiry->{$f} instanceof \BackedEnum ? $enquiry->{$f}->value : $enquiry->{$f});
  $d = fn ($f) => old($f, $enquiry->{$f}?->format($df));
  $show = fn ($f) => $setup[$f]['visible'];
  $req = fn ($f) => $setup[$f]['required'];
  $star = fn ($f) => $setup[$f]['required'] ? '<span class="req">*</span>' : '';
@endphp
@extends('layouts.app', ['title' => $title, 'crumbs' => [['CRM', route('crm.enquiries.index')], ['Enquiry', null]]])

@section('content')
<div class="pagehd">
  <h1>Enquiry</h1>
  @if ($enquiry->exists)
    <span class="badge {{ $enquiry->status->badgeClass() }}">{{ $enquiry->status->value }}</span>
    @if ($enquiry->lead_rating)<span class="badge {{ $enquiry->lead_rating->badgeClass() }}">{{ $enquiry->lead_rating->value }}</span>@endif
    @if ($enquiry->isDue())<span class="badge b-err"><svg class="ico"><use href="#i-alert"/></svg>Follow-up due</span>@endif
  @endif
  <span class="sp"></span>
  <span class="cap muted">Unit: {{ $unit?->name ?? 'none' }}</span>
</div>

@include('crm.partials.entrybar', [
  'action' => route('crm.enquiries.index'), 'lookupUrl' => route('crm.enquiries.lookup'), 'q' => $q, 'options' => $options, 'current' => $enquiry->exists,
  'newLabel' => 'New enquiry', 'placeholder' => 'Enquiry no (e.g. 12), client name or contact no',
  'addUrl' => $u->can('enquiry.add') ? route('crm.enquiries.create') : null, 'buttons' => $buttons,
])

@if ($errors->any() && ! $errors->has('delete'))
  <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>Check the fields marked in red.</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
@endif
@if (! $canSave && ($enquiry->exists || ! $u->can('enquiry.add')))
  <div class="alert info"><svg class="ico"><use href="#i-lock"/></svg><span>{{ $enquiry->exists ? 'You can view this enquiry. Your role cannot change enquiries.' : 'There are no enquiries in this unit yet, and your role cannot add them.' }}</span></div>
@endif

@if ($flow)
  @include('crm.partials.flow', ['steps' => $flow])
@endif

<form method="POST" action="{{ $isNew ? route('crm.enquiries.store') : route('crm.enquiries.update', $enquiry) }}" novalidate id="enquiryForm" data-dirty-guard data-saving>
  @csrf
  @unless ($isNew) @method('PUT') @endunless
  <div class="sectcard">
    <div class="sh">
      <h2>{{ $isNew ? 'New enquiry' : 'Enquiry details' }}</h2><span class="sp"></span>
      <div class="autono"><span>Enquiry ID <b>{{ $enquiry->enquiry_no ?? 'on save' }}</b></span></div>
    </div>
    <div class="sb">
      <fieldset @disabled(! $canSave) style="border:0;padding:0;margin:0;min-width:0;display:grid;gap:14px">
      <div class="fgrid">
        <div class="field">
          <label for="enquiry_date">Enquiry date<span class="req">*</span></label>
          <input class="inp num @error('enquiry_date') err @enderror" id="enquiry_date" name="enquiry_date" value="{{ $d('enquiry_date') }}" placeholder="dd/mm/yyyy" inputmode="numeric" required>
          @include('partials.ferr', ['name' => 'enquiry_date'])
        </div>
        @if ($show('lead_source_id'))
        <div class="field">
          <label for="lead_source_id">Source{!! $star('lead_source_id') !!}</label>
          <select class="inp @error('lead_source_id') err @enderror" id="lead_source_id" name="lead_source_id" @required($req('lead_source_id'))>
            <option value="">Select</option>
            @foreach ($sources as $s)<option value="{{ $s->id }}" @selected((string) $v('lead_source_id') === (string) $s->id)>{{ $s->name }}</option>@endforeach
          </select>
          @include('partials.ferr', ['name' => 'lead_source_id'])
        </div>
        @endif
        @if ($show('sub_source'))
        <div class="field">
          <label for="sub_source">Sub-source / Reference{!! $star('sub_source') !!}</label>
          <input class="inp @error('sub_source') err @enderror" id="sub_source" name="sub_source" value="{{ $v('sub_source') }}" maxlength="150" @required($req('sub_source'))>
          @include('partials.ferr', ['name' => 'sub_source'])
        </div>
        @endif
        @if ($show('competitor_details'))
        <div class="field">
          <label for="competitor_details">Competitor details{!! $star('competitor_details') !!}</label>
          <input class="inp @error('competitor_details') err @enderror" id="competitor_details" name="competitor_details" value="{{ $v('competitor_details') }}" maxlength="255" @required($req('competitor_details'))>
          @include('partials.ferr', ['name' => 'competitor_details'])
        </div>
        @endif
        @if ($show('prospect_type'))
        <div class="field">
          <label for="prospect_type">Prospect type{!! $star('prospect_type') !!}</label>
          <select class="inp @error('prospect_type') err @enderror" id="prospect_type" name="prospect_type" @required($req('prospect_type'))>
            <option value="">Select</option>
            @foreach ($prospectTypes as $t)<option value="{{ $t }}" @selected($v('prospect_type') === $t)>{{ $t }}</option>@endforeach
          </select>
          @include('partials.ferr', ['name' => 'prospect_type'])
        </div>
        @endif

        <div class="field" role="group" aria-labelledby="clientLbl" style="grid-column:1/-1">
          <span class="lbl" id="clientLbl" style="font-size:13px;font-weight:500">Company / Client name<span class="req">*</span></span>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px">
            <div style="display:grid;gap:4px">
              <label for="prospect_id" class="cap muted">Prospect (new client)</label>
              <div style="display:flex;gap:6px">
                <div class="picker" data-picker="field" data-url="{{ route('lookup.prospects') }}" data-clears="#party_id" data-placeholder="Type a prospect name or phone" style="flex:1;min-width:0">
                  <select class="inp @error('prospect_id') err @enderror" id="prospect_id" data-client-info="{{ route('crm.enquiries.client-info') }}" name="prospect_id">
                    <option value="">Select</option>
                    @foreach ($prospects as $id => $name)<option value="{{ $id }}" @selected((string) $v('prospect_id') === (string) $id)>{{ $name }}</option>@endforeach
                  </select>
                </div>
                @if ($canSave && $u->can('prospect.add'))<button class="btn add" type="button" data-open="#prospectLayer"><svg class="ico"><use href="#i-plus"/></svg>Add</button>@endif
              </div>
            </div>
            <div style="display:grid;gap:4px">
              <label for="party_id" class="cap muted">Existing customer</label>
              <div class="picker" data-picker="field" data-url="{{ route('lookup.customers') }}" data-clears="#prospect_id" data-placeholder="{{ $hasCustomers ? 'Type a customer name' : 'No customers yet' }}">
                <select class="inp @error('party_id') err @enderror" id="party_id" name="party_id">
                  <option value="">Select</option>
                  @foreach ($customers as $id => $name)<option value="{{ $id }}" @selected((string) $v('party_id') === (string) $id)>{{ $name }}</option>@endforeach
                </select>
              </div>
            </div>
          </div>
          @include('partials.ferr', ['name' => 'prospect_id'])
          @include('partials.ferr', ['name' => 'party_id'])
        </div>

        @if ($show('industry'))
        <div class="field">
          <label for="industry">Industry{!! $star('industry') !!}</label>
          <input class="inp @error('industry') err @enderror" id="industry" name="industry" value="{{ $v('industry') }}" maxlength="100" @required($req('industry'))>
          @include('partials.ferr', ['name' => 'industry'])
        </div>
        @endif
        @if ($show('industry_type_id'))
        <div class="field">
          <label for="industry_type_id">Industry type{!! $star('industry_type_id') !!}</label>
          <select class="inp @error('industry_type_id') err @enderror" id="industry_type_id" name="industry_type_id" @required($req('industry_type_id'))>
            <option value="">Select</option>
            @foreach ($industryTypes as $s)<option value="{{ $s->id }}" @selected((string) $v('industry_type_id') === (string) $s->id)>{{ $s->name }}</option>@endforeach
          </select>
          @include('partials.ferr', ['name' => 'industry_type_id'])
        </div>
        @endif
        @if ($show('city_id'))
        <div class="field">
          <label for="city_id">City{!! $star('city_id') !!}</label>
          {{-- City keeps growing, so it is a type-ahead searched on the server (UX review M7). --}}
          <div class="picker" data-picker="field" data-url="{{ route('lookup.cities') }}" data-placeholder="Type a city">
            <select class="inp @error('city_id') err @enderror" id="city_id" name="city_id" aria-required="{{ $req('city_id') ? 'true' : 'false' }}">
              <option value="">Select</option>
              @foreach ($cities as $id => $name)<option value="{{ $id }}" @selected((string) $v('city_id') === (string) $id)>{{ $name }}</option>@endforeach
            </select>
          </div>
          @include('partials.ferr', ['name' => 'city_id'])
        </div>
        @endif
        @if ($show('website'))
        <div class="field">
          <label for="website">Website{!! $star('website') !!}</label>
          <input class="inp @error('website') err @enderror" id="website" name="website" value="{{ $v('website') }}" maxlength="191" inputmode="url" placeholder="www.example.com" @required($req('website'))>
          @include('partials.ferr', ['name' => 'website'])
        </div>
        @endif
        @if ($show('address'))
        <div class="field" style="grid-column:1/-1">
          <label for="address">Address{!! $star('address') !!}</label>
          <textarea class="inp @error('address') err @enderror" id="address" name="address" rows="2" maxlength="1000" style="height:auto;padding-top:8px" @required($req('address'))>{{ $v('address') }}</textarea>
          @include('partials.ferr', ['name' => 'address'])
        </div>
        @endif
        @if ($show('contact'))
        {{-- A Prospect already holds its contact person and phone, so the pair is hidden (and not sent) while one is
             picked; the enquiry takes them from the Prospect when saved (22/10/2026). --}}
        @php($fromProspect = (string) $v('prospect_id') !== '')
        <div class="contactpair" id="contactPair" @if ($fromProspect) hidden @endif>
        <div class="field">
          <label for="contact_name">Contact name{!! $star('contact') !!}</label>
          <input class="inp @error('contact_name') err @enderror" id="contact_name" name="contact_name" value="{{ $v('contact_name') }}" maxlength="100" autocomplete="off" @required($req('contact')) @disabled($fromProspect)>
          @include('partials.ferr', ['name' => 'contact_name'])
        </div>
        <div class="field">
          <label for="contact_no">Contact no{!! $star('contact') !!}</label>
          <input class="inp @error('contact_no') err @enderror" id="contact_no" name="contact_no" value="{{ $v('contact_no') }}" maxlength="20" inputmode="tel" placeholder="+91 98220 00001" @required($req('contact')) @disabled($fromProspect)>
          @include('partials.ferr', ['name' => 'contact_no'])
        </div>
        </div>
        @endif
        @if ($show('service_category_id'))
        <div class="field">
          <label for="service_category_id">Service type{!! $star('service_category_id') !!}</label>
          <select class="inp @error('service_category_id') err @enderror" id="service_category_id" name="service_category_id" @required($req('service_category_id'))>
            <option value="">Select</option>
            @foreach ($categories as $s)<option value="{{ $s->id }}" @selected((string) $v('service_category_id') === (string) $s->id)>{{ $s->name }}</option>@endforeach
          </select>
          @include('partials.ferr', ['name' => 'service_category_id'])
        </div>
        @endif
        @if ($show('approx_requirement'))
        <div class="field">
          <label for="approx_requirement">Approx. requirement{!! $star('approx_requirement') !!}</label>
          <input class="inp @error('approx_requirement') err @enderror" id="approx_requirement" name="approx_requirement" value="{{ $v('approx_requirement') }}" maxlength="255" placeholder="e.g. 40 plants, 2 floors" @required($req('approx_requirement'))>
          @include('partials.ferr', ['name' => 'approx_requirement'])
        </div>
        @endif
        @if ($show('estimated_value'))
        <div class="field">
          <label for="estimated_value">Estimated value (₹){!! $star('estimated_value') !!}</label>
          <input class="inp num @error('estimated_value') err @enderror" id="estimated_value" name="estimated_value" value="{{ old('estimated_value', $enquiry->estimated_value !== null ? rtrim(rtrim($enquiry->estimated_value, '0'), '.') : '') }}" inputmode="decimal" @required($req('estimated_value'))>
          @include('partials.ferr', ['name' => 'estimated_value'])
        </div>
        @endif
        @if ($show('expected_closing_date'))
        <div class="field">
          <label for="expected_closing_date">Expected closing date{!! $star('expected_closing_date') !!}</label>
          <input class="inp num @error('expected_closing_date') err @enderror" id="expected_closing_date" name="expected_closing_date" value="{{ $d('expected_closing_date') }}" placeholder="dd/mm/yyyy" inputmode="numeric" @required($req('expected_closing_date'))>
          @include('partials.ferr', ['name' => 'expected_closing_date'])
        </div>
        @endif
        @if ($show('lead_rating'))
        <div class="field">
          <label for="lead_rating">Lead rating{!! $star('lead_rating') !!}</label>
          <select class="inp @error('lead_rating') err @enderror" id="lead_rating" name="lead_rating" @required($req('lead_rating'))>
            <option value="">Select</option>
            @foreach ($ratings as $t)<option value="{{ $t }}" @selected($v('lead_rating') === $t)>{{ $t }}</option>@endforeach
          </select>
          @include('partials.ferr', ['name' => 'lead_rating'])
        </div>
        @endif
        @if ($show('assigned_to'))
        <div class="field">
          <label for="assigned_to">Lead assigned to{!! $star('assigned_to') !!}</label>
          <div class="picker" data-picker="field" data-url="{{ route('lookup.employees') }}" data-placeholder="Type an employee name">
            <select class="inp @error('assigned_to') err @enderror" id="assigned_to" name="assigned_to" aria-required="{{ $req('assigned_to') ? 'true' : 'false' }}">
              <option value="">Select</option>
              @foreach ($employees as $id => $name)<option value="{{ $id }}" @selected((string) $v('assigned_to') === (string) $id)>{{ $name }}</option>@endforeach
            </select>
          </div>
          @if ($employees->isEmpty())<span class="hint txt">No employees yet. Add them in Admin › Masters › Employee master.</span>@endif
          @include('partials.ferr', ['name' => 'assigned_to'])
        </div>
        @endif
        @if ($show('next_action'))
        <div class="field">
          <label for="next_action">Next action{!! $star('next_action') !!}</label>
          <input class="inp @error('next_action') err @enderror" id="next_action" name="next_action" value="{{ $v('next_action') }}" maxlength="255" placeholder="e.g. Call to discuss the requirement" @required($req('next_action'))>
          @include('partials.ferr', ['name' => 'next_action'])
        </div>
        <div class="field">
          <label for="next_action_date">Next action date{!! $star('next_action') !!}</label>
          <input class="inp num @error('next_action_date') err @enderror {{ $enquiry->isDue() ? 'due' : '' }}" id="next_action_date" name="next_action_date" value="{{ $d('next_action_date') }}" placeholder="dd/mm/yyyy" inputmode="numeric" @required($req('next_action'))>
          @if ($enquiry->isDue())<span class="ferr"><svg class="ico"><use href="#i-alert"/></svg>Due {{ $enquiry->next_action_date->isToday() ? 'today' : 'since '.$enquiry->next_action_date->format($df) }}</span>@endif
          @include('partials.ferr', ['name' => 'next_action_date'])
        </div>
        @endif
        @if ($show('remarks'))
        <div class="field" style="grid-column:1/-1">
          <label for="remarks">Remarks{!! $star('remarks') !!}</label>
          <textarea class="inp @error('remarks') err @enderror" id="remarks" name="remarks" rows="3" maxlength="2000" style="height:auto;padding-top:8px" @required($req('remarks'))>{{ $v('remarks') }}</textarea>
          @include('partials.ferr', ['name' => 'remarks'])
        </div>
        @endif
      </div>
      </fieldset>
      @include('crm.partials.audit', ['m' => $enquiry])
    </div>
  </div>

  {{-- Thu 22/10/2026: no Delete enquiry button on this page (the destroy route stays). --}}
  <div class="formfoot">
    <span class="sp"></span>
    @if ($canSave)<button class="btn pri" type="submit"><svg class="ico"><use href="#i-check"/></svg>{{ $isNew ? 'Save enquiry' : 'Save changes' }}</button>@endif
  </div>
</form>

@if ($enquiry->exists)
  @include('crm.enquiries.followups', ['enquiry' => $enquiry, 'activities' => $activities, 'canEdit' => $u->can('enquiry.edit')])
  @include('crm.partials.contacts', ['box' => $contactBox])
  @include('crm.partials.documents', ['type' => 'enquiry', 'record' => $enquiry, 'docs' => $docs, 'canEdit' => $u->can('enquiry.edit'),
    'title' => 'Enquiry documents', 'hint' => 'Enquiry documents, visiting cards and requirement emails of this lead.', 'camera' => true, 'deletable' => false])
@endif

@include('crm.partials.history', ['history' => $history])

@if ($canSave && $u->can('prospect.add'))
  @include('crm.partials.prospect-drawer')
@endif
@endsection

@push('scripts')
<script src="{{ asset('js/crm.js') }}?v={{ filemtime(public_path('js/crm.js')) }}"></script>
@endpush
