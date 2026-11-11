<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
  @page{margin:24px 26px}
  body{font-family:"DejaVu Sans",sans-serif;font-size:8.5px;color:#1f2937}
  h1{font-size:16px;margin:0 0 4px;color:#065f46}
  .filters{color:#6b7280;margin-bottom:10px}
  .filters b{color:#374151}
  table{width:100%;border-collapse:collapse}
  th{background:#ecfdf5;color:#065f46;text-align:left;font-size:8px;text-transform:uppercase;padding:5px 4px;border-bottom:1px solid #a7f3d0}
  td{padding:4px;border-bottom:1px solid #e5e7eb;vertical-align:top}
  .empty{padding:20px;text-align:center;color:#6b7280}
  .foot{margin-top:10px;font-size:8px;color:#6b7280}
</style>
</head>
<body>
<h1>{{ $title }}</h1>
<div class="filters">@foreach ($filters as $k => $v)<b>{{ $k }}:</b> {{ $v }} &nbsp; @endforeach</div>
@if (! empty($note))<div class="filters"><b>Note:</b> {{ $note }}</div>@endif
{{-- Rows in tables of 50, each with the header: dompdf lays out one table at a time, which about halves its memory (C9). --}}
@php($chunks = array_chunk(is_array($rows) ? $rows : collect($rows)->all(), 50))
@forelse ($chunks as $chunk)
<table>
  <thead><tr>@foreach ($headers as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
  <tbody>
  @foreach ($chunk as $row)
    <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
  @endforeach
  </tbody>
</table>
@empty
<table>
  <thead><tr>@foreach ($headers as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
  <tbody><tr><td colspan="{{ count($headers) }}" class="empty">Nothing matches these options.</td></tr></tbody>
</table>
@endforelse
<div class="foot">{{ count($rows) }} row(s). Made on {{ now()->format(config('nursery.datetime_format')) }}.</div>
</body>
</html>
