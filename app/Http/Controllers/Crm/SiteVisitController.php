<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LookupController;
use App\Http\Requests\Crm\SiteVisitRequest;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\SiteVisit;
use App\Services\SiteVisitService;
use App\Support\ListQuery;
use App\Support\WorkContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/*
| CRM > Site visit / requirement gathering (CRM changes, Sat 13/10/2026). One entry as a full form with
| an Entry selector and Add new (D11), its site photos, and a list of visits due for the alerts.
*/
class SiteVisitController extends Controller
{
    public function __construct(private readonly SiteVisitService $visits)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        if ($request->filled('id')) {
            return redirect()->route('crm.site-visits.show', (int) $request->query('id'));
        }

        return $this->form($request, $this->search($request)->first() ?? $this->blank($request));
    }

    public function show(Request $request, SiteVisit $siteVisit): View
    {
        $this->authorizeUnit($siteVisit);

        return $this->form($request, $siteVisit);
    }

    public function create(Request $request): View
    {
        return $this->form($request, $this->blank($request));
    }

    public function store(SiteVisitRequest $request): RedirectResponse
    {
        $v = $this->visits->create($request->validated(), $request->user());

        return redirect()->route('crm.site-visits.show', $v)->with('toast', 'Site visit saved');
    }

    public function update(SiteVisitRequest $request, SiteVisit $siteVisit): RedirectResponse
    {
        $this->authorizeUnit($siteVisit);
        $this->visits->update($siteVisit, $request->validated(), $request->user());

        return redirect()->route('crm.site-visits.show', $siteVisit)->with('toast', 'Site visit saved');
    }

    public function destroy(Request $request, SiteVisit $siteVisit): RedirectResponse
    {
        $this->authorizeUnit($siteVisit);
        $this->visits->delete($siteVisit, $request->user());

        return redirect()->route('crm.site-visits.index')->with('toast', 'Site visit deleted');
    }

    /** Site visits of the unit: all, or planned visits due (the "Site visit due" alert). Paged on the server (P1). */
    public function list(Request $request): View
    {
        $views = ['all' => 'All', 'due' => 'Visits due', 'planned' => 'Planned', 'done' => 'Done'];
        $view = isset($views[$request->query('view')]) ? $request->query('view') : 'all';
        $query = SiteVisit::where('unit_id', WorkContext::unit()?->id)->with(['enquiry.party:id,name', 'enquiry.prospect:id,name', 'technicalPerson:id,name']);
        match ($view) {
            'due' => $query->due(),
            'planned' => $query->where('status', 'Planned'),
            'done' => $query->where('status', 'Done'),
            default => null,
        };
        [$sort, $dir] = ListQuery::sort($query, $request, ['visit' => 'visit_at'], $view === 'due' ? 'asc' : 'desc');

        return view('crm.site-visits.list', [
            'rows' => $query->paginate(ListQuery::perPage($request))->withQueryString(),
            'view' => $view, 'views' => $views, 'sort' => $sort, 'dir' => $dir,
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        return response()->json($this->search($request)->forPage(LookupController::page(), LookupController::LIMIT)->get()->map(fn (SiteVisit $v) => [
            'id' => $v->id,
            'label' => $v->visit_at->format(config('nursery.datetime_format')).' · '.($v->enquiry?->enquiry_no ?? '').' · '.($v->enquiry?->clientName() ?? ''),
            'sub' => $v->status,
            'url' => route('crm.site-visits.show', $v),
        ]));
    }

    private function search(Request $request)
    {
        $q = LookupController::term($request);
        $unit = WorkContext::unit()?->id;

        // The visits of the enquiries that match (enquiry no, client or contact no, "starts with": DB-C3).
        return SiteVisit::query()->where('unit_id', $unit)
            ->with(['enquiry.party:id,name', 'enquiry.prospect:id,name'])
            ->when($q !== '', fn ($w) => $w->whereIn('enquiry_id', EnquiryController::matching(Enquiry::select('id')->where('unit_id', $unit), $q)))
            ->latest('id');
    }

    private function form(Request $request, SiteVisit $visit): View
    {
        $entries = $this->search($request)->limit(50)->get();
        if ($visit->exists && ! $entries->contains('id', $visit->id)) {
            $entries->prepend($visit);
        }
        $visit->loadMissing(['enquiry.party', 'enquiry.prospect', 'creator', 'updater']);

        return view('crm.site-visits.form', [
            'visit' => $visit,
            'entries' => $entries,
            'q' => trim((string) $request->query('q')),
            'enquiryOptions' => $this->enquiryOptions($request->old('enquiry_id', $visit->enquiry_id)),
            'employees' => QuotationController::pickerOptions(Employee::active()->orderBy('name'), Employee::class, $request->old('technical_employee_id', $visit->technical_employee_id)),
            // Paged on the server, 10 a page (architecture review C3, code review M4).
            'docs' => \App\Services\DocumentService::box($visit),
            'history' => ActivityLog::history($visit, 6),
        ]);
    }

    private function enquiryOptions($selected): Collection
    {
        $list = Enquiry::where('unit_id', WorkContext::unit()?->id)->open()->with(['party:id,name', 'prospect:id,name'])->latest('id')->limit(201)->get();
        if ($list->count() > 200) {
            $list = collect();
        }
        if ($selected && ! $list->contains('id', (int) $selected) && ($e = Enquiry::withTrashed()->with(['party:id,name', 'prospect:id,name'])->find($selected))) {
            $list->prepend($e);
        }

        return $list->mapWithKeys(fn (Enquiry $e) => [$e->id => $e->enquiry_no.' · '.$e->clientName()]);
    }

    /** A new visit, filled from the enquiry when opened from it (?enquiry=). */
    private function blank(Request $request): SiteVisit
    {
        $v = new SiteVisit(['status' => 'Planned', 'visit_at' => now()->addDay()->setTime(11, 0)]);
        if ($request->filled('enquiry') && ($e = Enquiry::where('unit_id', WorkContext::unit()?->id)->find((int) $request->query('enquiry')))) {
            $v->enquiry_id = $e->id;
            $v->poc = $e->contact_name ?: null; // the number stays on the Prospect, shown read-only (Sat 18/10/2026)
            $v->site_address = $e->address;
        }

        return $v;
    }

    private function authorizeUnit(SiteVisit $v): void
    {
        abort_unless(in_array($v->unit_id, WorkContext::unitIds(), true), 403);
    }
}
