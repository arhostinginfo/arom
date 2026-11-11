@php
  $isNew = ! $prospect->exists;
  $u = auth()->user();
  $canSave = $isNew ? $u->can('prospect.add') : $u->can('prospect.edit');
  $title = $isNew ? 'Prospect' : $prospect->name;
  $options = $entries->map(fn ($e) => ['id' => $e->id, 'url' => route('crm.prospects.show', ['prospect' => $e, 'q' => $q ?: null]), 'label' => $e->name.' · '.$e->phone_1, 'on' => $prospect->exists && $e->id === $prospect->id])->all();
  $buttons = array_values(array_filter([
    $u->can('prospect.add') ? ['open' => '#importLayer', 'icon' => 'upload', 'label' => 'Import', 'kind' => 'import'] : null,
    ['href' => route('crm.prospects.export'), 'icon' => 'download', 'label' => 'Download CSV', 'kind' => 'csv'],
  ]));
@endphp
@extends('layouts.app', ['title' => $title, 'crumbs' => [['CRM', route('crm.quotations.index')], ['Prospect', null]]])

@section('content')
<div class="pagehd">
  <h1>Prospect</h1>
  <span class="badge b-neu">{{ $total }} {{ \Illuminate\Support\Str::plural('prospect', $total) }}</span>
  <span class="sp"></span>
 
</div>

@include('crm.partials.entrybar', [
  'action' => route('crm.prospects.index'), 'lookupUrl' => route('crm.prospects.lookup'), 'q' => $q, 'options' => $options, 'current' => $prospect->exists,
  'newLabel' => 'New prospect', 'placeholder' => 'Name, contact, phone or email',
  'addUrl' => $u->can('prospect.add') ? route('crm.prospects.create') : null, 'buttons' => $buttons,
])

@if ($errors->any() && ! $errors->has('delete'))
  <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>{{ $errors->has('file') ? 'The file was not imported. Nothing was saved.' : 'Check the fields marked in red.' }}</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
@endif
@if (! $canSave && ($prospect->exists || ! $u->can('prospect.add')))
  <div class="alert info"><svg class="ico"><use href="#i-lock"/></svg><span>{{ $prospect->exists ? 'You can view this prospect. Your role cannot change prospects.' : 'There are no prospects yet, and your role cannot add them.' }}</span></div>
@endif

<form method="POST" action="{{ $isNew ? route('crm.prospects.store') : route('crm.prospects.update', $prospect) }}" novalidate id="prospectForm" data-dirty-guard data-saving>
  @csrf
  @unless ($isNew) @method('PUT') @endunless
  <div class="sectcard">
    <div class="sh"><h2>{{ $isNew ? 'New prospect' : 'Prospect details' }}</h2><span class="sp"></span><span class="cap muted">Fields marked <span class="req">*</span> are required</span></div>
    <div class="sb">
      <fieldset @disabled(! $canSave) style="border:0;padding:0;margin:0;min-width:0;display:grid;gap:14px">
        @include('crm.prospects.fields', ['p' => $prospect])
      </fieldset>
      @include('crm.partials.audit', ['m' => $prospect])
    </div>
  </div>

  {{-- Thu 22/10/2026: no Delete prospect button on this page (the destroy route stays). --}}
  <div class="formfoot">
    <span class="sp"></span>
    @if ($canSave)<button class="btn pri" type="submit"><svg class="ico"><use href="#i-check"/></svg>{{ $isNew ? 'Save prospect' : 'Save changes' }}</button>@endif
  </div>
</form>

@include('crm.partials.contacts', ['box' => $contactBox])

@include('crm.partials.history', ['history' => $history])

@can('prospect.add')
  {{-- maxKb: the upload size of Admin > Config, as ImportRequest checks for this route (22/10/2026). --}}
  @include('crm.partials.import', [
    'id' => 'importLayer', 'title' => 'Import prospects', 'action' => route('crm.prospects.import'), 'template' => route('crm.prospects.template'),
    'maxKb' => \App\Support\UploadLimit::kb(),
    'help' => '<p>One row per prospect, with the same columns as Download CSV: <b>Prospect name</b>, <b>Domestic / Export</b> and <b>Contact no 1</b> are required. Every row is added as a new prospect.</p><p>The whole file is checked first. If any row has a mistake, nothing is saved and the rows to fix are listed.</p>',
  ])
@endcan
@endsection

@push('scripts')
<script src="{{ asset('js/crm.js') }}?v={{ filemtime(public_path('js/crm.js')) }}"></script>
@endpush
