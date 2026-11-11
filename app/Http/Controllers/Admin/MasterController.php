<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LookupController;
use App\Http\Requests\Admin\MasterRequest;
use App\Services\MasterService;
use App\Support\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/*
| Admin > Masters. The CRM masters (CRM changes, Sat 13/10/2026) are built: Source, Category, Industry
| type, City and the Employee master. The masters of modules not built yet still open their sample screens.
*/
class MasterController extends Controller
{
    /** Masters of later modules, in the prototype's order (its sample screen number). */
    public const SAMPLES = ['Currency/Country' => 2, 'Bank master' => 7];

    public function __construct(private readonly MasterService $masters)
    {
    }

    public function index(): View
    {
        $counts = [];
        foreach (MasterService::LISTS as $key => $cfg) {
            $counts[$key] = $cfg['model']::count();
        }

        return view('admin.masters.index', [
            'lists' => MasterService::LISTS,
            'counts' => $counts,
            'employees' => \App\Models\Employee::count(),
            // Masters of the Sales module (15/10/2026).
            'salesLists' => config('nursery.sales_masters'),
            'salesCounts' => collect(config('nursery.sales_masters'))->map(fn ($cfg) => $cfg['model']::count())->all(),
            'parties' => \App\Models\Party::count(),
            'items' => \App\Models\Item::count(),
            'samples' => self::SAMPLES,
        ]);
    }

    /** One master: its entries paged and searched on the server (P1), with the add / edit form beside them. */
    public function show(Request $request, string $master): View
    {
        $cfg = MasterService::list($master);
        $q = LookupController::term($request);
        $query = $cfg['model']::query()->when($q !== '', fn ($w) => $w->where('name', 'like', LookupController::prefix($q)));
        [$sort, $dir] = ListQuery::sort($query, $request, ['order' => 'sort_order', 'name' => 'name']);
        $editing = $request->filled('edit') ? $cfg['model']::find((int) $request->query('edit')) : null;

        return view('admin.masters.list', [
            'master' => $master,
            'cfg' => $cfg,
            'rows' => $query->paginate(ListQuery::perPage($request))->withQueryString(),
            'q' => $q,
            'sort' => $sort,
            'dir' => $dir,
            'editing' => $editing,
            'nextOrder' => (int) $cfg['model']::max('sort_order') + 1,
        ]);
    }

    public function store(MasterRequest $request, string $master): RedirectResponse
    {
        $row = $this->masters->saveListItem($master, null, $request->validated(), $request->user());

        return redirect()->route('admin.masters.show', $master)->with('toast', $row->name.' added');
    }

    public function update(MasterRequest $request, string $master, int $id): RedirectResponse
    {
        $row = MasterService::list($master)['model']::findOrFail($id);
        $this->masters->saveListItem($master, $row, $request->validated(), $request->user());

        return redirect()->route('admin.masters.show', [$master] + $request->only(['page', 'q', 'sort', 'dir', 'per_page']))->with('toast', $row->name.' saved');
    }

    public function destroy(Request $request, string $master, int $id): RedirectResponse
    {
        $row = MasterService::list($master)['model']::findOrFail($id);
        $this->masters->deleteListItem($master, $row, $request->user());

        return redirect()->route('admin.masters.show', $master)->with('toast', $row->name.' deleted');
    }
}
