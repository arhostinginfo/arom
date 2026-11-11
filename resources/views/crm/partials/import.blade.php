{{-- Import from a CSV file (OTHER POINTS: import / download CSV on entry pages). Optional $maxKb: the largest file
     in KB (the Prospect page passes the Admin > Config upload size, 22/10/2026); default ImportRequest::MAX_KB. --}}
@php($maxKb = $maxKb ?? \App\Http\Requests\Crm\ImportRequest::MAX_KB)
<div class="overlay" id="{{ $id }}" hidden>
  <div class="scrim" data-x></div>
  <form class="modal" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}T" method="POST" action="{{ $action }}" enctype="multipart/form-data">
    @csrf
    <div class="mh"><h3 id="{{ $id }}T">{{ $title }}</h3></div>
    <div class="mb">
      <div class="field">
        <label for="{{ $id }}F">CSV file<span class="req">*</span></label>
        <input class="inp" id="{{ $id }}F" type="file" name="file" accept=".csv,text/csv" data-max-kb="{{ $maxKb }}" required style="padding-top:6px">
        <span class="hint">CSV saved from Excel (plain CSV or CSV UTF-8), max {{ \App\Support\UploadLimit::label($maxKb) }} and {{ \App\Support\Csv::MAX_ROWS }} rows. Dates as dd/mm/yyyy.</span>
      </div>
      <div class="small muted" style="display:grid;gap:6px">{!! $help !!}</div>
      <div><a class="btn gh sm" href="{{ $template }}" download><svg class="ico"><use href="#i-download"/></svg>Download template</a></div>
    </div>
    <div class="mf" style="display:flex;gap:8px;justify-content:flex-end;padding:12px 20px 18px">
      <button class="btn gh" type="button" data-x>Cancel</button>
      <button class="btn add" type="submit"><svg class="ico"><use href="#i-upload"/></svg>Import</button>
    </div>
  </form>
</div>
