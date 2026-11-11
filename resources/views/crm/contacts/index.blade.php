{{-- CRM > Contacts: every client's contacts / POCs, paged, searched and sorted on the server (rule P1). --}}
@php
  $title = $client ? 'Contacts of '.$client->name : 'Contacts';
  $crumbs = array_values(array_filter([['CRM', route('crm.enquiries.index')], ['Contacts', $client ? route('crm.contacts.index') : null], $client ? [$client->name, null] : null]));
@endphp
@extends('layouts.app', ['title' => $title, 'crumbs' => $crumbs])

@section('content')
@php($cps = app(\App\Services\PageSetupService::class)->form('contact'))
<div class="pagehd">
  <h1>{{ $title }}</h1>
  <span class="badge b-neu">{{ \App\Support\Money::indian($rows->total()) }}</span>
  <span class="sp"></span>
  @can('contact.add')<a class="btn add" href="{{ route('crm.contacts.create', $clientParams) }}"><svg class="ico"><use href="#i-plus"/></svg>Add contact</a>@endcan
</div>

@include('partials.listbar', ['route' => 'crm.contacts.index', 'q' => $q, 'keep' => $clientParams,
  'placeholder' => 'Name, contact ID, mobile or client name', 'sorts' => ['no' => 'Contact ID', 'name' => 'Name'], 'sort' => $sort, 'dir' => $dir,
  'clearUrl' => route('crm.contacts.index'), 'clearLabel' => $client ? 'All contacts' : null])

<div class="tablecard">
  <table class="atable rtable fit">
    <thead><tr>
      @include('partials.sorth', ['label' => 'Contact ID', 'key' => 'no'])
      @include('partials.sorth', ['label' => 'Name', 'key' => 'name'])
      <th>Company / Client</th>@if ($cps->show('designation'))<th class="c3">Designation</th>@endif @if ($cps->show('mobile'))<th>Mobile</th>@endif @if ($cps->show('email'))<th class="c2">Email</th>@endif @if ($cps->show('roles'))<th class="c3">Roles</th>@endif
    </tr></thead>
    <tbody>
    @forelse ($rows as $c)
      <tr class="clk" onclick="location.href='{{ route('crm.contacts.show', $c) }}'">
        <td data-label="Contact ID" class="num">{{ $c->contact_no }}</td>
        <td data-label="Name" class="wide"><a class="rowlink" href="{{ route('crm.contacts.show', $c) }}"><b>{{ $c->name }}</b></a></td>
        <td data-label="Company / Client">{{ $c->clientName() }}</td>
        @if ($cps->show('designation'))<td data-label="Designation" class="c3">{{ $c->designation }}</td>@endif
        @if ($cps->show('mobile'))<td data-label="Mobile" class="num">{{ $c->mobile }}</td>@endif
        @if ($cps->show('email'))<td data-label="Email" class="c2">{{ $c->email }}</td>@endif
        @if ($cps->show('roles'))<td data-label="Roles" class="c3">{{ $c->roleNames() }}</td>@endif
      </tr>
    @empty
      <tr><td colspan="7"><div class="empty">{{ $q !== '' ? 'No contacts match this search.' : 'No contacts yet.' }}</div></td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@include('partials.pager', ['p' => $rows])
@endsection
