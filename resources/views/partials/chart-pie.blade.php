{{-- Pie chart from App\Support\Charts::pie(): the drawing plus a legend with the numbers, so it reads without colour. --}}
@if (! $c['total'])
  <div class="empty">No enquiries this year yet.</div>
@else
<div class="piewrap">
  <svg viewBox="0 0 100 100" class="pie" role="img" aria-label="{{ $label }}">
    @foreach ($c['slices'] as $s)
      @if ($s['path'])<path d="{{ $s['path'] }}" class="{{ $s['class'] }}"><title>{{ $s['label'] }}: {{ \App\Support\Money::indian($s['value']) }} ({{ $s['percent'] }}%)</title></path>
      @else<circle cx="50" cy="50" r="46" class="{{ $s['class'] }}"><title>{{ $s['label'] }}: {{ \App\Support\Money::indian($s['value']) }} (100%)</title></circle>@endif
    @endforeach
  </svg>
  <ul class="chlegend">
    @foreach ($c['slices'] as $s)
      <li><span class="sw {{ $s['class'] }}" aria-hidden="true"></span><span class="ln">{{ $s['label'] }}</span><span class="num">{{ \App\Support\Money::indian($s['value']) }} · {{ $s['percent'] }}%</span></li>
    @endforeach
  </ul>
</div>
@endif
