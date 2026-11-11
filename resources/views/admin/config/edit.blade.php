{{-- Admin > Config (22/10/2026): installation-wide settings, now the maximum size of a file upload on a form. --}}
@php($canSave = auth()->user()->can('config.edit'))
@php($phpKb = min(array_filter($php) ?: [PHP_INT_MAX]))
@extends('layouts.app', ['title' => 'Config', 'crumbs' => [['Admin', route('admin.users.index')], ['Config', null]]])

@section('content')
<div class="pagehd"><h1>Config</h1><span class="sp"></span></div>

@if ($errors->any())
  <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>Check the fields marked in red.</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
@endif

<form method="POST" action="{{ route('admin.config.update') }}" novalidate data-dirty-guard data-saving>
  @csrf @method('PUT')
  <div class="sectcard">
    <div class="sh"><h2>File uploads</h2><span class="sp"></span><span class="cap muted">Every form with a file upload</span></div>
    <div class="sb">
      <fieldset @disabled(! $canSave) style="border:0;padding:0;margin:0;min-width:0">
      <div class="fgrid">
        <div class="field">
          <label for="upload_max_mb">Maximum file size (MB)<span class="req">*</span></label>
          <input class="inp num @error('upload_max_mb') err @enderror" id="upload_max_mb" name="upload_max_mb" value="{{ old('upload_max_mb', \App\Support\UploadLimit::mb($uploadKb)) }}" inputmode="decimal" required @error('upload_max_mb') aria-invalid="true" aria-describedby="upload_max_mb-err" @enderror>
          <span class="hint">{{ \App\Support\UploadLimit::MIN_MB }} to {{ \App\Support\UploadLimit::MAX_MB }} MB, for example 1 or 2.5. Default 1 MB. A larger file is refused with "The file must not be larger than {{ \App\Support\UploadLimit::label($uploadKb) }}."</span>
          @include('partials.ferr', ['name' => 'upload_max_mb'])
        </div>
      </div>
      </fieldset>
      @if ($uploadKb > $phpKb)
        <div class="alert err" role="status"><svg class="ico"><use href="#i-alert"/></svg><div><b>The server takes at most {{ \App\Support\UploadLimit::label($phpKb) }}.</b> PHP's upload_max_filesize is {{ ini_get('upload_max_filesize') }} and post_max_size is {{ ini_get('post_max_size') }}, so files above {{ \App\Support\UploadLimit::label($phpKb) }} fail before this limit is checked. Ask the server administrator to raise both in php.ini.</div></div>
      @endif
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>Applies now to Prospect › Import CSV and Enquiry › documents; other uploads move to it as their forms are updated. Server limit (PHP): upload_max_filesize {{ ini_get('upload_max_filesize') ?: 'none' }}, post_max_size {{ ini_get('post_max_size') ?: 'none' }}.</span></div>
    </div>
  </div>
  @if ($canSave)<div class="formfoot"><span class="sp"></span><button class="btn pri" type="submit"><svg class="ico"><use href="#i-check"/></svg>Save config</button></div>@endif
</form>
@endsection
