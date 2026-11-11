{{-- A page of an optional part that an administrator switched off (Admin › Page setup › Optional parts). $part: its name. --}}
@extends('errors.layout')
@section('code', 'Off')
@section('title', $part.' is switched off')
@section('message', 'An administrator has switched this part off for everyone. Saved entries are kept and show again when it is switched back on in Admin › Page setup.')
@section('links')
  @auth
    @can('enquiry.view')<a class="btn sec" href="{{ route('crm.enquiries.index') }}">Go to Enquiry</a>@endcan
  @endauth
@endsection
