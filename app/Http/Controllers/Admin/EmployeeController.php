<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LookupController;
use App\Http\Requests\Admin\EmployeeRequest;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\User;
use App\Services\MasterService;
use App\Support\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Admin > Masters > Employee master (CRM changes, Sat 13/10/2026): list paged on the server (P1) and a form. */
class EmployeeController extends Controller
{
    public function __construct(private readonly MasterService $masters)
    {
    }

    public function index(Request $request): View
    {
        $q = LookupController::term($request);
        $status = in_array($request->query('status'), ['Active', 'Inactive'], true) ? $request->query('status') : null;
        $query = Employee::query()->with('user:id,name')
            ->when($status, fn ($w) => $w->where('status', $status))
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('name', 'like', LookupController::prefix($q))->orWhere('code', 'like', LookupController::prefix($q))));
        [$sort, $dir] = ListQuery::sort($query, $request, ['name' => 'name', 'code' => 'code']);

        return view('admin.employees.index', [
            'rows' => $query->paginate(ListQuery::perPage($request))->withQueryString(),
            'q' => $q, 'status' => $status, 'sort' => $sort, 'dir' => $dir,
        ]);
    }

    public function create(): View
    {
        return $this->form(new Employee(['status' => 'Active']));
    }

    public function edit(Employee $employee): View
    {
        return $this->form($employee);
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        $e = $this->masters->saveEmployee(null, $request->validatedForSave(), $request->user());

        return redirect()->route('admin.employees.edit', $e)->with('toast', 'Employee '.$e->name.' added');
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->masters->saveEmployee($employee, $request->validatedForSave(), $request->user());

        return redirect()->route('admin.employees.edit', $employee)->with('toast', 'Employee '.$employee->name.' saved');
    }

    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        $this->masters->deleteEmployee($employee, $request->user());

        return redirect()->route('admin.employees.index')->with('toast', 'Employee '.$employee->name.' deleted');
    }

    private function form(Employee $employee): View
    {
        $employee->loadMissing(['creator', 'updater']);
        return view('admin.employees.form', [
            'employee' => $employee,
            // Type-ahead "Login user" picker (decision A11); the select lists all only when there are few users.
            'users' => \App\Http\Controllers\Crm\QuotationController::pickerOptions(User::orderBy('name'), User::class, old('user_id', $employee->user_id)),
            'history' => ActivityLog::history($employee, 6),
        ]);
    }
}
