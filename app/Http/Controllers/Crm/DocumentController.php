<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\DocumentRequest;
use App\Models\CustomerPo;
use App\Models\Document;
use App\Models\Enquiry;
use App\Models\Quotation;
use App\Models\SiteVisit;
use App\Services\DocumentService;
use App\Support\WorkContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

use Symfony\Component\HttpFoundation\StreamedResponse;

/*
| Documents & attachments (CRM changes, Sat 13/10/2026) on enquiries, site visits, quotations and POs.
| Viewing a document needs View of its record's page; uploading and deleting need Edit. The record
| must be in one of the user's units.
*/
class DocumentController extends Controller
{
    /** Record type in the URL => model, page permission and the optional part it belongs to. */
    private const TYPES = [
        // CRM and PO can be switched off (01/11/2026): their records' documents are then refused as "switched off".
        'enquiry' => [Enquiry::class, 'enquiry', 'crm'],
        'site_visit' => [SiteVisit::class, 'site-visit', ['crm', 'site_visit']],
        'quotation' => [Quotation::class, 'quotation', 'crm'],
        // CRM › 6 PO / Agreement only again (Separate PO menu removed, 02/11/2026).
        'customer_po' => [CustomerPo::class, 'customer-po', 'crm'],
        // Upload PO of a sales order (Sales module, SALES G11).
        'sales_order' => [\App\Models\SalesOrder::class, 'sales-order', null],
    ];

    public function __construct(private readonly DocumentService $documents)
    {
    }

    public function store(DocumentRequest $request, string $type, int $id): RedirectResponse
    {
        $record = $this->record($type, $id, 'edit');
        $doc = $this->documents->upload($record, $request->validated('category'), $request->file('file'), $request->user());

        return back()->with('toast', $doc->original_name.' uploaded');
    }

    public function download(Request $request, Document $document): StreamedResponse|RedirectResponse
    {
        $this->record($document->documentable_type, $document->documentable_id, 'view');
        // A BOQ or costing document is refused while that part is switched off.
        $this->ensureCategoryShown($document->category);
        $disk = DocumentService::disk();
        abort_unless($disk->exists($document->path), 404);

        // Images and PDFs open in the browser; anything else downloads.
        $inline = str_starts_with((string) $document->mime, 'image/') || $document->mime === 'application/pdf';
        $disposition = ($inline ? 'inline' : 'attachment').'; filename="'.addcslashes($document->original_name, '"\\').'"';

        // From the shared bucket in production: a link valid for 5 minutes, so files are not streamed through PHP.
        if (config('filesystems.disks.'.DocumentService::diskName().'.driver') === 's3') {
            return redirect()->away($disk->temporaryUrl($document->path, now()->addMinutes(5), [
                'ResponseContentType' => $document->mime ?: 'application/octet-stream',
                'ResponseContentDisposition' => $disposition,
            ]));
        }

        return $disk->response($document->path, $document->original_name, [
            'Content-Type' => $document->mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ], $inline ? 'inline' : 'attachment');
    }

    public function destroy(Request $request, Document $document): RedirectResponse
    {
        $this->record($document->documentable_type, $document->documentable_id, 'edit');
        $this->ensureCategoryShown($document->category);
        $this->documents->delete($document, $request->user());

        return back()->with('toast', $document->original_name.' deleted');
    }

    /** A BOQ or costing document while that part is switched off: "switched off", not "not found". */
    private function ensureCategoryShown(string $category): void
    {
        if (! DocumentService::categoryShown($category)) {
            \App\Support\Modules::refuse(config('nursery.document_category_modules')[$category]);
        }
    }

    private function record(string $type, int $id, string $action): Model
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        [$model, $page, $module] = self::TYPES[$type];
        if (! \App\Support\Modules::allows($module)) {
            \App\Support\Modules::refuse(\App\Support\Modules::offOf($module));
        }
        abort_unless(auth()->user()->can($page.'.'.$action), 403);
        $record = $model::findOrFail($id);
        abort_unless(in_array($record->unit_id, WorkContext::unitIds(), true), 403);

        return $record;
    }
}
