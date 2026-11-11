<?php

namespace App\Http\Controllers\Crm;

use App\Enums\EnquiryStatus;
use App\Enums\LeadRating;
use App\Enums\ProspectType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\LookupController;
use App\Http\Requests\Crm\ActivityRequest;
use App\Http\Requests\Crm\EnquiryRequest;
use App\Models\ActivityLog;
use App\Models\City;
use App\Models\Contact;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\IndustryType;
use App\Models\LeadSource;
use App\Models\Party;
use App\Models\Prospect;
use App\Models\ServiceCategory;
use App\Models\Unit;
use App\Services\CrmAlerts;
use App\Services\EnquiryService;
use App\Services\PageSetupService;
use App\Support\ListQuery;
use App\Support\WorkContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/*
| CRM > Enquiry (CRM changes, Sat 13/10/2026). One entry as a full form with an Entry selector and
| Add new (decision D11), its follow-ups, documents and the workflow from lead to PO. Enquiries are
| listed for the unit chosen on the top bar, across financial years, because a lead can run past 31 March.
*/
class EnquiryController extends Controller
{
    public function __construct(private readonly EnquiryService $enquiries)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        if ($request->filled('id')) {
            return redirect()->route('crm.enquiries.show', (int) $request->query('id'));
        }

        return $this->form($request, $this->search($request)->first() ?? $this->blank());
    }

    public function show(Request $request, Enquiry $enquiry): View
    {
        $this->authorizeUnit($enquiry);

        return $this->form($request, $enquiry);
    }

    public function create(Request $request): View
    {
        return $this->form($request, $this->blank());
    }

    public function store(EnquiryRequest $request): RedirectResponse
    {
        $e = $this->enquiries->create($request->validated(), $request->user(), $this->unit());

        return redirect()->route('crm.enquiries.show', $e)->with('toast', 'Enquiry '.$e->enquiry_no.' added');
    }

    public function update(EnquiryRequest $request, Enquiry $enquiry): RedirectResponse
    {
        $this->authorizeUnit($enquiry);
        $this->enquiries->update($enquiry, $request->validated(), $request->user());

        return redirect()->route('crm.enquiries.show', $enquiry)->with('toast', 'Enquiry '.$enquiry->enquiry_no.' saved');
    }

    public function destroy(Request $request, Enquiry $enquiry): RedirectResponse
    {
        $this->authorizeUnit($enquiry);
        $this->enquiries->delete($enquiry, $request->user());

        return redirect()->route('crm.enquiries.index')->with('toast', 'Enquiry '.$enquiry->enquiry_no.' deleted');
    }

    /** Follow-up & activity management: record a Call, Email or WhatsApp with the next action. */
    public function storeActivity(ActivityRequest $request, Enquiry $enquiry): RedirectResponse
    {
        $this->authorizeUnit($enquiry);
        $this->enquiries->addActivity($enquiry, $request->validated(), $request->user());

        return redirect()->to(route('crm.enquiries.show', $enquiry).'#followups')->with('toast', 'Follow-up recorded on '.$enquiry->enquiry_no);
    }

    /**
     * Enquiry list with the dashboard and alert filters (all, follow-ups due, hot leads, closing this month,
     * open, lost, PO received). Paged on the server, 25 a page by default (rule P1).
     */
    public function list(Request $request): View
    {
        $view = $request->query('view');
        $views = self::views();
        if (! isset($views[$view])) {
            $view = 'all';
        }
        $q = LookupController::term($request);
        // Follow-ups due are the same list as the red pop up: the user's own leads when linked to an employee.
        $query = ($view === 'due' ? CrmAlerts::dueQuery($request->user(), $this->unit()) : Enquiry::query()->where('unit_id', $this->unit()->id))
            ->with(['party:id,name', 'prospect:id,name', 'assignee:id,name', 'source:id,name']);
        match ($view) {
            'hot' => $query->open()->where('lead_rating', LeadRating::Hot->value),
            'closing' => $query->open()->whereBetween('expected_closing_date', [today()->startOfMonth()->toDateString(), today()->endOfMonth()->toDateString().' 23:59:59']),
            'open' => $query->open(),
            'lost' => $query->where('status', EnquiryStatus::Lost->value),
            'po' => $query->where('status', EnquiryStatus::PoReceived->value),
            default => null,
        };
        $query->when($q !== '', fn ($w) => $w->where(fn ($s) => LookupController::orClient(LookupController::orNumber($s, 'enquiry_no', 'ENQ', $q), $q)));

        // Follow-ups due list the most overdue first, Closing this month the soonest first (UX review S2).
        $columns = ['date' => 'enquiry_date', 'next' => 'next_action_date', 'closing' => 'expected_closing_date', 'no' => 'enquiry_no'];
        $first = ['due' => 'next', 'closing' => 'closing'][$view] ?? 'date';
        $columns = [$first => $columns[$first]] + $columns;
        [$sort, $dir] = ListQuery::sort($query, $request, $columns, $view === 'due' || $view === 'closing' ? 'asc' : 'desc');

        return view('crm.enquiries.list', [
            'rows' => $query->paginate(ListQuery::perPage($request))->withQueryString(),
            'view' => $view,
            'views' => $views,
            'q' => $q,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public static function views(): array
    {
        return ['all' => 'All', 'open' => 'Open leads', 'due' => 'Follow-ups due', 'hot' => 'Hot leads', 'closing' => 'Closing this month', 'po' => 'PO received', 'lost' => 'Lost'];
    }

    /**
     * An existing customer's details for the Enquiry form (Sat 18/10/2026): picking a customer fills contact, phone,
     * address, industry, city and website. Taken from the customer's billing address, then its main contact
     * (decision maker first), then the customer's latest enquiry in the user's units. Every key is returned, empty when
     * nothing is known, so choosing another customer replaces what the last one filled. The boxes stay editable.
     * A Prospect's details are no longer shown on the enquiry (22/10/2026), so only ?party= is served.
     */
    public function clientInfo(Request $request): JsonResponse
    {
        $partyId = $request->integer('party') ?: null;
        abort_unless($partyId, 404);
        $client = Party::customers()->find($partyId);
        abort_unless($client, 404);

        $last = Enquiry::query()->whereIn('unit_id', WorkContext::unitIds())->where('party_id', $client->id)
            ->with(['industryType:id,name', 'city:id,name'])->latest('id')->first();
        $contact = Contact::query()->where('party_id', $client->id)->orderByDesc('is_decision_maker')->orderBy('id')->first();
        $pick = fn (...$values) => collect($values)->first(fn ($v) => $v !== null && trim((string) $v) !== '') ?? '';

        $industryType = $last?->industryType;

        return response()->json([
            'contact_name' => $pick($contact?->name, $last?->contact_name),
            'contact_no' => $pick($contact?->mobile, $last?->contact_no),
            'address' => $pick($client->billing_address, $last?->address),
            'industry' => $pick($last?->industry),
            'website' => $pick($last?->website),
            'industry_type_id' => $industryType ? (string) $industryType->id : '',
            'city' => $last?->city ? ['id' => (string) $last->city->id, 'label' => $last->city->name] : null,
        ]);
    }

    /** Entry picker (decision UX2) and the Enquiry picker of the quotation and site visit pages: latest 20 matches. */
    public function lookup(Request $request): JsonResponse
    {
        return response()->json($this->search($request)->forPage(LookupController::page(), LookupController::LIMIT)->get()->map(fn (Enquiry $e) => [
            'id' => $e->id,
            'label' => $e->enquiry_no.' · '.($e->clientName() ?: 'No client'),
            'sub' => $e->enquiry_date->format(config('nursery.date_format')).' · '.$e->status->value.($e->lead_rating ? ' · '.$e->lead_rating->value : ''),
            'url' => route('crm.enquiries.show', $e),
        ]));
    }

    private function search(Request $request)
    {
        return self::matching(Enquiry::query()->where('unit_id', WorkContext::unit()?->id), LookupController::term($request))
            ->with(['party:id,name', 'prospect:id,name'])
            ->latest('id');
    }

    /**
     * Enquiries matching an enquiry no, client name or contact no, all "starts with" so the indexes are used
     * (database review DB-C3). Also used by the site visit and PO searches.
     */
    public static function matching($query, string $q)
    {
        return $query->when($q !== '', fn ($w) => $w->where(fn ($s) => LookupController::orClient(LookupController::orNumber($s, 'enquiry_no', 'ENQ', $q), $q)
            ->orWhere('contact_no', 'like', LookupController::prefix($q))
            // A phone number typed any way ("9822000005", "+91 98220 00005") matches its digits (full test B3).
            ->when(preg_match('/^[\d\s+()-]{4,}$/', $q) && ($d = Enquiry::phoneDigits($q)) && strlen($d) >= 4,
                fn ($w) => $w->orWhere('contact_no_digits', 'like', LookupController::prefix(Enquiry::phoneDigits($q))))));
    }

    private function form(Request $request, Enquiry $enquiry): View
    {
        $entries = $this->search($request)->limit(50)->get();
        if ($enquiry->exists && ! $entries->contains('id', $enquiry->id)) {
            $entries->prepend($enquiry);
        }
        $enquiry->loadMissing(['party', 'prospect', 'creator', 'updater', 'assignee']);
        $keep = fn ($f) => $request->old($f, $enquiry->{$f});

        return view('crm.enquiries.form', [
            'enquiry' => $enquiry,
            'entries' => $entries,
            'q' => trim((string) $request->query('q')),
            'setup' => app(PageSetupService::class)->fields('enquiry'),
            'sources' => LeadSource::forPicker($keep('lead_source_id'))->get(['id', 'name']),
            'categories' => ServiceCategory::forPicker($keep('service_category_id'))->get(['id', 'name']),
            'industryTypes' => IndustryType::forPicker($keep('industry_type_id'))->get(['id', 'name']),
            // Type-ahead: the select lists every city only while there are at most 200 (UX review M7).
            'cities' => QuotationController::pickerOptions(City::forPicker($keep('city_id')), City::class, $keep('city_id')),
            'employees' => QuotationController::pickerOptions(Employee::active()->orderBy('name'), Employee::class, $keep('assigned_to')),
            'customers' => QuotationController::pickerOptions(Party::customers(), Party::class, $keep('party_id')),
            'prospects' => QuotationController::pickerOptions(Prospect::orderBy('name'), Prospect::class, $keep('prospect_id')),
            'hasCustomers' => Party::customers()->exists(),
            'prospectTypes' => ProspectType::values(),
            'ratings' => LeadRating::values(),
            'unit' => WorkContext::unit(),
            'history' => ActivityLog::history($enquiry, 6),
            // Follow-ups, newest first, paged on the server (rule P1).
            'activities' => $enquiry->exists ? $enquiry->activities()->with('creator:id,name')->latest('id')->simplePaginate(10, ['*'], 'fpage')->withQueryString() : null,
            'flow' => $enquiry->exists ? \App\Support\CrmFlow::steps($enquiry, $request->user()) : null,
            // Paged on the server, 10 a page (architecture review C3, code review M4).
            'docs' => \App\Services\DocumentService::box($enquiry),
            // Contacts / POC of the enquiry's client.
            'contactBox' => $enquiry->exists ? ContactController::box($enquiry->party_id, $enquiry->prospect_id, 'enquiry:'.$enquiry->id) : null,
        ]);
    }

    private function blank(): Enquiry
    {
        $e = new Enquiry(['enquiry_date' => today()]);
        $e->status = EnquiryStatus::Open;
        // The signed-in user's employee record is the usual assignee.
        $e->assigned_to = Employee::active()->where('user_id', auth()->id())->value('id');

        return $e;
    }

    private function unit(): Unit
    {
        $unit = WorkContext::unit();
        abort_unless($unit, 403, 'No unit is allowed for your user, so you cannot work with enquiries.');

        return $unit;
    }

    private function authorizeUnit(Enquiry $e): void
    {
        abort_unless(in_array($e->unit_id, WorkContext::unitIds(), true), 403);
    }
}
