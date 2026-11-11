<?php

namespace App\Services;

use App\Enums\QuotationStatus;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\Quotation;
use App\Models\SiteVisit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
| Notifications & alerts (CRM changes, Sat 13/10/2026): follow-ups due (the red pop up), site visits due,
| quotations pending approval and POs pending. They show on every page, so (architecture review C1,
| database review DB-C2):
| - the three unit-wide counts are cached once per unit, stale-while-revalidate (fresh for 60 seconds,
|   then served while one request refreshes them, at most 5 minutes old);
| - follow-ups due are cached per employee, or per unit for Admin and users not linked to an employee,
|   and those keys are cleared after the commit of any change to a lead, so a saved follow-up shows at once.
| Nothing is cleared on every save of the unit.
*/
class CrmAlerts
{
    /** Due follow-ups kept with the count: 5 for the pop up, 10 for the dashboard list. */
    public const LIST_SIZE = 10;

    public const POPUP_SIZE = 5;

    private const TTL = [60, 300];

    public function for(User $user, ?Unit $unit): array
    {
        if (! $unit) {
            return ['total' => 0, 'items' => [], 'due' => [], 'dueCount' => 0];
        }
        $items = [];
        $due = null;
        if ($user->can('enquiry.view')) {
            $due = $this->due($user, $unit);
            $items[] = ['label' => 'Follow-ups due', 'count' => $due['count'], 'url' => route('crm.enquiries.list', ['view' => 'due']), 'tone' => 'err'];
        }
        $unitCounts = null;
        $counts = function () use (&$unitCounts, $unit) {
            return $unitCounts ??= $this->unitCounts($unit);
        };
        // Site visits due: the gate refuses site-visit.view while Site Visit / Requirement Gathering is switched off.
        if ($user->can('site-visit.view')) {
            $items[] = ['label' => 'Site visits due', 'count' => $counts()['visits'], 'url' => route('crm.site-visits.list', ['view' => 'due']), 'tone' => 'warn'];
        }
        if ($user->can('quotation-approval.edit')) {
            $items[] = ['label' => 'Quotations pending approval', 'count' => $counts()['approval'], 'url' => route('crm.quotations.list', ['view' => 'approval']), 'tone' => 'warn'];
        }
        // Quotations waiting for a PO: a CRM list, so only while CRM is on (01/11/2026); its rows open the quotation, whose
        // "Enter PO / agreement" opens CRM › PO / Agreement.
        // Tester question 1 (01/11/2026): it also needs Quotation view, as the quotation list it opens.
        if ($user->can('customer-po.view') && $user->can('quotation.view') && \App\Support\Modules::on('crm')) {
            $items[] = ['label' => 'PO pending', 'count' => $counts()['po'], 'url' => route('crm.quotations.list', ['view' => 'po-pending']), 'tone' => 'info'];
        }

        return [
            'total' => array_sum(array_column($items, 'count')),
            'items' => $items,
            'due' => array_slice($due['list'] ?? [], 0, self::POPUP_SIZE),
            'dueCount' => $due['count'] ?? 0,
        ];
    }

    /**
     * Follow-ups due for this user: count, overdue count and the first LIST_SIZE, soonest first.
     * The same list feeds the bell, the red pop up, the dashboard tile and Enquiry list › Follow-ups due.
     */
    public function due(User $user, Unit $unit): array
    {
        $employeeId = self::employeeId($user);

        $due = Cache::flexible(self::dueKey($unit->id, $employeeId), self::TTL, function () use ($user, $unit) {
            $query = self::dueQuery($user, $unit);
            $today = today()->toDateString();

            return [
                'count' => (clone $query)->count(),
                'overdue' => (clone $query)->where('next_action_date', '<', $today)->count(),
                'list' => $query->with(['party:id,name', 'prospect:id,name'])->orderBy('next_action_date')->orderBy('id')
                    ->limit(self::LIST_SIZE)->get()
                    ->map(fn (Enquiry $e) => [
                        'id' => $e->id,
                        'no' => $e->enquiry_no,
                        'client' => $e->clientName(),
                        'action' => $e->next_action,
                        'date' => $e->next_action_date->format(config('nursery.date_format')),
                        'late' => (int) $e->next_action_date->diffInDays(today()),
                    ])->all(),
            ];
        });

        // Links are built for this request, not cached: a cached link keeps the host and folder of whichever
        // request or background refresh built it (e.g. missing /nursery-app) (23/10/2026).
        $due['list'] = array_map(fn ($f) => $f + ['url' => route('crm.enquiries.show', $f['id'])], $due['list']);

        return $due;
    }

    /**
     * Follow-ups of the signed-in user's own leads when the user is linked to an employee in the Employee
     * master; Admin and users not linked to an employee see every lead of the unit.
     */
    public static function dueQuery(User $user, Unit $unit)
    {
        $employeeId = self::employeeId($user);

        return Enquiry::where('unit_id', $unit->id)->due()
            ->when($employeeId, fn ($q) => $q->where('assigned_to', $employeeId));
    }

    /**
     * After the change to a lead commits, clear the due follow-ups of its unit and of the employees it was
     * and is assigned to, so the person who saved sees the change at once.
     */
    public static function forgetDue(int $unitId, array $employeeIds): void
    {
        DB::afterCommit(function () use ($unitId, $employeeIds) {
            Cache::forget(self::dueKey($unitId, null));
            foreach (array_unique(array_filter($employeeIds)) as $id) {
                Cache::forget(self::dueKey($unitId, (int) $id));
            }
        });
    }

    private function unitCounts(Unit $unit): array
    {
        // The site visit switch is part of the key, so switching it back on never shows a count cached while it was off.
        $visits = \App\Support\Modules::on('site_visit');

        return Cache::flexible('crm.alerts.unit.'.$unit->id.'.'.today()->format('Ymd').($visits ? '' : '.nv'), self::TTL, fn () => [
            'visits' => $visits ? SiteVisit::where('unit_id', $unit->id)->due()->count() : 0,
            'approval' => Quotation::where('unit_id', $unit->id)->latestRevisions()->where('status', QuotationStatus::InternalApproval->value)->count(),
            'po' => Quotation::where('unit_id', $unit->id)->poPending()->count(),
        ]);
    }

    private static function employeeId(User $user): ?int
    {
        return $user->isAdmin() ? null : Employee::where('user_id', $user->id)->value('id');
    }

    private static function dueKey(int $unitId, ?int $employeeId): string
    {
        return 'crm.due.'.$unitId.'.'.($employeeId ?: 'all').'.'.today()->format('Ymd');
    }
}
