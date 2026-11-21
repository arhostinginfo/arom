<?php

namespace App\Http\Controllers\Amc;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LookupController;
use App\Http\Requests\Amc\AmcMasterRequest;
use App\Services\Amc\AmcMasterService;
use App\Support\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * AMC › Masters: Business segment, Planter, Plant and AMC type (21/11/2026). One shared screen like the Sales masters:
 * a list paged and searched on the server (rule P1) with the add / edit form beside it. Each master has its own routes
 * (amc.masters.<key>.*), so the menu marks the right one.
 */
class AmcMasterController extends Controller
{
    public function __construct(private readonly AmcMasterService $masters)
    {
    }

    public function show(Request $request, string $master): View
    {
        $cfg = AmcMasterService::config($master);
        $q = LookupController::term($request);
        $status = in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : null;
        $query = $cfg['model']::query()
            ->when($q !== '', fn ($w) => $w->where('name', 'like', LookupController::prefix($q)))
            ->when($status, fn ($w) => $w->where('is_active', $status === 'active'));
        [$sort, $dir] = ListQuery::sort($query, $request, ['name' => 'name']);
        $editing = $request->filled('edit') ? $cfg['model']::find((int) $request->query('edit')) : null;

        return view('amc.masters.list', [
            'master' => $master,
            'cfg' => $cfg,
            'rows' => $query->paginate(ListQuery::perPage($request))->withQueryString(),
            'q' => $q, 'status' => $status, 'sort' => $sort, 'dir' => $dir,
            'editing' => $editing,
            'editingUsed' => $editing ? AmcMasterService::usedCount($cfg, $editing->id) : 0,
        ]);
    }

    public function store(AmcMasterRequest $request, string $master): RedirectResponse
    {
        $row = $this->masters->save($master, null, $request->validated(), $request->user());

        return redirect()->route("amc.masters.$master.index")->with('toast', $row->label().' added');
    }

    // Route parameters come before the route's default {master}, so the id is taken first and the master from the route.
    public function update(AmcMasterRequest $request, int $id): RedirectResponse
    {
        $master = (string) $request->route('master');
        $row = AmcMasterService::config($master)['model']::findOrFail($id);
        $this->masters->save($master, $row, $request->validated(), $request->user());

        return redirect()->route("amc.masters.$master.index", $request->only(['page', 'q', 'status', 'sort', 'dir', 'per_page']))->with('toast', $row->label().' saved');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $master = (string) $request->route('master');
        $row = AmcMasterService::config($master)['model']::findOrFail($id);
        $this->masters->delete($master, $row, $request->user());

        return redirect()->route("amc.masters.$master.index")->with('toast', $row->label().' deleted');
    }
}
