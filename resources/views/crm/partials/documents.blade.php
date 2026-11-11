{{--
  Documents & attachments (CRM changes). Params: $type (enquiry, site_visit, quotation, customer_po), $record,
  $docs (from DocumentService::box: a page of documents, the total, whether they can be changed now), $canEdit,
  optional $title, $hint and $default. Each row shows its uploader and upload time. Paged on the server.
  Optional $deletable (default true): false hides the per-row Delete button (every CRM page, Thu 22/10/2026; the sales order page keeps it).
--}}
@php($cats = \App\Services\DocumentService::categories($type))
@php($editable = $canEdit && $docs['changeable'])
@php($list = $docs['list'])
{{-- Largest file: the Admin > Config upload size on enquiries, 10 MB elsewhere for now (22/10/2026). --}}
@php($maxKb = \App\Http\Requests\Crm\DocumentRequest::maxKb($type))
<div class="sectcard" id="documents">
  <div class="sh"><h2>{{ $title ?? 'Documents' }}</h2><span class="badge b-neu">{{ $docs['count'] }}</span><span class="sp"></span></div>
  <div class="sb">
    @if ($editable)
      <form class="docup" method="POST" action="{{ route('crm.documents.store', ['type' => $type, 'id' => $record->id]) }}" enctype="multipart/form-data" data-saving>
        @csrf
        <div class="field">
          <label for="docCat">Document type<span class="req">*</span></label>
          <select class="inp @error('category') err @enderror" id="docCat" name="category" required>
            @foreach ($cats as $c)<option @selected(old('category', in_array($default ?? null, $cats, true) ? $default : $cats[0]) === $c)>{{ $c }}</option>@endforeach
          </select>
          @include('partials.ferr', ['name' => 'category'])
        </div>
        <div class="field grow">
          <label for="docFile">File<span class="req">*</span></label>
          <input class="inp @error('file') err @enderror" id="docFile" type="file" name="file" required accept="{{ $type === 'sales_order' ? '.pdf,.jpg,.jpeg,.png' : '.pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt,.eml,.msg' }}{{ ($camera ?? false) ? ',image/*' : '' }}" data-max-kb="{{ $maxKb }}" style="padding-top:6px" @error('file') aria-invalid="true" aria-describedby="file-err" @enderror>
          <span class="hint">{{ $type === 'sales_order' ? 'PDF, JPG or PNG' : 'PDF, image, Word, Excel, CSV, text or email' }}. Max {{ \App\Support\UploadLimit::label($maxKb) }}.</span>
          @include('partials.ferr', ['name' => 'file'])
        </div>
        <button class="btn add" type="submit"><svg class="ico"><use href="#i-upload"/></svg>{{ $uploadLabel ?? 'Upload' }}</button>
      </form>
      @if (! empty($hint))<div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>{{ $hint }}</span></div>@endif
    @elseif ($canEdit && ! $docs['changeable'])
      <div class="ruleh"><svg class="ico"><use href="#i-lock"/></svg><span>These documents were part of what was submitted for approval, so they cannot be changed now. Use Revise to make a new Draft for changes.</span></div>
    @endif
    @if (! $list || $list->isEmpty())
      <div class="empty">No documents yet.</div>
    @else
      <div class="tablecard">
        <table class="atable rtable">
          <thead><tr><th>Document</th><th>Type</th><th>Size</th><th>Uploaded by</th><th>Uploaded on</th><th><span class="sr">Actions</span></th></tr></thead>
          <tbody>
          @foreach ($list as $d)
            <tr>
              <td data-label="Document" class="wide"><a href="{{ route('crm.documents.download', $d) }}" target="_blank" rel="noopener">{{ $d->original_name }}</a></td>
              <td data-label="Type">{{ $d->category }}</td>
              <td data-label="Size" class="num">{{ $d->sizeLabel() }}</td>
              <td data-label="Uploaded by">{{ $d->uploader?->name ?? 'Unknown' }}</td>
              <td data-label="Uploaded on" class="num">{{ $d->created_at?->format(config('nursery.datetime_format')) }}</td>
              <td class="r">
                @if ($editable && ($deletable ?? true))
                  <form method="POST" action="{{ route('crm.documents.destroy', $d) }}" data-confirm="Delete {{ $d->original_name }}?">@csrf @method('DELETE')
                    <button class="btn gh icon sm" type="submit" aria-label="Delete {{ $d->original_name }}"><svg class="ico"><use href="#i-x"/></svg></button>
                  </form>
                @endif
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
      @if ($list->hasPages())
        <nav class="pager" aria-label="Document pages">
          <span>Showing {{ $list->firstItem() }} to {{ $list->lastItem() }} of {{ $docs['count'] }}</span>
          <span class="pg">
            @if ($list->onFirstPage())<span class="dis" aria-hidden="true"><svg class="ico" style="width:14px"><use href="#i-left"/></svg></span>@else<a href="{{ $list->previousPageUrl() }}" aria-label="Previous documents"><svg class="ico" style="width:14px"><use href="#i-left"/></svg></a>@endif
            <span class="on" aria-current="page">{{ $list->currentPage() }}</span>
            @if ($list->hasMorePages())<a href="{{ $list->nextPageUrl() }}" aria-label="Next documents"><svg class="ico" style="width:14px"><use href="#i-right"/></svg></a>@else<span class="dis" aria-hidden="true"><svg class="ico" style="width:14px"><use href="#i-right"/></svg></span>@endif
          </span>
        </nav>
      @endif
    @endif
  </div>
</div>
