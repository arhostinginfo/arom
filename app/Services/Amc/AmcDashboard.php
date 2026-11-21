<?php

namespace App\Services\Amc;

use App\Models\Amc\AmcContract;
use App\Models\FinancialYear;
use Illuminate\Support\Carbon;

/**
 * AMC dashboard figures (21/11/2026), read from the AMC tables only.
 * Monthly AMC revenue: each Active contract's amount is spread evenly over the calendar months its period touches
 * (1 April to 31 March = 12 months; 15 April to 14 April next year = 13), at least 1, so the months add up to the
 * amount. Lists are capped (LIST_LIMIT) with a link to the AMC list for the rest.
 */
class AmcDashboard
{
    public const LIST_LIMIT = 10;

    public function figures(FinancialYear $fy, ?Carbon $today = null): array
    {
        $today ??= today();
        // Upper bounds are "before the next day", so dates stored with a time (SQLite) compare as MySQL DATEs do.
        $tomorrow = $today->copy()->addDay()->toDateString();
        $afterRenew = $today->copy()->addDays((int) config('amc.renew_days') + 1)->toDateString();
        $active = fn () => AmcContract::query()->where('status', AmcContract::ACTIVE);

        $months = $this->months($fy);
        $revenue = array_fill_keys(array_keys($months), 0.0);
        $active()->where('start_date', '<', $fy->ends_on->copy()->addDay()->toDateString())->where('end_date', '>=', $fy->starts_on->toDateString())
            ->select(['id', 'start_date', 'end_date', 'amount'])
            ->chunkById(500, function ($rows) use (&$revenue) {
                foreach ($rows as $c) {
                    $first = $c->start_date->copy()->startOfMonth();
                    $last = $c->end_date->copy()->startOfMonth();
                    $span = max(1, ($last->year - $first->year) * 12 + $last->month - $first->month + 1);
                    $share = (float) $c->amount / $span;
                    for ($m = $first->copy(); $m <= $last; $m->addMonth()) {
                        if (array_key_exists($m->format('Y-m'), $revenue)) {
                            $revenue[$m->format('Y-m')] += $share;
                        }
                    }
                }
            });

        $renewals = $active()->where('end_date', '>=', $today->toDateString())->where('end_date', '<', $afterRenew);
        $overdue = $active()->where('end_date', '<', $today->toDateString());
        $newOrders = AmcContract::query()->where('created_at', '>=', $today->copy()->subDays(60)->startOfDay());
        $with = ['client:id,name', 'type:id,name'];

        return [
            'activeClients' => $active()->where('start_date', '<', $tomorrow)->where('end_date', '>=', $today->toDateString())
                ->distinct()->count('amc_client_id'),
            'thisMonth' => $revenue[$today->format('Y-m')] ?? null,
            'thisMonthLabel' => $today->format('F Y'),
            'revenue' => collect($months)->map(fn ($label, $key) => ['month' => $label, 'amount' => $revenue[$key]])->values()->all(),
            'revenueTotal' => array_sum($revenue),
            'renewalCount' => (clone $renewals)->count(),
            'renewals' => (clone $renewals)->with($with)->orderBy('end_date')->orderBy('id')->limit(self::LIST_LIMIT)->get(),
            'overdueCount' => (clone $overdue)->count(),
            'overdue' => (clone $overdue)->with($with)->orderBy('end_date')->orderBy('id')->limit(self::LIST_LIMIT)->get(),
            'newCount' => (clone $newOrders)->count(),
            'newOrders' => (clone $newOrders)->with($with)->latest('created_at')->latest('id')->limit(self::LIST_LIMIT)->get(),
        ];
    }

    /** "2026-04" => "Apr 2026" for the twelve months of the financial year. */
    private function months(FinancialYear $fy): array
    {
        $out = [];
        for ($m = $fy->starts_on->copy()->startOfMonth(), $i = 0; $i < 12; $i++, $m->addMonth()) {
            $out[$m->format('Y-m')] = $m->format('M Y');
        }

        return $out;
    }
}
