<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LookupController;
use App\Http\Requests\Admin\SalesMasterRequest;
use App\Services\SalesMasterService;
use App\Support\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin › Masters › Tax master, HSN/SAC, UOM, Category (items) and Transporter (Sales module, 15/10/2026): one
 * shared screen, a list paged and searched on the server (rule P1) with the add / edit form beside it.
 */
class SalesMasterController extends Controller
{
    public function __construct(private readonly SalesMasterService $masters)
    {
    }

    public function show(Request $request, string $master): View
    {
        $cfg = SalesMasterService::config($master);
        $q = LookupController::term($request);
        $query = $cfg['model']::query()->when($q !== '', fn ($w) => $w->where($cfg['search'], 'like', LookupController::prefix($q)));
        [$sort, $dir] = ListQuery::sort($query, $request, [$cfg['search'] => $cfg['search']]);
        $options = [];
        foreach ($cfg['fields'] as $f => $field) {
            if ($field['type'] === 'select') {
                $options[$f] = $field['model']::query()->where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn ($m) => [$m->id => $m->label()])->all();
            }
        }

        return view('admin.sales-masters.list', [
            'master' => $master,
            'cfg' => $cfg,
            'rows' => $query->paginate(ListQuery::perPage($request))->withQueryString(),
            'q' => $q,
            'sort' => $sort,
            'dir' => $dir,
            'editing' => $request->filled('edit') ? $cfg['model']::find((int) $request->query('edit')) : null,
            'options' => $options,
        ]);
    }

    public function store(SalesMasterRequest $request, string $master): RedirectResponse
    {
        $row = $this->masters->save($master, null, $request->validated(), $request->user());

        return redirect()->route('admin.sales-masters.show', $master)->with('toast', $row->label().' added');
    }

    public function update(SalesMasterRequest $request, string $master, int $id): RedirectResponse
    {
        $row = SalesMasterService::config($master)['model']::findOrFail($id);
        $this->masters->save($master, $row, $request->validated(), $request->user());

        return redirect()->route('admin.sales-masters.show', [$master] + $request->only(['page', 'q', 'sort', 'dir', 'per_page']))->with('toast', $row->label().' saved');
    }

    public function destroy(Request $request, string $master, int $id): RedirectResponse
    {
        $row = SalesMasterService::config($master)['model']::findOrFail($id);
        $this->masters->delete($master, $row, $request->user());

        return redirect()->route('admin.sales-masters.show', $master)->with('toast', $row->label().' deleted');
    }
}
