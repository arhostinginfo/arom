<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Models\Prospect;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/*
| Type-ahead lists for big pickers (decision A11): each returns LIMIT matches a page as [{id, label, sub}]
| (?q=…&page=n, for the select-with-search's "scroll for more"), so a page never loads a whole master.
| Matching is "starts with" so the name index is used on large tables (database review DB-M2, decision A11).
| Who may call each one is checked by its route.
*/
class LookupController extends Controller
{
    public const LIMIT = 20;

    public function customers(Request $request): JsonResponse
    {
        $q = self::term($request);

        return response()->json(Party::customers()
            ->when($q !== '', fn ($w) => $w->where('name', 'like', self::prefix($q)))
            ->orderBy('name')->orderBy('id')->forPage(self::page(), self::LIMIT)->get(['id', 'name', 'gstin'])
            ->map(fn ($p) => ['id' => $p->id, 'label' => $p->name, 'sub' => $p->gstin]));
    }

    public function prospects(Request $request): JsonResponse
    {
        $q = self::term($request);

        return response()->json(Prospect::query()
            // Name, or the phone when digits are typed: an OR on the unindexed phone made every name search read
            // the whole table (picker speed check, 18/10/2026).
            ->when($q !== '' && ! preg_match('/^[\d +()-]+$/', $q), fn ($w) => $w->where('name', 'like', self::prefix($q)))
            ->when($q !== '' && preg_match('/^[\d +()-]+$/', $q), fn ($w) => $w->where('phone_1', 'like', self::prefix($q)))
            ->orderBy('name')->orderBy('id')->forPage(self::page(), self::LIMIT)->get(['id', 'name', 'market', 'phone_1'])
            ->map(fn ($p) => ['id' => $p->id, 'label' => $p->name, 'sub' => $p->market->value.' · '.$p->phone_1]));
    }

    /** Users for the Activity log "Done by" filter, including deleted users who still have log rows. */
    public function users(Request $request): JsonResponse
    {
        $q = self::term($request);

        return response()->json(User::withTrashed()
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('name', 'like', self::prefix($q))->orWhere('email', 'like', self::prefix($q))))
            ->orderBy('name')->orderBy('id')->forPage(self::page(), self::LIMIT)->get(['id', 'name', 'email', 'deleted_at'])
            ->map(fn ($u) => ['id' => $u->id, 'label' => $u->name, 'sub' => $u->email.($u->deleted_at ? ' · deleted' : '')]));
    }

    /** Active users with the Sales role (decision D22), for the quotation and report pickers. */
    public function salesPeople(Request $request): JsonResponse
    {
        $q = self::term($request);
        $query = \App\Http\Requests\Crm\QuotationRequest::salesPeopleQuery();
        if (! $query) {
            return response()->json([]);
        }

        return response()->json($query
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('users.name', 'like', self::prefix($q))->orWhere('users.email', 'like', self::prefix($q))))
            ->orderBy('users.name')->orderBy('users.id')->forPage(self::page(), self::LIMIT)->get(['users.id', 'users.name', 'users.email'])
            ->map(fn ($u) => ['id' => $u->id, 'label' => $u->name, 'sub' => $u->email]));
    }

    /** Active employees (Employee master) for Lead assigned to, Technical person and Received by (CRM changes). */
    public function employees(Request $request): JsonResponse
    {
        $q = self::term($request);

        return response()->json(\App\Models\Employee::active()
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('name', 'like', self::prefix($q))->orWhere('code', 'like', self::prefix($q))))
            ->orderBy('name')->orderBy('id')->forPage(self::page(), self::LIMIT)->get(['id', 'name', 'code', 'designation'])
            ->map(fn ($e) => ['id' => $e->id, 'label' => $e->name, 'sub' => trim($e->code.' · '.$e->designation, ' ·')]));
    }

    /** Active cities (City master) for the Enquiry's City field, first 20 starting with the typed text. */
    public function cities(Request $request): JsonResponse
    {
        $q = self::term($request);

        return response()->json(\App\Models\City::where('is_active', true)
            ->when($q !== '', fn ($w) => $w->where('name', 'like', self::prefix($q)))
            ->orderBy('name')->orderBy('id')->forPage(self::page(), self::LIMIT)->get(['id', 'name'])
            ->map(fn ($c) => ['id' => $c->id, 'label' => $c->name, 'sub' => null]));
    }

    /** Login users for the Employee master's "Login user" field. */
    public function loginUsers(Request $request): JsonResponse
    {
        $q = self::term($request);

        return response()->json(User::query()
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('name', 'like', self::prefix($q))->orWhere('email', 'like', self::prefix($q))))
            ->orderBy('name')->orderBy('id')->forPage(self::page(), self::LIMIT)->get(['id', 'name', 'email'])
            ->map(fn ($u) => ['id' => $u->id, 'label' => $u->name, 'sub' => $u->email]));
    }

    /**
     * Document number search that can use the indexes (database review DB-C3): typed digits find that
     * number in the last three financial years ("12" finds ENQ/26-27/0012 and ENQ/25-26/0012); other text
     * matches the start of the number ("ENQ/26-27/00").
     */
    public static function orNumber($w, string $column, string $prefix, string $q)
    {
        if (preg_match('/^\d{1,6}$/', $q)) {
            $codes = \App\Models\FinancialYear::where('starts_on', '<=', now())->orderByDesc('starts_on')->limit(3)->pluck('code');

            return $w->orWhereIn($column, $codes->map(fn ($c) => sprintf('%s/%s/%04d', $prefix, $c, (int) $q))->all());
        }

        return $w->orWhere($column, 'like', self::prefix($q));
    }

    /** Client name starts with the typed text: prospect or customer, through id lists that use the name indexes. */
    public static function orClient($w, string $q)
    {
        return $w->orWhereIn('prospect_id', Prospect::select('id')->where('name', 'like', self::prefix($q)))
            ->orWhereIn('party_id', Party::select('id')->where('name', 'like', self::prefix($q)));
    }

    /** "abc%" with LIKE wildcards in the typed text escaped. */
    public static function prefix(string $q): string
    {
        return addcslashes($q, '%_\\').'%';
    }

    /** "%abc%" with LIKE wildcards in the typed text escaped, for the entry searches. */
    public static function contains(string $q): string
    {
        return '%'.self::prefix($q);
    }

    /** Page asked for by a picker's "scroll for more": 1 to 50. */
    public static function page(): int
    {
        return max(1, min(50, (int) request()->query('page', 1)));
    }

    public static function term(Request $request): string
    {
        return mb_substr(trim((string) $request->query('q')), 0, 100);
    }
}
