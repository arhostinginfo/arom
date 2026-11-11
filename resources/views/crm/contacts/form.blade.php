{{-- CRM > Contacts: add or edit one contact / POC of a client. Which fields show and which are mandatory comes from Admin > Page setup ($ps). --}}
@php
  $isNew = ! $contact->exists;
  $u = auth()->user();
  $canSave = $isNew ? $u->can('contact.add') : $u->can('contact.edit');
  $v = fn ($f) => old($f, $contact->{$f});
  $clientParams = array_filter(['prospect' => $contact->prospect_id, 'party' => $contact->party_id]);
  $phones = ['mobile' => 'Mobile', 'whatsapp' => 'WhatsApp', 'alternate_no' => 'Alternate number'];
  $title = $isNew ? 'Add contact' : $contact->name;
@endphp
@extends('layouts.app', ['title' => $title, 'crumbs' => [['CRM', route('crm.enquiries.index')], ['Contacts', route('crm.contacts.index')], [$isNew ? 'Add' : $contact->contact_no, null]]])

@section('content')
<div class="pagehd">
  <h1>{{ $title }}</h1>
  @unless ($isNew)<span class="badge b-neu">{{ $contact->contact_no }}</span>@endunless
  <span class="sp"></span>
  @if ($origin)<a class="btn gh sm" href="{{ $origin['url'] }}#contacts"><svg class="ico"><use href="#i-left"/></svg>{{ $origin['label'] }}</a>@endif
  <a class="btn gh sm" href="{{ route('crm.contacts.index', $clientParams) }}"><svg class="ico"><use href="#i-list"/></svg>{{ $clientParams ? 'All contacts of this client' : 'Contact list' }}</a>
</div>

@if ($errors->any() && ! $errors->has('delete'))
  <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>Check the fields marked in red.</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
@endif
@if (! $canSave && $contact->exists)
  <div class="alert info"><svg class="ico"><use href="#i-lock"/></svg><span>You can view this contact. Your role cannot change contacts.</span></div>
@endif

<form method="POST" action="{{ $isNew ? route('crm.contacts.store') : route('crm.contacts.update', $contact) }}" novalidate id="contactForm" data-dirty-guard data-saving>
  @csrf
  @unless ($isNew) @method('PUT') @endunless
  @if ($origin)<input type="hidden" name="from" value="{{ $origin['from'] }}">@endif
  <div class="sectcard">
    <div class="sh">
      <h2>{{ $isNew ? 'New contact' : 'Contact details' }}</h2><span class="sp"></span>
      <div class="autono"><span>Contact ID <b>{{ $contact->contact_no ?? 'on save' }}</b></span></div>
    </div>
    <div class="sb">
      <fieldset @disabled(! $canSave) style="border:0;padding:0;margin:0;min-width:0;display:grid;gap:14px">
      <div class="fgrid">
        <div class="field" role="group" aria-labelledby="clientLbl" style="grid-column:1/-1">
          <span class="lbl" id="clientLbl" style="font-size:13px;font-weight:500">Company / Client<span class="req">*</span></span>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px">
            <div style="display:grid;gap:4px">
              <label for="prospect_id" class="cap muted">Prospect (new client)</label>
              <div class="picker" data-picker="field" data-url="{{ route('lookup.prospects') }}" data-clears="#party_id" data-placeholder="Type a prospect name or phone">
                <select class="inp @error('prospect_id') err @enderror" id="prospect_id" name="prospect_id">
                  <option value="">Select</option>
                  @foreach ($prospects as $id => $name)<option value="{{ $id }}" @selected((string) $v('prospect_id') === (string) $id)>{{ $name }}</option>@endforeach
                </select>
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
        <div class="field">
          <label for="name">Name<span class="req">*</span></label>
          <input class="inp @error('name') err @enderror" id="name" name="name" value="{{ $v('name') }}" maxlength="100" required autocomplete="off">
          @include('partials.ferr', ['name' => 'name'])
        </div>
        @foreach (['designation' => 'Designation', 'department' => 'Department'] as $f => $label)
          @continue(! $ps->show($f))
          <div class="field">
            <label for="{{ $f }}">{{ $label }}{{ $ps->star($f) }}</label>
            <input class="inp @error($f) err @enderror" id="{{ $f }}" name="{{ $f }}" value="{{ $v($f) }}" maxlength="100" @required($ps->req($f))>
            @include('partials.ferr', ['name' => $f])
          </div>
        @endforeach
        @foreach ($phones as $f => $label)
          @continue(! $ps->show($f))
          <div class="field">
            <label for="{{ $f }}">{{ $label }}{{ $ps->star($f) }}</label>
            <input class="inp @error($f) err @enderror" id="{{ $f }}" name="{{ $f }}" value="{{ $v($f) }}" inputmode="tel" maxlength="20" placeholder="+91 98220 00001" @required($ps->req($f))>
            @include('partials.ferr', ['name' => $f])
          </div>
        @endforeach
        @if ($ps->show('email'))
        <div class="field">
          <label for="email">Email{{ $ps->star('email') }}</label>
          <input class="inp @error('email') err @enderror" id="email" name="email" type="email" inputmode="email" value="{{ $v('email') }}" maxlength="191" @required($ps->req('email'))>
          @include('partials.ferr', ['name' => 'email'])
        </div>
        @endif
        @if ($ps->show('preferred_communication'))
        <div class="field">
          <label for="preferred_communication">Preferred communication{{ $ps->star('preferred_communication') }}</label>
          <select class="inp @error('preferred_communication') err @enderror" id="preferred_communication" name="preferred_communication" @if ($ps->req('preferred_communication')) aria-required="true" @endif>
            <option value="">Select</option>
            @foreach (config('nursery.contact_channels') as $ch)<option @selected($v('preferred_communication') === $ch)>{{ $ch }}</option>@endforeach
          </select>
          @include('partials.ferr', ['name' => 'preferred_communication'])
        </div>
        @endif
        @if ($ps->show('roles'))
        <div class="field" role="group" aria-labelledby="rolesLbl" style="grid-column:1/-1">
          <span class="lbl" id="rolesLbl" style="font-size:13px;font-weight:500">Contact roles</span>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            @foreach (\App\Models\Contact::ROLES as $f => $label)
              <label class="chk"><input type="hidden" name="{{ $f }}" value="0"><input type="checkbox" name="{{ $f }}" value="1" @checked((bool) $v($f))> {{ $label }}</label>
            @endforeach
          </div>
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
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>{{ $ps->mandatory() }} The Contact ID is given when the contact is saved.</span></div>
      </fieldset>
      @include('crm.partials.audit', ['m' => $contact])
    </div>
  </div>

  {{-- Thu 22/10/2026: no Delete contact button on this page (the destroy route stays). --}}
  <div class="formfoot">
    <span class="sp"></span>
    @if ($canSave)<button class="btn pri" type="submit"><svg class="ico"><use href="#i-check"/></svg>{{ $isNew ? 'Save contact' : 'Save changes' }}</button>@endif
  </div>
</form>

@include('crm.partials.history', ['history' => $history])
@endsection
