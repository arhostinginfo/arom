{{-- Grouped bar chart from App\Support\Charts::bars(). The numbers are also in a table next to it. --}}
<div class="barwrap">
  <svg viewBox="0 0 {{ $c['width'] }} {{ $c['height'] }}" class="bars" role="img" aria-label="{{ $label }}" preserveAspectRatio="xMidYMid meet">
    @foreach ($c['ticks'] as $t)
      <line x1="{{ $c['left'] }}" x2="{{ $c['width'] }}" y1="{{ $t['y'] }}" y2="{{ $t['y'] }}" class="grid"/>
      <text x="{{ $c['left'] - 6 }}" y="{{ $t['y'] + 4 }}" class="ax" text-anchor="end">{{ $t['label'] }}</text>
    @endforeach
    @foreach ($c['bars'] as $b)
      @if ($b['h'] > 0)<rect x="{{ $b['x'] }}" y="{{ $b['y'] }}" width="{{ $b['w'] }}" height="{{ $b['h'] }}" rx="2" class="{{ $b['class'] }}"><title>{{ $b['title'] }}</title></rect>@endif
    @endforeach
    @foreach ($c['labels'] as $l)
      <text x="{{ $l['x'] }}" y="{{ $c['base'] + 18 }}" class="ax" text-anchor="middle">{{ $l['label'] }}</text>
    @endforeach
  </svg>
  <ul class="chlegend inline">
    @foreach ($c['legend'] as $s)<li><span class="sw {{ $s['class'] }}" aria-hidden="true"></span>{{ $s['label'] }}</li>@endforeach
  </ul>
</div>
