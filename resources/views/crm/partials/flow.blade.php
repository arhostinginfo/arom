{{-- Workflow of this enquiry from lead to PO (CRM changes). $steps from App\Support\CrmFlow. A step with no state yet links to "Add". --}}
<nav class="flowbar" aria-label="Workflow of this enquiry">
  <ol>
    @foreach ($steps as $s)
      <li class="{{ $s['done'] ? 'done' : '' }}">
        <span class="dot" aria-hidden="true">@if ($s['done'])<svg class="ico"><use href="#i-check"/></svg>@else{{ $loop->iteration }}@endif</span>
        <span class="tx"><b>{{ $s['label'] }}</b>
          @if ($s['url'])<a href="{{ $s['url'] }}">{{ $s['state'] ?? 'Add' }}</a>@else<span class="muted">{{ $s['state'] ?? 'Not yet' }}</span>@endif
        </span>
      </li>
    @endforeach
  </ol>
</nav>
