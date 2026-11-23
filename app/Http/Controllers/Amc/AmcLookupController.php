<?php

namespace App\Http\Controllers\Amc;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LookupController;
use App\Models\Amc\AmcClient;
use App\Models\Amc\AmcEmployee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Type-ahead lists of the AMC pages (21/11/2026): AMC clients and AMC employees, 20 "starts with" matches a page as
 * [{id, label, sub}], the same shape as LookupController, for public/js/picker.js. Who may call each is checked by its route.
 */
class AmcLookupController extends Controller
{
    /** A plain dropdown lists every option only up to this many; above it the type-ahead does the search. */
    public const FALLBACK_OPTIONS = 200;

    public function clients(Request $request): JsonResponse
    {
        $q = LookupController::term($request);

        return response()->json(AmcClient::query()->with('vertical:id,name')
            ->when($q !== '', fn ($w) => $w->where('name', 'like', LookupController::prefix($q)))
            ->orderBy('name')->orderBy('id')->forPage(LookupController::page(), LookupController::LIMIT)->get(['id', 'name', 'amc_vertical_id'])
            ->map(fn ($c) => ['id' => $c->id, 'label' => $c->name, 'sub' => $c->vertical?->name]));
    }

    /** (22/11/2026) Active customer parties not yet in the AMC Client Inventory (the client being edited may keep its own). */
    public function parties(Request $request): JsonResponse
    {
        $q = LookupController::term($request);
        $keep = $request->integer('keep') ?: null;

        return response()->json(\App\Http\Requests\Amc\AmcClientRequest::customerParties()
            ->where(fn ($w) => $w->whereNotIn('id', AmcClient::query()->whereNotNull('party_id')->select('party_id'))->when($keep, fn ($k) => $k->orWhere('id', $keep)))
            ->when($q !== '', fn ($w) => $w->where('name', 'like', LookupController::prefix($q)))
            ->orderBy('name')->orderBy('id')->forPage(LookupController::page(), LookupController::LIMIT)->get(['id', 'name', 'gstin', 'type'])
            ->map(fn ($p) => ['id' => $p->id, 'label' => $p->name, 'sub' => $p->gstin ?: $p->type]));
    }

    /** Active AMC employees, of one role when ?role= is Operation Manager or Gardener. */
    public function employees(Request $request): JsonResponse
    {
        $q = LookupController::term($request);
        $role = in_array($request->query('role'), config('amc.employee_roles'), true) ? $request->query('role') : null;

        return response()->json(AmcEmployee::active()->when($role, fn ($w) => $w->where('role', $role))
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('name', 'like', LookupController::prefix($q))->orWhere('code', 'like', LookupController::prefix(strtoupper($q)))))
            ->orderBy('name')->orderBy('id')->forPage(LookupController::page(), LookupController::LIMIT)->get(['id', 'name', 'code', 'role'])
            ->map(fn ($e) => ['id' => $e->id, 'label' => $e->name, 'sub' => $e->code.' · '.$e->role]));
    }

    /** id => label for a picker's select: everything while there are at most FALLBACK_OPTIONS, plus the chosen row. */
    public static function options(Builder $query, $selected, callable $label): Collection
    {
        $list = (clone $query)->limit(self::FALLBACK_OPTIONS + 1)->get();
        $list = $list->count() > self::FALLBACK_OPTIONS ? collect() : $list;
        if ($selected && ! $list->contains('id', (int) $selected) && ($m = $query->getModel()->newQuery()->find($selected))) {
            $list->push($m);
        }

        return $list->mapWithKeys(fn ($m) => [$m->id => $label($m)]);
    }
}
