{{-- AMC › Customer PO / AMC: one contract with its files, the people's photos and the overdue / renewal alerts. --}}
@php
  $isNew = ! $c->exists;
  $u = auth()->user();
  $canSave = $isNew ? $u->can('amc-contract.add') : $u->can('amc-contract.edit');
  $canFiles = ! $isNew && $u->can('amc-contract.edit');
  $canDelete = ! $isNew && $u->can('amc-contract.delete');
  $df = config('nursery.date_format');
  $v = fn ($f) => old($f, $c->{$f});
  $d = fn ($f) => old($f, $c->{$f}?->format($df));
  $colour = $isNew ? null : $c->colour();
  $left = $isNew ? null : $c->daysLeft();
  $personUrl = fn ($role) => route('amc.lookup.employees', ['role' => $role]);
@endphp
@extends('layouts.app', ['title' => $isNew ? 'AMC Tracker' : $c->number()])

@section('content')
@include('amc.partials.assets')
<div class="pagehd">
  <h1>{{ $isNew ? 'AMC Tracker' : $c->number().' · '.$c->client?->name }}</h1>
  @if ($colour)<span class="badge {{ \App\Models\Amc\AmcContract::COLOURS[$colour][1] }}">{{ \App\Models\Amc\AmcContract::COLOURS[$colour][0] }}</span>@endif
  <span class="sp"></span>
  @if (! $isNew && $u->can('amc-contract.add'))<a class="btn add sm" href="{{ route('amc.contract.create') }}"><svg class="ico"><use href="#i-plus"/></svg>Add new</a>@endif
  <a class="btn gh sm" href="{{ route('reports.amc.report', ['in' => 'amc']) }}"><svg class="ico"><use href="#i-list"/></svg>AMC Report</a>
</div>

@include('amc.partials.errors')

@if (! $isNew && $c->isOverdue())
  {{-- Red pop up each time an overdue Active contract is opened (client requirement); Close or Escape hides it. --}}
  <div class="overlay amcpop" id="amcOverduePop" data-autoopen>
    <div class="scrim" data-x></div>
    <div class="modal" role="alertdialog" aria-modal="true" aria-labelledby="amcOverdueT" aria-describedby="amcOverdueD">
      <div class="mh"><svg class="ico"><use href="#i-alarm"/></svg><h3 id="amcOverdueT">AMC overdue</h3></div>
      <div class="mb"><p id="amcOverdueD" class="small" style="margin:0">{{ $c->number() }} of <b>{{ $c->client?->name }}</b> ended on <b>{{ $c->end_date->format($df) }}</b>, {{ abs($left) }} day(s) ago, and is still Active. Renew it with a new contract, or make it Inactive.</p></div>
      <div class="mf" style="display:flex;gap:8px;justify-content:flex-end;padding:12px 20px 18px">
        @if ($u->can('amc-contract.add'))<a class="btn gh" href="{{ route('amc.contract.create', ['client' => $c->amc_client_id]) }}">New contract</a>@endif
        <button class="btn pri" type="button" data-x>Close</button>
      </div>
    </div>
  </div>
  <div class="alert err" role="alert"><svg class="ico"><use href="#i-alarm"/></svg><span><b>Overdue.</b> The end date {{ $c->end_date->format($df) }} has passed ({{ abs($left) }} day(s) ago) and the contract is still Active.</span></div>
@elseif (! $isNew && $c->needsRenewal())
  <div class="alert w" role="status"><svg class="ico"><use href="#i-clock"/></svg><span><b>Renewal due.</b> This contract ends on {{ $c->end_date->format($df) }}, {{ $left === 0 ? 'today' : 'in '.$left.' day(s)' }}. Plan the renewal with the customer.</span></div>
@endif

<form method="POST" action="{{ $isNew ? route('amc.contract.store') : route('amc.contract.update', $c) }}" novalidate data-dirty-guard data-saving id="amcContractForm">
  @csrf
  @unless ($isNew) @method('PUT') @endunless
  <div class="sectcard">
    <div class="sh"><h2>Contract</h2></div>
    <div class="sb">
      <fieldset @disabled(! $canSave) style="border:0;padding:0;margin:0;min-width:0;display:grid;gap:14px">
      <div class="fgrid">
        <div class="field" style="grid-column:1/-1">
          <label for="amc_client_id">Customer<span class="req">*</span></label>
          <div class="picker" data-picker="field" data-url="{{ route('amc.lookup.clients') }}" data-placeholder="Type a customer name">
            <select class="inp @error('amc_client_id') err @enderror" id="amc_client_id" name="amc_client_id" aria-required="true" data-info-url="{{ route('amc.clients.info', ['client' => '__ID__']) }}">
              <option value="">Select</option>
              @foreach ($clients as $id => $name)<option value="{{ $id }}" @selected((string) $v('amc_client_id') === (string) $id)>{{ $name }}</option>@endforeach
            </select>
          </div>
          <span class="hint txt">From the AMC Client Inventory.</span>
          @include('partials.ferr', ['name' => 'amc_client_id'])
        </div>
        <div class="field amcdates">
          <label for="start_date">AMC start date<span class="req">*</span></label>
          <input class="inp num @error('start_date') err @enderror" id="start_date" name="start_date" value="{{ $d('start_date') }}" placeholder="dd/mm/yyyy" inputmode="numeric" required>
          @include('partials.ferr', ['name' => 'start_date'])
        </div>
        <div class="field amcdates">
          <label for="end_date">AMC end date<span class="req">*</span></label>
          <input class="inp num @error('end_date') err @enderror" id="end_date" name="end_date" value="{{ $d('end_date') }}" placeholder="dd/mm/yyyy" inputmode="numeric" required>
          @if ($left !== null && $c->status === 'Active')<span class="hint txt">{{ $left < 0 ? abs($left).' day(s) overdue' : ($left === 0 ? 'Ends today' : $left.' day(s) left') }}</span>@endif
          @include('partials.ferr', ['name' => 'end_date'])
        </div>
        <div class="amcclient" id="amcClientInfo" style="grid-column:1/-1" aria-live="polite">
          @if ($client)
            <div class="kv"><span>Business segment: <b>{{ $client->vertical?->name ?? '–' }}</b></span></div>
            @if ($client->lines->isEmpty())
              <div class="cap muted">No location lines in the AMC Client Inventory.</div>
            @else
              <div class="tablecard"><table class="atable rtable"><thead><tr><th>Location 1</th><th>Location 2</th><th>Planter</th><th class="r">Planter qty</th><th>Plant</th><th class="r">Plant qty</th></tr></thead><tbody>
                @foreach ($client->lines as $l)
                  <tr><td data-label="Location 1">{{ $l->location_1 }}</td><td data-label="Location 2">{{ $l->location_2 }}</td><td data-label="Planter">{{ $l->planter?->name }}</td>
                    <td data-label="Planter qty" class="r num">{{ $l->planter_qty }}</td><td data-label="Plant">{{ $l->plant?->label() }}</td><td data-label="Plant qty" class="r num">{{ $l->plant_qty }}</td></tr>
                @endforeach
              </tbody></table></div>
            @endif
          @else
            <div class="cap muted">Choose a customer to see its locations, planters and plants.</div>
          @endif
        </div>
        <div class="field">
          <label for="amc_type_id">AMC type<span class="req">*</span></label>
          <select class="inp @error('amc_type_id') err @enderror" id="amc_type_id" name="amc_type_id" aria-required="true">
            <option value="">Select</option>
            @foreach ($types as $o)<option value="{{ $o->id }}" @selected((string) $v('amc_type_id') === (string) $o->id)>{{ $o->name }}</option>@endforeach
          </select>
          @include('partials.ferr', ['name' => 'amc_type_id'])
        </div>
        <div class="field">
          <label for="billing_cycle">Billing cycle<span class="req">*</span></label>
          <select class="inp @error('billing_cycle') err @enderror" id="billing_cycle" name="billing_cycle" aria-required="true">
            @foreach (config('amc.billing_cycles') as $o)<option @selected($v('billing_cycle') === $o)>{{ $o }}</option>@endforeach
          </select>
          @include('partials.ferr', ['name' => 'billing_cycle'])
        </div>
        <div class="field">
          <label for="amount">AMC amount (₹)<span class="req">*</span></label>
          <input class="inp num @error('amount') err @enderror" id="amount" name="amount" value="{{ old('amount', $c->amount !== null ? rtrim(rtrim((string) $c->amount, '0'), '.') : '') }}" inputmode="decimal" placeholder="e.g. 5000" required>
          @if (! $isNew)<span class="hint txt">{{ \App\Support\Money::rupees($c->amount, 2) }}</span>@endif
          @include('partials.ferr', ['name' => 'amount'])
        </div>
        <div class="field">
          <label for="status">AMC status<span class="req">*</span></label>
          <select class="inp @error('status') err @enderror" id="status" name="status" aria-required="true">
            @foreach (['Active', 'Inactive'] as $o)<option @selected($v('status') === $o)>{{ $o }}</option>@endforeach
          </select>
          @include('partials.ferr', ['name' => 'status'])
        </div>
        <div class="field" style="grid-column:1/-1">
          <label for="visit_plan">Visit plan / maintenance frequency</label>
          <input class="inp @error('visit_plan') err @enderror" id="visit_plan" name="visit_plan" value="{{ $v('visit_plan') }}" maxlength="255" placeholder="e.g. Weekly visit every Monday">
          @include('partials.ferr', ['name' => 'visit_plan'])
        </div>
        <div class="field">
          <label for="operation_manager_id">Operation manager</label>
          <div class="picker" data-picker="field" data-url="{{ $personUrl('Operation Manager') }}" data-placeholder="Type a name or code">
            <select class="inp @error('operation_manager_id') err @enderror" id="operation_manager_id" name="operation_manager_id">
              <option value="">None</option>
              @foreach ($managers as $id => $label)<option value="{{ $id }}" @selected((string) $v('operation_manager_id') === (string) $id)>{{ $label }}</option>@endforeach
            </select>
          </div>
          <span class="hint txt">AMC employees whose role is Operation Manager.</span>
          @include('partials.ferr', ['name' => 'operation_manager_id'])
        </div>
        <div class="field">
          <label for="gardener_id">Gardener</label>
          <div class="picker" data-picker="field" data-url="{{ $personUrl('Gardener') }}" data-placeholder="Type a name or code">
            <select class="inp @error('gardener_id') err @enderror" id="gardener_id" name="gardener_id">
              <option value="">None</option>
              @foreach ($gardeners as $id => $label)<option value="{{ $id }}" @selected((string) $v('gardener_id') === (string) $id)>{{ $label }}</option>@endforeach
            </select>
          </div>
          <span class="hint txt">AMC employees whose role is Gardener. Picking the customer fills in the client's gardener when this box is empty.</span>
          @include('partials.ferr', ['name' => 'gardener_id'])
        </div>
      </div>
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>Fields marked * are mandatory. Dates as dd/mm/yyyy; the end date must be on or after the start date. On the AMC Report a contract is Green while Active, Red when its end date is within {{ config('amc.due_days') }} days, Black once the end date has passed, Grey when Inactive.</span></div>
      </fieldset>
    </div>
  </div>
  @include('crm.partials.audit', ['m' => $c])
  <div class="formfoot">
    @if ($canDelete)
      <button class="btn sec" type="submit" form="amcDelForm" style="color:var(--error-700)"><svg class="ico"><use href="#i-x"/></svg>Delete contract</button>
    @endif
    <span class="sp"></span>
    @if ($canSave)<button class="btn {{ $isNew ? 'add' : 'pri' }}" type="submit"><svg class="ico"><use href="#i-{{ $isNew ? 'plus' : 'check' }}"/></svg>Submit</button>@endif
  </div>
</form>
@if ($canDelete)
  <form id="amcDelForm" method="POST" action="{{ route('amc.contract.destroy', $c) }}" data-confirm="Delete {{ $c->number() }} and all its files? This cannot be undone.">@csrf @method('DELETE')</form>
@endif

@unless ($isNew)
  {{-- Photos of the operation manager and gardener: a new photo replaces the one in the AMC Employee master. --}}
  <div class="sectcard">
    <div class="sh"><h2>Operation manager and gardener</h2></div>
    <div class="sb">
      <div class="amcpeople">
        @foreach (['Operation manager' => $c->operationManager, 'Gardener' => $c->gardener] as $role => $person)
          <div class="amcperson">
            @include('amc.partials.photo', ['e' => $person, 'lg' => true])
            <div>
              <div><span class="cap muted">{{ $role }}</span><br><b>{{ $person?->label() ?? 'Not chosen' }}</b>@if ($person?->mobile)<span class="cap muted"> · {{ $person->mobile }}</span>@endif</div>
              @if ($person && $u->can('amc-contract.edit'))
                <form method="POST" action="{{ route('amc.employees.photo', $person) }}" enctype="multipart/form-data" data-saving>
                  @csrf
                  <input class="inp" type="file" name="photo" accept=".jpg,.jpeg,.png" data-max-kb="{{ \App\Http\Requests\Amc\AmcEmployeeRequest::PHOTO_KB }}" required aria-label="New photo of {{ $person->name }}" style="padding-top:6px">
                  <button class="btn sec sm" type="submit"><svg class="ico"><use href="#i-upload"/></svg>{{ $person->photo_path ? 'Replace photo' : 'Add photo' }}</button>
                </form>
              @endif
            </div>
          </div>
        @endforeach
      </div>
      @error('photo')<div class="alert err" role="alert" style="margin-top:10px"><svg class="ico"><use href="#i-alert"/></svg><span>{{ $message }}</span></div>@enderror
    </div>
  </div>

  <div class="sectcard" id="files">
    <div class="sh"><h2>Files</h2><span class="badge b-neu">{{ $fileList->flatten()->count() }}</span><span class="sp"></span>
      @if ($u->isAdmin())
        <form method="POST" action="{{ route('amc.contracts.purge') }}" data-confirm="Delete every deployment image (all contracts) uploaded more than 1 year ago? PO, agreement and delivery challan files are kept.">@csrf
          <button class="btn gh sm" type="submit" style="color:var(--error-700)"><svg class="ico"><use href="#i-x"/></svg>Delete deployment images older than 1 year</button>
        </form>
      @endif
    </div>
    <div class="sb">
      @unless ($gd)
        <div class="alert info"><svg class="ico"><use href="#i-info"/></svg><span>Image tools (PHP GD) are not available on this server, so images are kept one by one as uploaded instead of being combined into a collage.</span></div>
      @endunless
      <div class="amcboxes">
        @foreach (config('amc.file_categories') as $cat => $fc)
          @php($list = $fileList->get($cat, collect()))
          @php($images = ! in_array('pdf', $fc['types'], true))
          <div class="amcbox">
            <h3>{{ $fc['label'] }} <span class="badge b-neu">{{ $list->count() }}</span></h3>
            @if ($canFiles)
              <form class="amcup" method="POST" action="{{ route('amc.contract.files.store', $c) }}" enctype="multipart/form-data" data-saving>
                @csrf
                <input type="hidden" name="category" value="{{ $cat }}">
                <input class="inp" type="file" name="files[]" multiple required accept="{{ implode(',', array_map(fn ($t) => '.'.$t, $fc['types'])) }}" data-max-kb="{{ config('amc.max_file_kb') }}" data-max-total-kb="{{ config('amc.max_total_kb') }}" data-max-files="{{ config('amc.max_files') }}" data-total-msg="{{ \App\Http\Requests\Amc\AmcFileRequest::totalLimitMessage() }}" aria-label="Files for {{ $fc['label'] }}" style="padding-top:6px">
                <span class="hint">{{ strtoupper(implode(', ', array_diff($fc['types'], ['jpeg']))) }}, max {{ config('amc.max_file_kb') / 1024 }} MB each, {{ config('amc.max_total_kb') / 1024 }} MB in total, up to {{ config('amc.max_files') }} at a time.@if (in_array('jpg', $fc['types'], true)) {{ $gd ? 'Two or more images chosen together are combined into one collage.' : '' }}@endif @if ($cat === 'deployment') Deleted automatically after 1 year.@endif</span>
                <div><button class="btn add sm" type="submit"><svg class="ico"><use href="#i-upload"/></svg>Upload</button></div>
              </form>
            @endif
            @if ($list->isEmpty())
              <div class="cap muted">No files yet.</div>
            @else
              <ul class="amcfiles">
                @foreach ($list as $f)
                  <li>
                    @if ($f->isImage())<a href="{{ route('amc.contract.files.show', $f) }}" target="_blank" rel="noopener"><img src="{{ route('amc.contract.files.show', $f) }}" alt="" loading="lazy"></a>@else<svg class="ico" aria-hidden="true"><use href="#i-pdf"/></svg>@endif
                    <div class="fn"><a href="{{ route('amc.contract.files.show', $f) }}" target="_blank" rel="noopener">{{ $f->original_name }}</a><div class="cap muted">{{ $f->sizeLabel() }} · {{ $f->uploader?->name ?? 'Unknown' }} · {{ $f->created_at?->format(config('nursery.datetime_format')) }}</div></div>
                    @can('amc-contract.delete')
                      <form method="POST" action="{{ route('amc.contract.files.destroy', $f) }}" data-confirm="Delete {{ $f->original_name }}?">@csrf @method('DELETE')
                        <button class="btn gh icon sm" type="submit" aria-label="Delete {{ $f->original_name }}"><svg class="ico"><use href="#i-x"/></svg></button>
                      </form>
                    @endcan
                  </li>
                @endforeach
              </ul>
            @endif
          </div>
        @endforeach
      </div>
      @if ($errors->hasAny(['files', 'files.*', 'category']))
        <div class="alert err" role="alert" style="margin-top:10px"><svg class="ico"><use href="#i-alert"/></svg><span>{{ $errors->first('files') ?: ($errors->first('files.*') ?: $errors->first('category')) }}</span></div>
      @endif
    </div>
  </div>
@endunless
@include('crm.partials.history', ['history' => $history])
@endsection
