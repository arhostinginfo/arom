{{-- AMC › Masters (22/11/2026): the AMC masters in one place, each with its row count. Only those the user may view. --}}
@extends('layouts.app', ['title' => 'AMC masters'])

@section('content')
@include('amc.partials.assets')
<div class="pagehd">
  <h1>AMC masters</h1>
  <span class="sp"></span>
</div>

<div class="amcmasters">
  @foreach ($cards as $c)
    <a class="card amcmcard" href="{{ $c['url'] }}" data-master="{{ $c['key'] }}">
      <span class="t">{{ $c['title'] }}</span>
      <span class="badge b-neu">{{ \App\Support\Money::indian($c['count']) }}</span>
      <span class="d">{{ $c['hint'] }}</span>
    </a>
  @endforeach
</div>
@endsection
