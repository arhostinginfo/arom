<?php

namespace App\Http\Controllers\Amc;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LookupController;
use App\Http\Requests\Amc\AmcEmployeeRequest;
use App\Http\Requests\Amc\AmcPhotoRequest;
use App\Models\ActivityLog;
use App\Models\Amc\AmcEmployee;
use App\Services\Amc\AmcEmployeeService;
use App\Services\Amc\AmcFiles;
use App\Support\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** AMC › AMC Employee master (21/11/2026): list paged, searched and filtered on the server, form with the photo. */
class AmcEmployeeController extends Controller
{
    public function __construct(private readonly AmcEmployeeService $employees)
    {
    }

    public function index(Request $request): View
    {
        $q = LookupController::term($request);
        $role = in_array($request->query('role'), config('amc.employee_roles'), true) ? $request->query('role') : null;
        $status = in_array($request->query('status'), ['Active', 'Inactive'], true) ? $request->query('status') : null;
        $query = AmcEmployee::query()
            ->when($role, fn ($w) => $w->where('role', $role))
            ->when($status, fn ($w) => $w->where('status', $status))
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('name', 'like', LookupController::prefix($q))
                ->orWhere('code', 'like', LookupController::prefix(strtoupper($q)))->orWhere('mobile', 'like', LookupController::prefix($q))));
        [$sort, $dir] = ListQuery::sort($query, $request, ['name' => 'name', 'code' => 'code']);

        return view('amc.employees.index', [
            'rows' => $query->paginate(ListQuery::perPage($request))->withQueryString(),
            'q' => $q, 'role' => $role, 'status' => $status, 'sort' => $sort, 'dir' => $dir,
        ]);
    }

    public function create(): View
    {
        return $this->form(new AmcEmployee(['role' => AmcEmployee::GARDENER, 'status' => 'Active']));
    }

    public function edit(AmcEmployee $employee): View
    {
        return $this->form($employee);
    }

    public function store(AmcEmployeeRequest $request): RedirectResponse
    {
        $e = $this->employees->save(null, $request->safe()->except('photo'), $request->file('photo'), $request->user());

        return redirect()->route('amc.employees.edit', $e)->with('toast', 'AMC employee '.$e->label().' added');
    }

    public function update(AmcEmployeeRequest $request, AmcEmployee $employee): RedirectResponse
    {
        $this->employees->save($employee, $request->safe()->except('photo'), $request->file('photo'), $request->user());

        return redirect()->route('amc.employees.edit', $employee)->with('toast', 'AMC employee '.$employee->name.' saved');
    }

    public function destroy(Request $request, AmcEmployee $employee): RedirectResponse
    {
        $this->employees->delete($employee, $request->user());

        return redirect()->route('amc.employees.index')->with('toast', 'AMC employee '.$employee->label().' deleted');
    }

    /** Replace the photo only: from the Employee master or the contract page (back to where it was sent from). */
    public function photo(AmcPhotoRequest $request, AmcEmployee $employee): RedirectResponse
    {
        $this->employees->replacePhoto($employee, $request->file('photo'), $request->user());

        return back()->with('toast', 'Photo of '.$employee->name.' replaced');
    }

    /** The photo from the private disk; who may see it is checked by the route. */
    public function showPhoto(AmcEmployee $employee): Response
    {
        abort_unless($employee->photo_path && AmcFiles::disk()->exists($employee->photo_path), 404);

        return AmcFiles::disk()->response($employee->photo_path, $employee->code.'.jpg', ['Cache-Control' => 'private, max-age=300']);
    }

    private function form(AmcEmployee $e): View
    {
        return view('amc.employees.form', [
            'e' => $e,
            'nextCode' => $e->exists ? null : $this->employees->nextCode(),
            'history' => ActivityLog::history($e),
        ]);
    }
}
