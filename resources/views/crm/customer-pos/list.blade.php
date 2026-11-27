@php
  $df = config('nursery.date_format');
  // Filters carried by the search, sort, pager and CSV (01/11/2026).
  $keep = array_filter(['from' => $from, 'to' => $to], fn ($x) => $x !== null && $x !== '');
  // Separate PO menu removed (02/11/2026): the CRM PO list only (it was po/list.blade.php), with no Type column or filter.
@endphp
@extends('layouts.app', ['title' => 'PO list', 'crumbs' => [['CRM', route('crm.enquiries.index')], ['PO / Agreement', route('crm.customer-pos.index')], ['PO list', null]]])

@section('content')
<div class="pagehd">
  <h1>PO list</h1>
  <span class="badge b-neu">{{ number_format($rows->total()) }}</span>
  <span class="sp"></span>
  <a class="btn sec sm" href="{{ route('crm.customer-pos.export', array_filter(['q' => $q ?: null] + $keep)) }}"><svg class="ico"><use href="#i-download"/></svg>Download CSV</a>
  @can('customer-po.add')<a class="btn add sm" href="{{ route('crm.customer-pos.create') }}"><svg class="ico"><use href="#i-plus"/></svg>New PO</a>@endcan
</div>

{{-- (26/11/2026) a refused Plant deployment upload and wrong search dates each under their own heading --}}
@php($upErr = array_merge($errors->get('file'), $errors->get('category')))
@php($dateErr = array_diff($errors->all(), $upErr))
@if ($upErr)
  <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>Plant deployment details not uploaded.</b><ul>@foreach ($upErr as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
@endif
@if ($dateErr)
  <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>Check the dates.</b><ul>@foreach ($dateErr as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
@endif

@include('partials.listbar', ['route' => 'crm.customer-pos.list', 'label' => 'PO views', 'views' => [], 'view' => 'all', 'q' => $q, 'keep' => $keep,
  'placeholder' => 'PO no, customer or enquiry no', 'sorts' => ['date' => 'PO date', 'no' => 'PO no', 'value' => 'PO value'], 'sort' => $sort, 'dir' => $dir,
  'clearUrl' => route('crm.customer-pos.list'), 'clearLabel' => ($q !== '' || $keep) ? 'Clear' : null])

{{-- PO date range (01/11/2026), applied with the search. --}}
<form class="filterbar" method="GET" action="{{ route('crm.customer-pos.list') }}" aria-label="PO date range" style="margin-bottom:10px">
  @if ($q !== '')<input type="hidden" name="q" value="{{ $q }}">@endif
  <div class="field"><label for="from">From PO date</label><input class="inp num @error('from') err @enderror" id="from" name="from" value="{{ request('from') }}" placeholder="dd/mm/yyyy" inputmode="numeric" style="min-width:120px;width:130px">@include('partials.ferr', ['name' => 'from'])</div>
  <div class="field"><label for="to">To PO date</label><input class="inp num @error('to') err @enderror" id="to" name="to" value="{{ request('to') }}" placeholder="dd/mm/yyyy" inputmode="numeric" style="min-width:120px;width:130px">@include('partials.ferr', ['name' => 'to'])</div>
  <button class="btn sec" type="submit">Apply dates</button>
</form>

<div class="tablecard">
  <table class="atable rtable fit">
    <thead><tr>
      @include('partials.sorth', ['label' => 'PO no', 'key' => 'no'])
      @include('partials.sorth', ['label' => 'PO date', 'key' => 'date', 'cls' => 'c2'])
      <th>Customer</th><th class="c3">Enquiry / quotation</th><th class="c1">Items</th>
      @include('partials.sorth', ['label' => 'PO value', 'key' => 'value'])
      <th>Plant deployment</th>
      <th class="ph"><span class="sr-only">Actions</span></th>
    </tr></thead>
    <tbody>
    @forelse ($rows as $r)
      <tr class="clk" onclick="location.href='{{ route('crm.customer-pos.show', $r) }}'">
        <td data-label="PO no" class="wide"><a class="rowlink" href="{{ route('crm.customer-pos.show', $r) }}"><b>{{ $r->po_no }}</b></a></td>
        <td data-label="PO date" class="num c2">{{ $r->po_date?->format($df) }}</td>
        <td data-label="Customer">{{ $r->clientName() }}</td>
        <td data-label="Enquiry / quotation" class="c3">{{ collect([$r->enquiry?->enquiry_no, $r->quotation ? $r->quotation->quotation_no.' Rev '.$r->quotation->revision_no : null])->filter()->implode(' · ') }}</td>
        <td data-label="Items" class="num c1">{{ $r->lines_count }}</td>
        <td data-label="PO value" class="num r">{{ \App\Support\Money::indian($r->po_value, 2) }}</td>
        {{-- (26/11/2026) Plant deployment details: how many files, and Upload (saved with the PO, listed on its page) --}}
        <td data-label="Plant deployment" onclick="event.stopPropagation()" style="white-space:nowrap">
          @if ($r->deploy_count)<a href="{{ route('crm.customer-pos.show', $r) }}#documents">{{ $r->deploy_count }} file{{ (int) $r->deploy_count === 1 ? '' : 's' }}</a>@endif
          @can('customer-po.edit')<button class="btn gh sm" type="button" data-deploy="{{ route('crm.documents.store', ['type' => 'customer_po', 'id' => $r->id]) }}" data-po="{{ $r->po_no }}"><svg class="ico"><use href="#i-upload"/></svg>Upload</button>@endcan
        </td>
        <td data-label="" class="ph act"><a class="btn sec sm" href="{{ route('crm.customer-pos.show', $r) }}">Open</a></td>
      </tr>
    @empty
      <tr><td colspan="8"><div class="empty">No POs in this view.</div></td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@include('partials.pager', ['p' => $rows])

@can('customer-po.edit')
@php($deployKb = \App\Http\Requests\Crm\DocumentRequest::maxKb('customer_po'))
<div class="overlay" id="deployLayer" hidden>
  <div class="scrim" data-deploy-x></div>
  <form class="modal" role="dialog" aria-modal="true" aria-labelledby="deployLayerT" method="POST" action="" enctype="multipart/form-data" data-saving>
    @csrf
    <input type="hidden" name="category" value="{{ \App\Http\Controllers\Crm\CustomerPoController::DEPLOY }}">
    <div class="mh"><h3 id="deployLayerT">Upload Plant Deployment Details</h3></div>
    <div class="mb">
      <p class="small muted" style="margin:0">PO <b data-deploy-po></b></p>
      <div class="field">
        <label for="deployFile">File<span class="req">*</span></label>
        <input class="inp" id="deployFile" type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt,.eml,.msg" data-max-kb="{{ $deployKb }}" style="padding-top:6px">
        <span class="hint">PDF, image, Word, Excel, CSV, text or email. Max {{ \App\Support\UploadLimit::label($deployKb) }}. Saved with the PO as "Plant deployment details".</span>
      </div>
    </div>
    <div class="mf" style="display:flex;gap:8px;justify-content:flex-end;padding:12px 20px 18px">
      <button class="btn gh" type="button" data-deploy-x>Cancel</button>
      <button class="btn add" type="submit"><svg class="ico"><use href="#i-upload"/></svg>Submit</button>
    </div>
  </form>
</div>
<script>
(function(){
  var layer=document.getElementById('deployLayer');if(!layer)return;
  var form=layer.querySelector('form'),po=layer.querySelector('[data-deploy-po]'),file=document.getElementById('deployFile'),last=null;
  var close=function(){layer.hidden=true;if(last)last.focus()};
  document.querySelectorAll('[data-deploy]').forEach(function(b){b.addEventListener('click',function(e){
    e.stopPropagation();last=b;form.action=b.dataset.deploy;po.textContent=b.dataset.po||'';file.value='';layer.hidden=false;file.focus();
  })});
  layer.querySelectorAll('[data-deploy-x]').forEach(function(x){x.addEventListener('click',close)});
  document.addEventListener('keydown',function(e){if(e.key==='Escape'&&!layer.hidden)close()});
})();
</script>
@endcan
@endsection
