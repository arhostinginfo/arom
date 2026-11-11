<?php

namespace App\Http\Controllers\Crm;

use App\Enums\QuotationStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\LookupController;
use App\Http\Requests\Crm\CustomerPoRequest;
use App\Models\ActivityLog;
use App\Models\CustomerPo;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\Item;
use App\Models\Party;
use App\Models\Quotation;
use App\Models\Uom;
use App\Services\CustomerPoService;
use App\Services\SalesOrderService;
use App\Support\Csv;
use App\Support\Dates;
use App\Support\ListQuery;
use App\Support\WorkContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
| CRM › 6 PO / Agreement received (CRM changes, Sat 13/10/2026): one entry as a full form with an Entry selector and Add
| new (D11), with the PO and agreement documents (saved with a new PO since Fri 23/10/2026), and items from the Item
| master (Part master, 29/10/2026). A PO is entered against a quotation that can take one (made from an enquiry, current
| revision, sent to the customer, not Rejected / Expired); the client, enquiry and unit come from it, as before
| 29/10/2026. The PO list (01/11/2026) and Create Sales Order (02/11/2026) are on this page too.
| Separate PO menu removed (02/11/2026): this controller is back under CRM (it was App\Http\Controllers\Po from 01/11),
| with no PO menu context, no direct PO and no Customer picker.
*/
class CustomerPoController extends Controller
{
    public function __construct(private readonly CustomerPoService $pos)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        if ($request->filled('id')) {
            return redirect()->route('crm.customer-pos.show', (int) $request->query('id'));
        }

        return $this->form($request, $this->search($request)->first() ?? $this->blank($request));
    }

    public function show(Request $request, CustomerPo $customerPo): View
    {
        $this->authorizeUnit($customerPo);

        return $this->form($request, $customerPo);
    }

    public function create(Request $request): View
    {
        return $this->form($request, $this->blank($request));
    }

    public function store(CustomerPoRequest $request): RedirectResponse
    {
        // Documents chosen in the form are saved with the new PO, so there is no second step (Fri 23/10/2026). They were
        // checked with the PO; if one still cannot be stored, nothing is saved (the service rolls back the PO and removes
        // the files already stored) and the form comes back with the entries, to choose the files and save again.
        $files = array_values($request->file('doc_files', []));
        try {
            $po = $this->pos->create($request->poData(), $request->user(), $files, $request->validated('doc_category'));
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if (! $files) {
                throw $e;
            }
            report($e);

            return back()->withInput()->withErrors(['doc_files' => 'The PO was not saved because a document could not be stored. Check the entries, choose the files again and save.']);
        }
        $saved = count($files);

        return redirect()->route('crm.customer-pos.show', $po)
            ->with('toast', 'PO / agreement '.$po->po_no.' saved'.($saved ? ' with '.$saved.' '.($saved === 1 ? 'document' : 'documents') : ''));
    }

    public function update(CustomerPoRequest $request, CustomerPo $customerPo): RedirectResponse
    {
        $this->authorizeUnit($customerPo);
        $this->pos->update($customerPo, $request->poData(), $request->user());

        return redirect()->route('crm.customer-pos.show', $customerPo)->with('toast', 'PO / agreement '.$customerPo->po_no.' saved');
    }

    public function destroy(Request $request, CustomerPo $customerPo): RedirectResponse
    {
        $this->authorizeUnit($customerPo);
        $this->pos->delete($customerPo, $request->user());

        return redirect()->route('crm.customer-pos.index')->with('toast', 'PO / agreement '.$customerPo->po_no.' deleted');
    }

    /**
     * Create Sales Order (02/11/2026): a Draft sales order in Sales with this PO's customer, PO no, dates and items, then
     * the new order opens for the user to check and send for approval. A refusal comes back to the PO page.
     */
    public function salesOrder(Request $request, CustomerPo $customerPo, SalesOrderService $orders): RedirectResponse
    {
        $this->authorizeUnit($customerPo);
        try {
            $so = $orders->createFromCustomerPo($customerPo, $request->user(), WorkContext::financialYear());
        } catch (ValidationException $e) {
            return redirect()->route('crm.customer-pos.show', $customerPo)->withErrors(['sales_order' => collect($e->errors())->flatten()->all()]);
        }
        $toast = 'Sales order '.$so->so_no.' made from PO '.$customerPo->po_no.'. Check it and send it for approval.';
        if (! $request->user()->canAny(['sales-order.view', 'sales-order-approval.view'])) {
            return redirect()->route('crm.customer-pos.show', $customerPo)->with('toast', $toast);
        }

        return redirect()->route('sales.orders.show', $so)->with('toast', $toast);
    }

    /**
     * PO list (01/11/2026): the unit's POs, 25 a page on the server, latest first. Search: PO no, customer name, enquiry
     * no ("starts with"), and a PO date range.
     */
    public function list(Request $request): View
    {
        [$query, $filters, $errors] = self::listQuery($request);
        [$sort, $dir] = ListQuery::sort($query, $request, ['date' => 'po_date', 'no' => 'po_no', 'value' => 'po_value'], 'desc');

        return view('crm.customer-pos.list', [
            'rows' => $query->with(['party:id,name', 'prospect:id,name', 'enquiry:id,enquiry_no', 'quotation:id,quotation_no,revision_no'])
                ->withCount('lines')->paginate(ListQuery::perPage($request))->withQueryString(),
            'q' => $filters['q'], 'from' => $filters['from'], 'to' => $filters['to'], 'sort' => $sort, 'dir' => $dir,
        ])->withErrors($errors);
    }

    /** Download CSV of the PO list as filtered: one row per PO, with its number of items and value. */
    public function export(Request $request): StreamedResponse
    {
        [$query] = self::listQuery($request);
        $rows = $query->reorder()->with(['party:id,name', 'prospect:id,name', 'enquiry:id,enquiry_no', 'quotation:id,quotation_no,revision_no', 'receiver:id,name'])
            ->withCount('lines')->withSum('lines', 'amount')->lazyById(1000)
            ->map(fn (CustomerPo $p) => [
                $p->po_no, Csv::date($p->po_date), $p->clientName(), $p->enquiry?->enquiry_no,
                $p->quotation ? $p->quotation->quotation_no.' Rev '.$p->quotation->revision_no : '', $p->site,
                $p->lines_count, $p->lines_sum_amount !== null ? number_format((float) $p->lines_sum_amount, 2, '.', '') : '',
                number_format((float) $p->po_value, 2, '.', ''), Csv::date($p->valid_from), Csv::date($p->valid_to), $p->receiver?->name, Csv::date($p->received_date),
            ]);

        return Csv::download('customer-pos-'.now()->format('Y-m-d').'.csv',
            ['PO / Agreement no', 'PO date', 'Customer', 'Enquiry no', 'Quotation', 'Site', 'Items', 'Items total', 'PO value', 'Valid from', 'Valid to', 'Received by', 'Received date'], $rows);
    }

    /**
     * The list's query, shared by the list and its CSV: unit on the top bar, search and dates. Wrong dates are reported
     * and not applied.
     *
     * @return array{0: Builder, 1: array{q: string, from: ?string, to: ?string}, 2: MessageBag}
     */
    private static function listQuery(Request $request): array
    {
        $q = LookupController::term($request);
        // Dates are checked like a form field, as on the Activity log: a wrong date shows an error and is not applied.
        $errors = Validator::make($request->query(), [
            'from' => ['nullable', 'date_format:d/m/Y'],
            'to' => ['nullable', 'date_format:d/m/Y'],
        ], [
            'from.date_format' => 'Enter From date as a real date, dd/mm/yyyy.',
            'to.date_format' => 'Enter To date as a real date, dd/mm/yyyy.',
        ])->errors();
        $date = fn (string $key) => ! $errors->has($key) && $request->filled($key) ? Carbon::createFromFormat('!d/m/Y', (string) $request->query($key)) : null;
        $from = $date('from');
        $to = $date('to');
        if ($from && $to && $to->lt($from)) {
            $errors->add('to', 'To date must be on or after From date.');
            $to = null;
        }
        $unit = WorkContext::unit()?->id;
        $query = CustomerPo::query()->where('unit_id', $unit)
            ->when($from, fn ($w) => $w->where('po_date', '>=', $from->toDateString()))
            ->when($to, fn ($w) => $w->where('po_date', '<=', $to->toDateString()))
            // PO no, customer name or enquiry no, all "starts with" (database review DB-C3).
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('po_no', 'like', LookupController::prefix($q))
                ->orWhereIn('party_id', Party::select('id')->where('name', 'like', LookupController::prefix($q)))
                ->orWhereIn('enquiry_id', Enquiry::select('id')->where('unit_id', $unit)->where('enquiry_no', 'like', LookupController::prefix($q)))));

        return [$query, ['q' => $q, 'from' => $from ? Dates::show($from) : null, 'to' => $to ? Dates::show($to) : null], $errors];
    }

    public function lookup(Request $request): JsonResponse
    {
        return response()->json($this->search($request)->forPage(LookupController::page(), LookupController::LIMIT)->get()->map(fn (CustomerPo $p) => [
            'id' => $p->id,
            'label' => $p->po_no.' · '.$p->clientName(),
            'sub' => $p->po_date->format(config('nursery.date_format')).' · '.\App\Support\Money::indian($p->po_value, 2),
            'url' => route('crm.customer-pos.show', $p),
        ]));
    }

    /**
     * Item master (Part master) for the PO's items (29/10/2026), first 20 matching item no, IPN or description:
     * ?quotation= the quotation's customer's sales items and items of no party, or any sales item for a prospect's
     * quotation; none without a quotation of the user's units.
     */
    public function items(Request $request): JsonResponse
    {
        $q = LookupController::term($request);
        $quote = Quotation::whereIn('unit_id', WorkContext::unitIds())->find($request->integer('quotation'));
        if (! $quote) {
            return response()->json([]);
        }

        return response()->json(self::itemsFor($quote->party_id)->with('uom:id,code')
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('item_no', 'like', LookupController::prefix(strtoupper($q)))
                ->orWhere('internal_item_no', 'like', LookupController::prefix(strtoupper($q)))
                ->orWhere('description', 'like', LookupController::prefix($q))))
            ->orderBy('item_no')->forPage(LookupController::page(), LookupController::LIMIT)->get()
            ->map(fn (Item $i) => ['id' => $i->id, 'label' => $i->pickerLabel(), 'sub' => 'Rate '.rtrim(rtrim((string) $i->price, '0'), '.').' · '.$i->uom?->code,
                'data' => self::itemData($i)]));
    }

    /** What the page fills in when an item is picked: description, UOM and the Item master price. */
    public static function itemData(Item $i): array
    {
        return ['desc' => $i->description, 'uom' => (string) $i->uom_id, 'price' => (string) (float) $i->price];
    }

    /** Sales items for a party (and items of no party); null party = any sales item. */
    private static function itemsFor(?int $partyId): Builder
    {
        return Item::forSale()->when($partyId, fn ($q) => $q->where(fn ($w) => $w->whereNull('party_id')->orWhere('party_id', $partyId)));
    }

    private function search(Request $request)
    {
        $q = LookupController::term($request);
        $unit = WorkContext::unit()?->id;

        // PO no, the enquiry's number, client or contact no, or the customer's name, all "starts with" (database review DB-C3).
        return CustomerPo::query()->where('unit_id', $unit)
            ->with(['party:id,name', 'prospect:id,name'])
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('po_no', 'like', LookupController::prefix($q))
                ->orWhereIn('enquiry_id', EnquiryController::matching(Enquiry::select('id')->where('unit_id', $unit), $q))
                ->orWhereIn('party_id', Party::select('id')->where('name', 'like', LookupController::prefix($q)))))
            ->latest('id');
    }

    private function form(Request $request, CustomerPo $po): View
    {
        $entries = $this->search($request)->limit(50)->get();
        if ($po->exists && ! $entries->contains('id', $po->id)) {
            $entries->prepend($po);
        }
        $po->loadMissing(['enquiry', 'quotation', 'party', 'prospect', 'creator', 'updater', 'lines.item', 'lines.uom', 'salesOrder']);

        return view('crm.customer-pos.form', [
            'po' => $po,
            'entries' => $entries,
            'q' => trim((string) $request->query('q')),
            'quotationOptions' => $this->quotationOptions($request->old('quotation_id', $po->quotation_id)),
            'employees' => QuotationController::pickerOptions(Employee::active()->orderBy('name'), Employee::class, $request->old('received_by', $po->received_by)),
            'itemOptions' => $this->itemOptions($po->party_id),
            'uoms' => Uom::forPicker()->orWhereIn('id', $po->lines->pluck('uom_id')->filter()->all())->orderBy('code')->get(['id', 'code', 'description']),
            // The quotation's customer's items (any sales item for a prospect's quotation).
            'itemsUrl' => route('crm.customer-pos.items', ['quotation' => (int) $request->old('quotation_id', $po->quotation_id)]),
            // Paged on the server, 10 a page (architecture review C3, code review M4).
            'docs' => \App\Services\DocumentService::box($po),
            'history' => ActivityLog::history($po, 6),
        ]);
    }

    /** The item drop-downs without typing: all the customer's possible items while there are at most 200. */
    private function itemOptions(?int $partyId): Collection
    {
        if (! $partyId) {
            return collect();
        }
        $list = self::itemsFor($partyId)->orderBy('item_no')->limit(201)->get();

        return $list->count() > 200 ? collect() : $list->keyBy('id');
    }

    /**
     * Quotations that can take a PO (as before 29/10/2026): the current revision of the unit's quotations made from an
     * enquiry and with the customer (not Rejected or Expired), latest 200, plus the chosen one.
     */
    private function quotationOptions($selected): Collection
    {
        $list = Quotation::where('unit_id', WorkContext::unit()?->id)->latestRevisions()->whereNotNull('enquiry_id')
            ->whereIn('status', array_map(fn ($s) => $s->value,
                array_filter(QuotationStatus::customerStatuses(), fn ($s) => ! in_array($s, [QuotationStatus::Rejected, QuotationStatus::Expired], true))))
            ->with(['party:id,name', 'prospect:id,name'])->latest('id')->limit(200)->get();
        // Tester review (01/11/2026): the chosen one comes back from the form after an error, so only one of the user's units
        // is shown (a typed id of another unit's quotation must not show its number and customer).
        if ($selected && is_scalar($selected) && ! $list->contains('id', (int) $selected)
            && ($q = Quotation::withTrashed()->whereIn('unit_id', WorkContext::unitIds())->with(['party:id,name', 'prospect:id,name'])->find((int) $selected))) {
            $list->prepend($q);
        }

        return $list->mapWithKeys(fn (Quotation $q) => [$q->id => $q->quotation_no.' Rev '.$q->revision_no.' · '.$q->customerName().' · '.$q->status->value]);
    }

    /**
     * A new PO. Opened with ?quotation= (from the CRM step or the quotation page), it is filled from that quotation when it
     * can take a PO (code review M2, as before 29/10/2026): client, enquiry, site, value, terms and contact.
     */
    private function blank(Request $request): CustomerPo
    {
        $po = new CustomerPo(['received_date' => today(), 'po_date' => today()]);
        $po->received_by = Employee::active()->where('user_id', auth()->id())->value('id');
        $unit = WorkContext::unit()?->id;
        if ($request->filled('quotation')
            && ($q = Quotation::where('unit_id', $unit)->find((int) $request->query('quotation')))
            && CustomerPoService::canTakePo($q)) {
            $po->quotation_id = $q->id;
            $po->enquiry_id = $q->enquiry_id;
            $po->party_id = $q->party_id;
            $po->prospect_id = $q->prospect_id;
            $po->site = $q->site;
            $po->po_value = $q->grand_total;
            $po->payment_terms = $q->payment_terms;
            // A quotation's GST / tax is now a Tax master row (preferred); an old one gives its text (23/10/2026).
            $po->tax_terms = $q->taxStructure?->name ?: $q->tax_terms;
            $po->contact_name = $q->contact_name;
        }

        return $po;
    }

    private function authorizeUnit(CustomerPo $po): void
    {
        abort_unless(in_array($po->unit_id, WorkContext::unitIds(), true), 403);
    }
}
