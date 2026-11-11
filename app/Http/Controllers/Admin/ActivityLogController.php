<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\ListQuery;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

// Read-only list of every logged operation, filtered and paged on the server (decision D23).
class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        // Dates are checked like a form field: 31/02/2026 or "abc" shows an error instead of being
        // rolled over or dropped (user test B5). A date with an error is not applied.
        $v = Validator::make($request->query(), [
            'from' => ['nullable', 'date_format:d/m/Y'],
            'to' => ['nullable', 'date_format:d/m/Y'],
        ], [
            'from.date_format' => 'Enter From date as a real date, dd/mm/yyyy.',
            'to.date_format' => 'Enter To date as a real date, dd/mm/yyyy.',
        ]);
        $errors = $v->errors();
        $from = $errors->has('from') ? null : $this->date($request->query('from'));
        $to = $errors->has('to') ? null : $this->date($request->query('to'));
        if ($from && $to && $to->lt($from)) {
            $errors->add('to', 'To date must be on or after From date.');
            $to = null;
        }
        $q = trim((string) $request->query('q'));

        $query = ActivityLog::query()
            ->when($request->filled('action'), fn ($w) => $w->where('action', (string) $request->query('action')))
            ->when($request->filled('user'), fn ($w) => $w->where('user_id', (int) $request->query('user')))
            ->when($from, fn ($w) => $w->where('created_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($w) => $w->where('created_at', '<=', $to->copy()->endOfDay()))
            // Typed % and _ are matched literally, not as LIKE wildcards.
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('description', 'like', '%'.addcslashes($q, '%_\\').'%')->orWhere('subject_label', 'like', '%'.addcslashes($q, '%_\\').'%')));
        // The log grows by millions of rows, so it pages with Previous / Next and never counts the
        // whole table (database review DB-M3, rule P1). Newest first; the id order is the time order.
        [$sort, $dir] = ListQuery::sort($query, $request, ['date' => 'id'], 'desc');
        $logs = $query->simplePaginate(ListQuery::perPage($request))->withQueryString();

        return view('admin.activity.index', [
            'logs' => $logs,
            'sort' => $sort,
            'dir' => $dir,
            'actions' => ActivityLog::ACTIONS,
            'users' => $this->userOptions($request->query('user')),
        ])->withErrors($errors);
    }

    public function show(ActivityLog $log): View
    {
        return view('admin.activity.show', ['log' => $log]);
    }

    /**
     * "Done by" is a type-ahead picker (decision A11). Without JavaScript its select lists every user
     * when there are at most 200, otherwise only the chosen one.
     */
    private function userOptions($selected)
    {
        $users = User::withTrashed()->orderBy('name')->limit(201)->get(['id', 'name']);
        if ($users->count() > 200) {
            $users = User::withTrashed()->whereKey((int) $selected)->get(['id', 'name']);
        }

        return $users;
    }

    // Dates are typed as dd/mm/yyyy (OTHER POINTS A18).
    private function date(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }
        try {
            return Carbon::createFromFormat('d/m/Y', $value, config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }
}
