<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LookupController;
use App\Http\Requests\Crm\ImportRequest;
use App\Http\Requests\Crm\ProspectRequest;
use App\Models\ActivityLog;
use App\Models\Prospect;
use App\Services\ProspectService;
use App\Support\Csv;
use App\Support\WorkContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

// CRM > Prospect. One entry shown as a full form with an Entry selector and Add new (decision D11).
class ProspectController extends Controller
{
    public function __construct(private readonly ProspectService $prospects)
    {
    }

    /** Opens the newest prospect matching the search, or a blank form when there is none. */
    public function index(Request $request): View|RedirectResponse
    {
        // The entry picker's no-JavaScript form sends the chosen entry as ?id=.
        if ($request->filled('id')) {
            return redirect()->route('crm.prospects.show', (int) $request->query('id'));
        }
        $first = $this->search($request)->first();

        return $this->form($request, $first ?? new Prospect(['market' => 'Domestic']));
    }

    public function show(Request $request, Prospect $prospect): View
    {
        return $this->form($request, $prospect);
    }

    public function create(Request $request): View
    {
        return $this->form($request, new Prospect(['market' => 'Domestic']));
    }

    public function store(ProspectRequest $request): RedirectResponse
    {
        $p = $this->prospects->create($request->validated(), $request->user(), WorkContext::unit());

        return redirect()->route('crm.prospects.show', $p)->with('toast', 'Prospect '.$p->name.' added');
    }

    /** "Add" next to Prospect on the quotation page (CRM H9): saves and returns the new prospect. */
    public function quickStore(ProspectRequest $request): JsonResponse
    {
        $p = $this->prospects->create($request->validated(), $request->user(), WorkContext::unit());

        return response()->json(['id' => $p->id, 'name' => $p->name, 'message' => $p->name.' added and selected'], 201);
    }

    public function update(ProspectRequest $request, Prospect $prospect): RedirectResponse
    {
        $this->prospects->update($prospect, $request->validated(), $request->user());

        return redirect()->route('crm.prospects.show', $prospect)->with('toast', 'Prospect '.$prospect->name.' saved');
    }

    public function destroy(Request $request, Prospect $prospect): RedirectResponse
    {
        $this->prospects->delete($prospect, $request->user());

        return redirect()->route('crm.prospects.index')->with('toast', 'Prospect '.$prospect->name.' deleted');
    }

    /**
     * Download CSV: every prospect, in the import layout so the file can be edited and imported again.
     * Columns hidden in Admin > Page setup are left out of the download and the template.
     * Streamed 1,000 rows at a time by id, in the order they were added (DB-M7, decision C13).
     */
    public function export(): StreamedResponse
    {
        $rows = Prospect::query()->lazyById(1000)->map(fn (Prospect $p) => $this->prospects->csvRow($p));

        return Csv::download('prospects-'.now()->format('Y-m-d').'.csv', array_values(ProspectService::columns()), $rows);
    }

    public function template(): StreamedResponse
    {
        $sample = [
            'name' => 'Orion Agro Equipment', 'market' => 'Domestic', 'contact_person' => 'Sanjay Patil', 'position' => 'Purchase manager',
            'phone_1' => '+91 98220 11111', 'email' => 'sanjay@orion.example', 'gstin' => '27AAACO1234F1Z5',
            'industry_type' => 'Agriculture machinery', 'address' => 'MIDC Chakan, Pune',
        ];
        $columns = ProspectService::columns();

        return Csv::download('prospect-import-template.csv', array_values($columns), [
            array_map(fn ($field) => $sample[$field] ?? '', array_keys($columns)),
        ]);
    }

    public function import(ImportRequest $request): RedirectResponse
    {
        $rows = Csv::read($request->file('file'), ['Prospect name', 'Domestic / Export', 'Contact no 1']);
        $count = $this->prospects->import($rows, $request->user(), WorkContext::unit());

        return redirect()->route('crm.prospects.index')->with('toast', $count.' prospect(s) imported');
    }

    /** Entry picker (decision UX2): latest 20 matching a name, contact, phone or email. */
    public function lookup(Request $request): JsonResponse
    {
        return response()->json($this->search($request)->forPage(LookupController::page(), LookupController::LIMIT)->get(['id', 'name', 'market', 'phone_1'])->map(fn (Prospect $p) => [
            'id' => $p->id,
            'label' => $p->name,
            'sub' => $p->market->value.' · '.$p->phone_1,
            'url' => route('crm.prospects.show', $p),
        ]));
    }

    private function search(Request $request)
    {
        $q = LookupController::term($request);
        // Typed % and _ are matched literally, not as LIKE wildcards.
        $like = LookupController::contains($q);

        return Prospect::query()
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s
                ->where('name', 'like', $like)
                ->orWhere('contact_person', 'like', $like)
                ->orWhere('phone_1', 'like', $like)
                ->orWhere('email', 'like', $like)))
            ->latest('id');
    }

    private function form(Request $request, Prospect $prospect): View
    {
        $entries = $this->search($request)->limit(50)->get(['id', 'name', 'phone_1']);
        if ($prospect->exists && ! $entries->contains('id', $prospect->id)) {
            $entries->prepend($prospect);
        }
        $prospect->loadMissing(['creator', 'updater']);

        return view('crm.prospects.form', [
            'prospect' => $prospect,
            'entries' => $entries,
            // Counting a large table on every open is slow (DB-S3), so the count is cached for a minute.
            'total' => Cache::remember('crm.prospects.count', 60, fn () => Prospect::count()),
            'q' => trim((string) $request->query('q')),
            'history' => ActivityLog::history($prospect, 6),
            // Contacts / POC of this prospect.
            'contactBox' => $prospect->exists ? ContactController::box(null, $prospect->id, 'prospect:'.$prospect->id) : null,
        ]);
    }
}
