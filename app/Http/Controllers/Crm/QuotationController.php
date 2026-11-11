<?php

namespace App\Http\Controllers\Crm;

use App\Enums\QuotationStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\LookupController;
use App\Http\Requests\Crm\ImportRequest;
use App\Http\Requests\Crm\QuotationActionRequest;
use App\Http\Requests\Crm\QuotationRequest;
use App\Models\ActivityLog;
use App\Models\ApprovalRule;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\Quotation;
use App\Models\TaxStructure;
use App\Models\Unit;
use App\Models\Uom;
use App\Models\User;
use App\Services\QuotationService;
use App\Support\Csv;
use App\Support\Gst;
use App\Support\ListQuery;
use App\Support\WorkContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
| CRM > Quotation. One entry shown as a full form with an Entry selector and Add new (decision D11).
| The selector lists the current revision of each quotation of the unit chosen on the top bar
| in the current financial year (OTHER POINTS A22). CRM changes (Sat 13/10/2026): made from an enquiry,
| with BOQ, internal cost details, approval (amount criteria), email and the quotation status.
*/
class QuotationController extends Controller
{
    private const FALLBACK_OPTIONS = 200;

    public function __construct(private readonly QuotationService $quotations)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        // The entry picker's no-JavaScript form sends the chosen entry as ?id=.
        if ($request->filled('id')) {
            return redirect()->route('crm.quotations.show', (int) $request->query('id'));
        }
        $first = $this->search($request)->first();

        return $this->form($request, $first ?? $this->blank($request));
    }

    public function show(Request $request, Quotation $quotation): View
    {
        $this->authorizeUnit($quotation);

        return $this->form($request, $quotation);
    }

    public function create(Request $request): View
    {
        return $this->form($request, $this->blank($request));
    }

    public function store(QuotationRequest $request): RedirectResponse
    {
        $q = $this->quotations->create($request->validated(), $request->user(), $this->unit());

        return redirect()->route('crm.quotations.show', $q)->with('toast', 'Quotation '.$q->quotation_no.' created');
    }

    public function update(QuotationRequest $request, Quotation $quotation): RedirectResponse
    {
        $this->authorizeUnit($quotation);
        $this->quotations->update($quotation, $request->validated(), $request->user());

        return redirect()->route('crm.quotations.show', $quotation)->with('toast', $quotation->activityLabel().' saved');
    }

    public function revise(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorizeUnit($quotation);
        $new = $this->quotations->revise($quotation, $request->user());

        return redirect()->route('crm.quotations.show', $new)
            ->with('toast', $new->quotation_no.' revised to Rev '.$new->revision_no.'. Rev '.$quotation->revision_no.' is kept read-only.');
    }

    public function destroy(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorizeUnit($quotation);
        $this->quotations->delete($quotation, $request->user());

        return redirect()->route('crm.quotations.index')->with('toast', 'Quotation '.$quotation->quotation_no.' deleted');
    }

    public function submit(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorizeUnit($quotation);
        $q = $this->quotations->submit($quotation, $request->user());

        return back()->with('toast', $q->status === QuotationStatus::Approved
            ? $q->activityLabel().' approved automatically: its total is within the approval limit'
            : $q->activityLabel().' sent for approval'.($q->currency?->code !== 'INR' ? ' (quotations in '.$q->currency?->code.' always need an approver)' : ''));
    }

    public function approve(QuotationActionRequest $request, Quotation $quotation): RedirectResponse
    {
        $this->authorizeUnit($quotation);
        $this->quotations->approve($quotation, $request->user(), $request->validated('remarks'));

        return back()->with('toast', $quotation->activityLabel().' approved');
    }

    public function reject(QuotationActionRequest $request, Quotation $quotation): RedirectResponse
    {
        $this->authorizeUnit($quotation);
        $this->quotations->reject($quotation, $request->user(), $request->validated('remarks'));

        return back()->with('toast', $quotation->activityLabel().' rejected and returned to Draft');
    }

    public function email(QuotationActionRequest $request, Quotation $quotation): RedirectResponse
    {
        $this->authorizeUnit($quotation);
        $this->quotations->email($quotation, $request->validated(), $request->user());

        return back()->with('toast', $quotation->activityLabel().' is being emailed to '.$request->validated('to'));
    }

    public function markSent(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorizeUnit($quotation);
        $this->quotations->markSent($quotation, $request->user(), 'by hand');

        return back()->with('toast', $quotation->activityLabel().' marked as sent');
    }

    public function customerStatus(QuotationActionRequest $request, Quotation $quotation): RedirectResponse
    {
        $this->authorizeUnit($quotation);
        $status = QuotationStatus::from($request->validated('status'));
        $this->quotations->setCustomerStatus($quotation, $status, $request->validated('status_reason'), $request->user());

        return back()->with('toast', $quotation->activityLabel().' is now '.$status->value);
    }

    /** Generate PDF of the revision on screen. Cost details are internal and never printed. */
    public function pdf(Quotation $quotation): Response
    {
        $this->authorizeUnit($quotation);
        $quotation->load(['lines.taxStructure', 'party', 'prospect', 'salesPerson', 'preparer', 'currency', 'unit', 'enquiry', 'taxStructure']);

        return Pdf::loadView('pdf.quotation', ['q' => $quotation])
            ->setPaper('a4', 'portrait')->setOption('isFontSubsettingEnabled', true)
            ->download(str_replace('/', '-', $quotation->quotation_no).'-rev'.$quotation->revision_no.'.pdf');
    }

    /** Quotation list for the alerts and dashboard: all, pending approval, PO pending. Paged on the server (P1). */
    public function list(Request $request): View
    {
        $views = ['all' => 'All', 'approval' => 'Pending approval', 'po-pending' => 'Verbal wins / PO pending', 'sent' => 'With the customer'];
        $view = isset($views[$request->query('view')]) ? $request->query('view') : 'all';
        $query = Quotation::where('unit_id', $this->unit()->id)->latestRevisions()->with(['party:id,name', 'prospect:id,name', 'currency:id,code', 'salesPerson:id,name']);
        match ($view) {
            'approval' => $query->where('status', QuotationStatus::InternalApproval->value),
            'po-pending' => $query->poPending(),
            'sent' => $query->whereIn('status', array_map(fn ($s) => $s->value, QuotationStatus::customerStatuses())),
            default => null,
        };
        $q = LookupController::term($request);
        // Quotation no, enquiry no or client name, "starts with" (database review DB-C3, UX review S12).
        $query->when($q !== '', fn ($w) => $w->where(fn ($s) => LookupController::orClient(LookupController::orNumber(LookupController::orNumber($s, 'quotation_no', 'QT', $q), 'enquiry_no', 'ENQ', $q), $q)));
        [$sort, $dir] = ListQuery::sort($query, $request, ['date' => 'quotation_date', 'total' => 'grand_total', 'no' => 'quotation_no'], 'desc');

        return view('crm.quotations.list', [
            'rows' => $query->paginate(ListQuery::perPage($request))->withQueryString(),
            'view' => $view, 'views' => $views, 'q' => $q, 'sort' => $sort, 'dir' => $dir,
        ]);
    }

    /** Download CSV: the current revision of each quotation of this unit and year, one row per item, in the import layout. */
    public function export(): StreamedResponse
    {
        $headers = array_merge(['Quotation no', 'Rev no', 'Status', 'Client'], array_values(QuotationService::IMPORT_COLUMNS), ['Item total']);
        $rows = $this->scoped()->latestRevisions()
            ->with(['lines.taxStructure', 'party', 'prospect', 'salesPerson', 'currency'])
            // 1,000 quotations at a time by id, in the order they were added (DB-M7, decision C13).
            ->lazyById(1000)
            ->flatMap(fn (Quotation $q) => $q->lines->map(fn ($l) => [
                $q->quotation_no, $q->revision_no, $q->status->value, $q->customerName(),
                $q->quotation_no, $q->enquiry_no, Csv::date($q->quotation_date), Csv::date($q->valid_until), $q->contact_name, $q->site,
                $q->salesPerson?->email, $q->currency?->code, $q->payment_terms, $q->delivery_terms, $q->discount_amount, $q->notes,
                // Tax: the Tax master name, or an old line's Tax % (23/10/2026). Only master values import again: old units or rates give row errors.
                $l->item_no, $l->description, $l->qty, $l->uom, $l->rate, $l->discount_percent,
                $l->taxStructure?->name ?? ((float) $l->tax_percent ? rtrim(rtrim((string) $l->tax_percent, '0'), '.') : ''), $l->amount,
            ]));

        return Csv::download('quotations-'.now()->format('Y-m-d').'.csv', $headers, $rows);
    }

    public function template(): StreamedResponse
    {
        $sales = QuotationRequest::salesPeopleQuery()?->orderBy('name')->value('email') ?? 'sales.user@company.example';
        $enq = Enquiry::where('unit_id', WorkContext::unit()?->id)->latest('id')->value('enquiry_no') ?? 'ENQ/26-27/0001';
        $today = now()->format('d/m/Y');
        // Unit and Tax of the sample rows come from the masters, so the template imports as it is (23/10/2026).
        $uom = Uom::where('is_active', true)->orderByRaw("code = 'NOS' desc")->orderBy('code')->value('code') ?? 'NOS';
        $tax = TaxStructure::where('is_active', true)->orderByRaw('igst_rate = 18 desc')->orderBy('name')->value('name') ?? 'GST 18%';

        return Csv::download('quotation-import-template.csv', array_values(QuotationService::IMPORT_COLUMNS), [
            ['1', $enq, $today, now()->addDays(30)->format('d/m/Y'), 'Ms Kavita Rao', 'Tower B lobby', $sales, 'INR', '50% advance, balance on installation', 'Installation within 10 days of PO', '0', '', 'PL-ARECA', 'Areca palm, 5 ft, with planter', '20', $uom, '1450', '0', $tax],
            ['1', $enq, $today, now()->addDays(30)->format('d/m/Y'), 'Ms Kavita Rao', 'Tower B lobby', $sales, 'INR', '', '', '', '', '', 'Monthly maintenance visit', '12', $uom, '2500', '5', $tax],
        ]);
    }

    public function import(ImportRequest $request): RedirectResponse
    {
        $rows = Csv::read($request->file('file'), ['Group', 'Enquiry no', 'Quotation date', 'Sales person login email', 'Item description', 'Qty', 'Rate']);
        $count = $this->quotations->import($rows, $request->user(), $this->unit());

        return redirect()->route('crm.quotations.index')->with('toast', $count.' quotation(s) imported');
    }

    /** id => name for a picker's select: all records when few, otherwise just the chosen one (decision A11). */
    public static function pickerOptions($query, string $model, $selected): Collection
    {
        $list = $query->limit(self::FALLBACK_OPTIONS + 1)->pluck('name', 'id');
        if ($list->count() > self::FALLBACK_OPTIONS) {
            $list = collect();
        }
        if ($selected && ! $list->has($selected)) {
            $m = in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($model), true) ? $model::withTrashed()->find($selected) : $model::find($selected);
            if ($m) {
                $list->put($m->id, $m->name);
            }
        }

        return $list;
    }

    /**
     * Sales person picker (A5 / DB-M2): the first FALLBACK_OPTIONS Sales users for the no-JavaScript select,
     * or only the chosen one when there are more. The chosen one is kept even if no longer in the Sales role.
     */
    public static function salesPeopleOptions($selected): Collection
    {
        $list = QuotationRequest::salesPeopleQuery()?->orderBy('name')->limit(self::FALLBACK_OPTIONS + 1)->pluck('name', 'users.id') ?? collect();
        if ($list->count() > self::FALLBACK_OPTIONS) {
            $list = collect();
        }
        if ($selected && ! $list->has($selected) && ($m = User::withTrashed()->find($selected))) {
            $list->put($m->id, $m->name);
        }

        return $list;
    }

    /** A new quotation, filled from the enquiry when it is opened from one (?enquiry=). */
    private function blank(Request $request): Quotation
    {
        $q = new Quotation([
            'currency_id' => Currency::defaultId(),
            'quotation_date' => today(),
            'valid_until' => today()->addDays(30),
        ]);
        $q->status = QuotationStatus::Draft;
        $q->revision_no = 0;
        if ($request->filled('enquiry') && ($e = Enquiry::where('unit_id', WorkContext::unit()?->id)->find((int) $request->query('enquiry')))) {
            $q->enquiry_id = $e->id;
            $q->contact_name = $e->contact_name ?: null; // the number stays on the Prospect, shown read-only (Sat 18/10/2026)
            $q->site = $e->address ? mb_substr($e->address, 0, 255) : null;
            // The lead's assignee, when that employee's login user is a sales person.
            $userId = $e->assigned_to ? Employee::whereKey($e->assigned_to)->value('user_id') : null;
            if ($userId && QuotationRequest::salesPeopleQuery()?->whereKey($userId)->exists()) {
                $q->sales_person_id = $userId;
            }
        }

        return $q;
    }

    private function unit(): Unit
    {
        $unit = WorkContext::unit();
        abort_unless($unit, 403, 'No unit is allowed for your user, so you cannot make quotations.');

        return $unit;
    }

    private function authorizeUnit(Quotation $q): void
    {
        abort_unless(in_array($q->unit_id, WorkContext::unitIds(), true), 403);
    }

    private function scoped()
    {
        return Quotation::query()
            ->where('unit_id', WorkContext::unit()?->id)
            ->where('financial_year_id', WorkContext::financialYear()->id);
    }

    /** Entry picker (decision UX2): latest 20 matching a number, customer, prospect or a dd/mm/yyyy date. */
    public function lookup(Request $request): JsonResponse
    {
        return response()->json($this->search($request)->forPage(LookupController::page(), LookupController::LIMIT)->get()->map(fn (Quotation $e) => [
            'id' => $e->id,
            'label' => $e->quotation_no.' · Rev '.$e->revision_no.' · '.($e->customerName() ?: 'No customer'),
            'sub' => Csv::date($e->quotation_date ?? $e->enquiry_receipt_date).' · '.$e->status->value,
            'url' => route('crm.quotations.show', $e),
        ]));
    }

    private function search(Request $request)
    {
        $q = LookupController::term($request);
        // Typed % and _ are matched literally, not as LIKE wildcards; every match is "starts with" (DB-C3).
        $date = preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', $q) ? \Illuminate\Support\Carbon::createFromFormat('d/m/Y', $q)->toDateString() : null;

        return $this->scoped()->latestRevisions()
            ->with(['party:id,name', 'prospect:id,name'])
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => LookupController::orClient(
                LookupController::orNumber(LookupController::orNumber($s, 'quotation_no', 'QT', $q), 'enquiry_no', 'ENQ', $q), $q)
                ->when($date, fn ($d) => $d->orWhereBetween('quotation_date', [$date, $date.' 23:59:59']))))
            ->latest('id');
    }

    private function form(Request $request, Quotation $quotation): View
    {
        $entries = $this->search($request)->limit(50)->get();
        if ($quotation->exists && ! $entries->contains('id', $quotation->id)) {
            $entries->prepend($quotation);
        }
        $quotation->loadMissing(['lines', 'party', 'prospect', 'creator', 'updater', 'preparer', 'cost', 'enquiry.party', 'enquiry.prospect']);
        $enquiryId = $request->old('enquiry_id', $quotation->enquiry_id);
        // UOM / Tax drop-downs (23/10/2026): active master rows, plus the inactive ones this quotation already uses.
        $keepTax = array_filter(array_merge($quotation->lines->pluck('tax_structure_id')->all(), [$quotation->tax_structure_id]));
        $taxStructures = TaxStructure::where(fn ($w) => $w->where('is_active', true)->when($keepTax, fn ($k) => $k->orWhereIn('id', $keepTax)))->orderBy('igst_rate')->orderBy('name')->get();
        $party = $quotation->party ?? ($enquiryId ? Enquiry::with('party')->find((int) $enquiryId)?->party : null);
        $rule = ApprovalRule::for('quotation');

        return view('crm.quotations.form', [
            'quotation' => $quotation,
            'entries' => $entries,
            'q' => trim((string) $request->query('q')),
            'revisions' => $quotation->exists ? $quotation->revisions()->get(['id', 'revision_no', 'is_latest', 'status', 'created_at']) : collect(),
            'enquiryOptions' => $this->enquiryOptions($enquiryId),
            'salesPeople' => self::salesPeopleOptions($request->old('sales_person_id', $quotation->sales_person_id)),
            'hasSalesPeople' => (bool) QuotationRequest::salesPeopleQuery()?->exists(),
            'currencies' => Currency::orderBy('code')->get(),
            'uoms' => Uom::where('is_active', true)->orderBy('code')->get(['id', 'code', 'description']),
            'taxStructures' => $taxStructures,
            'supply' => Gst::forQuotation($party),
            'unit' => WorkContext::unit(),
            'rule' => $rule,
            'approvals' => $quotation->exists ? $quotation->approvals()->with('actor:id,name')->latest('id')->limit(10)->get() : collect(),
            // Paged on the server, 10 a page (architecture review C3, code review M4).
            'docs' => \App\Services\DocumentService::box($quotation),
            'customerStatuses' => array_map(fn ($s) => $s->value, QuotationStatus::customerStatuses()),
            'mailTo' => $quotation->prospect?->email,
            'history' => ActivityLog::history($quotation, 6),
        ]);
    }

    /** Enquiry picker: the open enquiries of the unit for the no-JavaScript select, plus the chosen one. */
    private function enquiryOptions($selected): Collection
    {
        $list = Enquiry::where('unit_id', WorkContext::unit()?->id)->open()->with(['party:id,name', 'prospect:id,name'])
            ->latest('id')->limit(self::FALLBACK_OPTIONS + 1)->get();
        if ($list->count() > self::FALLBACK_OPTIONS) {
            $list = collect();
        }
        if ($selected && ! $list->contains('id', (int) $selected) && ($e = Enquiry::withTrashed()->with(['party:id,name', 'prospect:id,name'])->find($selected))) {
            $list->prepend($e);
        }

        return $list->mapWithKeys(fn (Enquiry $e) => [$e->id => $e->enquiry_no.' · '.$e->clientName()]);
    }
}
