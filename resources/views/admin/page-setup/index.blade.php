@php($canSave = auth()->user()->can('page-setup.edit'))
@extends('layouts.app', ['title' => 'Page setup', 'crumbs' => [['Admin', route('admin.users.index')], ['Page setup', null]]])

@section('content')
<div class="pagehd"><h1>Page setup</h1></div>

@if ($errors->any())
  <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>Nothing was saved.</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
@endif

{{-- Optional parts of the CRM flow, for the whole installation. --}}
<form method="POST" action="{{ route('admin.page-setup.modules') }}" id="modulesForm" data-dirty-guard data-saving>
  @csrf @method('PUT')
  <div class="sectcard">
    <div class="sh"><h2 id="partsHd">Optional parts</h2></div>
    <div class="sb" style="padding-top:6px">
      <div class="tablecard">
        <table class="atable rtable" aria-labelledby="partsHd">
          <thead><tr><th class="c" style="width:64px">On</th><th>Part and what it covers</th></tr></thead>
          <tbody>
          @foreach (config('nursery.modules') as $key => $m)
            <tr>
              <td data-label="On" class="c">
                {{-- Cost details also needs Approval · Edit; without it the tick is locked and its value is sent unchanged. --}}
                @php($locked = ! $canSave || ($key === 'costing' && ! auth()->user()->can('approval.edit')))
                <input type="hidden" name="modules[{{ $key }}]" value="{{ $locked && $modules[$key] ? 1 : 0 }}">
                <input type="checkbox" id="mod_{{ $key }}" name="modules[{{ $key }}]" value="1" @checked($modules[$key]) @disabled($locked) aria-describedby="mod_{{ $key }}_h"
                  data-was="{{ $modules[$key] ? 1 : 0 }}" data-label="{{ $m['label'] }}" data-off="{{ $m['off'] }}">
              </td>
              <td data-label="Part" class="wide" style="white-space:normal">
                <label for="mod_{{ $key }}"><b>{{ $m['label'] }}</b></label>
                <div class="cap muted" id="mod_{{ $key }}_h">{{ $m['hint'] }}<br>When off: {{ $m['off'] }}</div>
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
      <div class="ruleh" style="margin-top:10px"><svg class="ico"><use href="#i-info"/></svg><span>A part that is off is left out of the menu, Access rights, the enquiry workflow, the quotation page, alerts and reports, and its pages cannot be opened. Entries already saved are kept and show again when the part is switched back on.</span></div>
      @if ($canSave)
        <div class="formfoot" style="margin-top:10px"><span class="sp"></span><button class="btn pri" type="submit"><svg class="ico"><use href="#i-check"/></svg>Save optional parts</button></div>
      @endif
    </div>
  </div>
</form>

@foreach ($groups as $group => $pages)
  <h2 class="sech" id="ps-{{ \Illuminate\Support\Str::slug($group) }}" style="margin:18px 0 8px;font-size:16px">{{ $group }}</h2>
  <div class="tablecard">
    <table class="atable rtable" aria-labelledby="ps-{{ \Illuminate\Support\Str::slug($group) }}">
      <thead><tr><th>Page</th><th>Fields shown</th><th>Mandatory</th><th><span class="sr">Actions</span></th></tr></thead>
      <tbody>
      @foreach ($pages as $key => $p)
        @if (isset($p['off']))
          <tr>
            <td data-label="Page" class="wide muted"><b>{{ $p['label'] }}</b></td>
            <td colspan="3" class="muted" style="white-space:normal">Switched off with {{ $p['off'] }} in Optional parts above</td>
          </tr>
          @continue
        @endif
        <tr>
          <td data-label="Page" class="wide"><b>{{ $p['label'] }}</b></td>
          <td data-label="Fields shown" class="num">{{ $p['shown'] }} of {{ $p['total'] }}</td>
          <td data-label="Mandatory" class="num">{{ $p['required'] }}</td>
          <td class="r"><a class="btn sec sm" href="{{ route('admin.page-setup.edit', $key) }}" aria-label="Configure {{ $p['label'] }}"><svg class="ico"><use href="#i-cog"/></svg>Configure</a></td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
@endforeach
<div class="ruleh" style="margin-top:10px"><svg class="ico"><use href="#i-info"/></svg><span>Configure sets which fields of a page show and which are mandatory. Sales, Purchase and Accounts pages are added here as those modules are built.</span></div>
@endsection

@push('scripts')
<script>
/* Switching a part off asks first, naming each part and what everyone loses (UX review M1). Runs on the form
   itself, before the page-wide save handlers, so Cancel leaves the page as it was. */
(function(){
  var f=document.getElementById('modulesForm');if(!f)return;
  f.addEventListener('submit',function(e){
    var off=[].filter.call(f.querySelectorAll('input[type=checkbox][data-was="1"]'),function(c){return !c.checked});
    if(!off.length)return;
    var msg='Switch off '+off.map(function(c){return c.dataset.label}).join(' and ')+'?\n\n'
      +off.map(function(c){return c.dataset.off}).join('\n')
      +'\n\nThis applies to every user until it is switched back on. Nothing is deleted.';
    if(!confirm(msg)){e.preventDefault();e.stopImmediatePropagation()}
  });
})();
</script>
@endpush
