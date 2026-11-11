<?php

namespace App\Services;

use App\Enums\LeadRating;
use App\Models\CustomerPo;
use App\Models\Enquiry;
use App\Models\FinancialYear;
use App\Models\LeadSource;
use App\Models\Quotation;
use App\Models\ServiceCategory;
use App\Models\Unit;
use App\Support\Charts;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * CRM dashboard (CRM changes, Sat 13/10/2026) for the unit on the top bar and the current financial year.
 * Every figure is one indexed, grouped query; the result is cached per unit for five minutes and
 * served stale-while-revalidate (fresh for a minute, at most 5 minutes old). Follow-ups due are not here:
 * they depend on who is looking and come from CrmAlerts::due, like the bell (UX review M6).
 */
class CrmDashboard
{
    public const LIST_SIZE = 10;

    /** Lead ageing buckets in days since the enquiry date. */
    public const AGEING = ['0 to 7 days' => [0, 7], '8 to 15 days' => [8, 15], '16 to 30 days' => [16, 30], '31 to 60 days' => [31, 60], 'Over 60 days' => [61, PHP_INT_MAX]];

    public function data(Unit $unit, FinancialYear $year): array
    {
        // Once per unit and year, stale-while-revalidate: fresh for a minute, then the last figures are served
        // while one request rebuilds them, so a busy unit never rebuilds on every view (architecture review C1).
        $key = 'crm.dashboard.'.$unit->id.'.'.$year->id.'.'.today()->format('Ymd');

        return Cache::flexible($key, [60, 300], fn () => $this->build($unit, $year));
    }

    private function build(Unit $unit, FinancialYear $year): array
    {
        $from = $year->starts_on->toDateString();
        // End of the day, so a date stored with a time (SQLite) on the last day still counts.
        $to = $year->ends_on->toDateString().' 23:59:59';
        $enquiries = fn () => Enquiry::where('unit_id', $unit->id);

        // Month by month over the financial year, April to March.
        $months = [];
        for ($m = $year->starts_on->copy()->startOfMonth(); $m->lte($year->ends_on); $m->addMonth()) {
            $months[$m->format('Y-m')] = $m->format('M');
        }
        $byDay = $enquiries()->whereBetween('enquiry_date', [$from, $to])->selectRaw('enquiry_date as d, count(*) as n')->groupBy('enquiry_date')->pluck('n', 'd');
        // POs against CRM quotations only (29/10/2026): a direct PO has no enquiry, so it is not a converted lead.
        $poByDay = CustomerPo::where('unit_id', $unit->id)->where('source', CustomerPo::CRM)->whereBetween('po_date', [$from, $to])->selectRaw('po_date as d, count(*) as n, sum(po_value) as v')->groupBy('po_date')->get();
        $enqMonth = array_fill_keys(array_keys($months), 0);
        $poMonth = array_fill_keys(array_keys($months), 0);
        $poValue = 0.0;
        foreach ($byDay as $d => $n) {
            $enqMonth[substr((string) $d, 0, 7)] += $n;
        }
        foreach ($poByDay as $row) {
            $poMonth[substr((string) $row->d, 0, 7)] += $row->n;
            $poValue += (float) $row->v;
        }
        $enqTotal = array_sum($enqMonth);
        $poTotal = array_sum($poMonth);

        $open = $enquiries()->open();
        $today = today();
        $monthEnd = $today->copy()->endOfMonth()->toDateString().' 23:59:59';

        // Lead ageing: open leads by days since the enquiry date.
        $ageing = array_fill_keys(array_keys(self::AGEING), 0);
        foreach ((clone $open)->selectRaw('enquiry_date as d, count(*) as n')->groupBy('enquiry_date')->pluck('n', 'd') as $d => $n) {
            $days = (int) Carbon::parse($d)->diffInDays($today);
            foreach (self::AGEING as $label => [$min, $max]) {
                if ($days >= $min && $days <= $max) {
                    $ageing[$label] += $n;
                    break;
                }
            }
        }

        $bySource = $enquiries()->whereBetween('enquiry_date', [$from, $to])->selectRaw('lead_source_id as k, count(*) as n')->groupBy('lead_source_id')->pluck('n', 'k');
        $byCategory = $enquiries()->whereBetween('enquiry_date', [$from, $to])->selectRaw('service_category_id as k, count(*) as n')->groupBy('service_category_id')->pluck('n', 'k');
        // Counts by id into counts by name, with one lookup of the names.
        $label = function ($counts, string $model) {
            $names = $model::whereIn('id', $counts->keys()->filter()->all())->pluck('name', 'id');

            return $counts->mapWithKeys(fn ($n, $k) => [($names[$k] ?? 'Not given') => (int) $n])->all();
        };

        $list = fn ($q) => $q->with(['party:id,name', 'prospect:id,name', 'assignee:id,name'])->limit(self::LIST_SIZE)->get();

        return [
            'kpis' => [
                'enquiries' => $enqTotal,
                'pos' => $poTotal,
                'po_value' => $poValue,
                'conversion' => $enqTotal ? round($poTotal / $enqTotal * 100, 1) : 0,
                'open' => (clone $open)->count(),
                'hot' => (clone $open)->where('lead_rating', LeadRating::Hot->value)->count(),
                'po_pending' => Quotation::where('unit_id', $unit->id)->poPending()->count(),
                'closing' => (clone $open)->whereBetween('expected_closing_date', [$today->copy()->startOfMonth()->toDateString(), $monthEnd])->count(),
                'closing_value' => (float) (clone $open)->whereBetween('expected_closing_date', [$today->copy()->startOfMonth()->toDateString(), $monthEnd])->sum('estimated_value'),
            ],
            'monthly' => Charts::bars(array_values($months), ['Enquiries received' => array_values($enqMonth), 'PO received' => array_values($poMonth)]),
            'monthlyTable' => array_map(fn ($k, $m) => ['month' => $m, 'enquiries' => $enqMonth[$k], 'pos' => $poMonth[$k]], array_keys($months), $months),
            'sources' => Charts::pie($label($bySource, LeadSource::class)),
            'categories' => Charts::pie($label($byCategory, ServiceCategory::class)),
            'ageing' => $ageing,
            'ageingMax' => max(1, ...array_values($ageing)),
            'hot' => $list((clone $open)->where('lead_rating', LeadRating::Hot->value)->orderByRaw('next_action_date is null')->orderBy('next_action_date')->orderBy('id')),
            'closing' => $list((clone $open)->whereBetween('expected_closing_date', [$today->copy()->startOfMonth()->toDateString(), $monthEnd])->orderBy('expected_closing_date')->orderBy('id')),
            'poPending' => Quotation::where('unit_id', $unit->id)->poPending()->with(['party:id,name', 'prospect:id,name'])->orderBy('id')->limit(self::LIST_SIZE)->get(),
            'year' => $year->label(),
        ];
    }
}
