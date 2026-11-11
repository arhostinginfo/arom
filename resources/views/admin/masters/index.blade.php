@extends('layouts.app', ['title' => 'Masters', 'crumbs' => [['Admin', route('admin.users.index')], ['Masters', null]]])

@section('content')
<div class="pagehd"><h1>Masters</h1></div>

<div class="lbl" style="margin:4px 0 8px">CRM</div>
<div class="mastergrid">
  @foreach ($lists as $key => $cfg)
    <a class="sectcard spot mcardl" href="{{ route('admin.masters.show', $key) }}">
      <svg class="ico" style="color:var(--primary-600)"><use href="#i-db"/></svg><b>{{ $cfg['title'] }}</b>
      <span class="badge b-neu">{{ $counts[$key] }}</span><span style="flex:1"></span><svg class="ico" style="color:var(--text-muted)"><use href="#i-right"/></svg>
    </a>
  @endforeach
  <a class="sectcard spot mcardl" href="{{ route('admin.employees.index') }}">
    <svg class="ico" style="color:var(--primary-600)"><use href="#i-db"/></svg><b>Employee master</b>
    <span class="badge b-neu">{{ $employees }}</span><span style="flex:1"></span><svg class="ico" style="color:var(--text-muted)"><use href="#i-right"/></svg>
  </a>
</div>

<div class="lbl" style="margin:18px 0 8px">Sales and items</div>
<div class="mastergrid">
  <a class="sectcard spot mcardl" href="{{ route('admin.parties.index') }}">
    <svg class="ico" style="color:var(--primary-600)"><use href="#i-db"/></svg><b>Party master</b>
    <span class="badge b-neu">{{ $parties }}</span><span style="flex:1"></span><svg class="ico" style="color:var(--text-muted)"><use href="#i-right"/></svg>
  </a>
  <a class="sectcard spot mcardl" href="{{ route('admin.items.index') }}">
    <svg class="ico" style="color:var(--primary-600)"><use href="#i-db"/></svg><b>Item master</b>
    <span class="badge b-neu">{{ $items }}</span><span style="flex:1"></span><svg class="ico" style="color:var(--text-muted)"><use href="#i-right"/></svg>
  </a>
  @foreach ($salesLists as $key => $cfg)
    <a class="sectcard spot mcardl" href="{{ route('admin.sales-masters.show', $key) }}">
      <svg class="ico" style="color:var(--primary-600)"><use href="#i-db"/></svg><b>{{ $cfg['title'] }}</b>
      <span class="badge b-neu">{{ $salesCounts[$key] }}</span><span style="flex:1"></span><svg class="ico" style="color:var(--text-muted)"><use href="#i-right"/></svg>
    </a>
  @endforeach
  <a class="sectcard spot mcardl" href="{{ route('admin.company.edit') }}">
    <svg class="ico" style="color:var(--primary-600)"><use href="#i-db"/></svg><b>Company</b>
    <span style="flex:1"></span><svg class="ico" style="color:var(--text-muted)"><use href="#i-right"/></svg>
  </a>
</div>

<div class="lbl" style="margin:18px 0 8px">Other modules <span class="cap muted">(sample screens until those modules are built)</span></div>
<div class="mastergrid">
  @foreach ($samples as $name => $i)
    <a class="sectcard spot mcardl" href="{{ route('app.page', ['page' => 'master', 'arg' => $i]) }}">
      <svg class="ico" style="color:var(--text-muted)"><use href="#i-db"/></svg><span>{{ $name }}</span><span style="flex:1"></span><svg class="ico" style="color:var(--text-muted)"><use href="#i-right"/></svg>
    </a>
  @endforeach
</div>
<div class="ruleh" style="margin-top:12px"><svg class="ico"><use href="#i-info"/></svg><span>Masters are not financial-year wise. An entry in use cannot be deleted; make it inactive to hide it from dropdowns.</span></div>
@endsection
