<?php

namespace App\Http\Controllers\Reports;

use App\Enums\EnquiryStatus;
use App\Enums\LeadRating;
use App\Enums\Market;
use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\Prospect;
use App\Services\CrmDashboard;
use App\Support\Csv;
use App\Support\WorkContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/*
| CRM reports: Prospects, Enquiries, Lead ageing and Lead report (lead to PO). Clicking a report opens its
| download options on the same page (OTHER POINTS A21); the file is made on the server (A20) as CSV or PDF
| (decision S3). Filters: From date, To date (default the current financial year), Unit, plus Domestic /
| Export for Prospects and Status / Lead rating for the enquiry reports (CRM changes, Sat 13/10/2026).
*/
class CrmReportController extends Controller
{
    /** Upper bound of the PDF row cap; the cap of a report is ReportPdf::maxRows() of its column count. */
    public const PDF_MAX_ROWS = \App\Support\ReportPdf::MAX_ROWS;

    /** Report columns that hold money. */
    private const MONEY_COLUMNS = ['Estimated value', 'Quotation total', 'PO value'];

    private const REPORTS = [
        'prospects' => ['title' => 'Prospects', 'date' => 'Added on'],
        'enquiries' => ['title' => 'Enquiries', 'date' => 'Enquiry date'],
        'lead-ageing' => ['title' => 'Lead ageing report', 'date' => 'Enquiry date'],
        'lead-report' => ['title' => 'Lead report (lead to PO)', 'date' => 'Enquiry date'],
    ];

    public function prospects(): View
    {
        return $this->panel('prospects');
    }

    public function enquiries(): View
    {
        return $this->panel('enquiries');
    }

    public function leadAgeing(): View
    {
        return $this->panel('lead-ageing');
    }

    public function leadReport(): View
    {
        return $this->panel('lead-report');
    }

    public function download(Request $request, string $report): Response
    {
        abort_unless(isset(self::REPORTS[$report]), 404);
        $f = $request->validate([
            'from' => ['required', 'date_format:d/m/Y'],
            'to' => ['required', 'date_format:d/m/Y'],
            'unit' => ['nullable', 'integer', Rule::in(WorkContext::unitIds())],
            'market' => ['nullable', Rule::enum(Market::class)],
            'status' => ['nullable', Rule::enum(EnquiryStatus::class)],
            'rating' => ['nullable', Rule::enum(LeadRating::class)],
            'format' => ['required', Rule::in(['csv', 'pdf'])],
        ], [
            'from.date_format' => 'Enter From date as dd/mm/yyyy.',
            'to.date_format' => 'Enter To date as dd/mm/yyyy.',
        ]);
        $from = Carbon::createFromFormat('d/m/Y', $f['from'])->startOfDay();
        $to = Carbon::createFromFormat('d/m/Y', $f['to'])->endOfDay();
        if ($to->lt($from)) {
            return back()->withErrors(['to' => 'To date must be on or after From date.'])->withInput();
        }
        $units = ! empty($f['unit']) ? [(int) $f['unit']] : WorkContext::unitIds();

        [$headers, $query, $map] = match ($report) {
            'prospects' => $this->prospectRows($from, $to, $units, empty($f['unit']), $f['market'] ?? null),
            'enquiries' => $this->enquiryRows($from, $to, $units, $f['status'] ?? null, $f['rating'] ?? null),
            'lead-ageing' => $this->ageingRows($from, $to, $units, $f['rating'] ?? null),
            'lead-report' => $this->leadRows($from, $to, $units, $f['status'] ?? null, $f['rating'] ?? null),
        };

        $name = \Illuminate\Support\Str::slug(self::REPORTS[$report]['title']).'-'.$from->format('Ymd').'-'.$to->format('Ymd');
        if ($f['format'] === 'csv') {
            // Streamed in chunks of 1,000 by id (keyset, no growing OFFSET), so a large CSV never loads every
            // row into memory (database review DB-M6/M7). Rows come in the order they were added (decision C13).
            return Csv::download($name.'.csv', $headers, $query->reorder()->lazyById(1000)->map($map));
        }

        // A PDF is built in memory, so it is limited by its number of cells: at most PDF_MAX_ROWS rows (decision C9).
        $cap = \App\Support\ReportPdf::maxRows(count($headers));
        if ((clone $query)->count() > $cap) {
            return back()->withErrors(['format' => \App\Support\ReportPdf::tooMany($cap)])->withInput();
        }
        // Money in Indian grouping in the PDF (1,06,660.20); the CSV keeps plain numbers so Excel can add them up.
        $money = array_keys(array_intersect($headers, self::MONEY_COLUMNS));
        $rows = $query->get()->map($map)->map(function (array $row) use ($money) {
            foreach ($money as $i) {
                if ($row[$i] !== null && $row[$i] !== '') {
                    $row[$i] = \App\Support\Money::indian($row[$i], 2);
                }
            }

            return $row;
        })->all();
        $unitNames = $request->user()->units->whereIn('id', $units)->pluck('name')->implode(', ');

        return Pdf::loadView('pdf.report', [
            'title' => self::REPORTS[$report]['title'],
            'filters' => array_filter([
                self::REPORTS[$report]['date'] => $f['from'].' to '.$f['to'],
                'Unit' => $unitNames,
                'Domestic / Export' => $f['market'] ?? null,
                'Status' => $f['status'] ?? null,
                'Lead rating' => $f['rating'] ?? null,
            ]),
            'headers' => $headers,
            'rows' => $rows,
        ])->setPaper('a4', 'landscape')->setOption('isFontSubsettingEnabled', true)->download($name.'.pdf');
    }

    private function panel(string $report): View
    {
        $year = WorkContext::financialYear();

        return view('reports.crm', [
            'report' => $report,
            'title' => self::REPORTS[$report]['title'],
            'dateLabel' => self::REPORTS[$report]['date'],
            'from' => $year->starts_on->format('d/m/Y'),
            'to' => $year->ends_on->format('d/m/Y'),
            'units' => auth()->user()->units,
            'statuses' => EnquiryStatus::values(),
            'ratings' => LeadRating::values(),
        ]);
    }

    private function prospectRows(Carbon $from, Carbon $to, array $units, bool $allUnits, ?string $market): array
    {
        $headers = ['Prospect name', 'Domestic / Export', 'Contact person', 'Position', 'Contact no 1', 'Contact no 2', 'Email', 'GSTIN', 'Industry type', 'Address', 'Unit', 'Added by', 'Added on'];
        $rows = Prospect::query()
            ->with(['unit', 'creator'])
            ->whereBetween('created_at', [$from, $to])
            // Prospects added by a user with no unit show under All units.
            ->where(fn ($w) => $w->whereIn('unit_id', $units)->when($allUnits, fn ($n) => $n->orWhereNull('unit_id')))
            ->when($market, fn ($w) => $w->where('market', $market))
            ->orderBy('name')->orderBy('id');
        $map = fn (Prospect $p) => [
            $p->name, $p->market->value, $p->contact_person, $p->position, $p->phone_1, $p->phone_2, $p->email,
            $p->gstin, $p->industry_type, $p->address, $p->unit?->name, $p->creator?->name, Csv::date($p->created_at),
        ];

        return [$headers, $rows, $map];
    }

    /** The enquiries (leads) with every field of the Enquiry page. */
    private function enquiryRows(Carbon $from, Carbon $to, array $units, ?string $status, ?string $rating): array
    {
        $headers = ['Enquiry no', 'Enquiry date', 'Source', 'Sub-source / Reference', 'Competitor details', 'Prospect type', 'Company / Client name', 'Industry', 'Industry type',
            'City', 'Address', 'Website', 'Contact name', 'Contact no', 'Service type', 'Approx. requirement', 'Estimated value', 'Expected closing date', 'Lead rating',
            'Lead assigned to', 'Next action', 'Next action date', 'Remarks', 'Status', 'Unit'];
        $rows = $this->enquiryQuery($from, $to, $units, $status, $rating)
            ->with(['source', 'industryType', 'city', 'serviceCategory', 'assignee', 'party', 'prospect', 'unit'])
            ->orderBy('enquiry_date')->orderBy('id');
        $map = fn (Enquiry $e) => [
            $e->enquiry_no, Csv::date($e->enquiry_date), $e->source?->name, $e->sub_source, $e->competitor_details, $e->prospect_type?->value, $e->clientName(),
            $e->industry, $e->industryType?->name, $e->city?->name, $e->address, $e->website, $e->contact_name, $e->contact_no, $e->serviceCategory?->name,
            $e->approx_requirement, $e->estimated_value, Csv::date($e->expected_closing_date), $e->lead_rating?->value, $e->assignee?->name,
            $e->next_action, Csv::date($e->next_action_date), $e->remarks, $e->status->value, $e->unit?->name,
        ];

        return [$headers, $rows, $map];
    }

    /** Lead ageing: open leads, how many days since the enquiry date, and their ageing bucket. */
    private function ageingRows(Carbon $from, Carbon $to, array $units, ?string $rating): array
    {
        $headers = ['Enquiry no', 'Enquiry date', 'Age (days)', 'Ageing', 'Company / Client name', 'Source', 'Service type', 'Lead rating', 'Lead assigned to', 'Next action', 'Next action date', 'Estimated value', 'Unit'];
        $rows = $this->enquiryQuery($from, $to, $units, EnquiryStatus::Open->value, $rating)
            ->with(['source', 'serviceCategory', 'assignee', 'party', 'prospect', 'unit'])
            ->orderBy('enquiry_date')->orderBy('id');
        $today = today();
        $map = function (Enquiry $e) use ($today) {
            $days = (int) $e->enquiry_date->diffInDays($today);
            $bucket = collect(CrmDashboard::AGEING)->search(fn ($r) => $days >= $r[0] && $days <= $r[1]);

            return [
                $e->enquiry_no, Csv::date($e->enquiry_date), $days, $bucket, $e->clientName(), $e->source?->name, $e->serviceCategory?->name,
                $e->lead_rating?->value, $e->assignee?->name, $e->next_action, Csv::date($e->next_action_date), $e->estimated_value, $e->unit?->name,
            ];
        };

        return [$headers, $rows, $map];
    }

    /**
     * Lead report: each lead with all its activity up to the PO (site visits, follow-ups, quotation, PO).
     * The two site visit columns are left out while Site Visit / Requirement Gathering is switched off.
     */
    private function leadRows(Carbon $from, Carbon $to, array $units, ?string $status, ?string $rating): array
    {
        $visits = \App\Support\Modules::on('site_visit');
        $headers = array_merge(['Enquiry no', 'Enquiry date', 'Company / Client name', 'Source', 'Service type', 'Lead rating', 'Lead assigned to', 'Status'],
            $visits ? ['Site visits', 'Last site visit'] : [],
            ['Follow-ups', 'Last follow-up', 'Quotation no', 'Rev no', 'Quotation status', 'Quotation total',
                'PO / Agreement no', 'PO date', 'PO value', 'Next action', 'Next action date', 'Unit']);
        $rows = $this->enquiryQuery($from, $to, $units, $status, $rating)
            ->with(['source', 'serviceCategory', 'assignee', 'party', 'prospect', 'unit',
                'quotations' => fn ($q) => $q->latestRevisions()->select(['id', 'enquiry_id', 'quotation_no', 'revision_no', 'status', 'grand_total']),
                'customerPos' => fn ($q) => $q->select(['id', 'enquiry_id', 'po_no', 'po_date', 'po_value'])])
            ->when($visits, fn ($w) => $w->withCount('siteVisits')->withMax('siteVisits', 'visit_at'))
            ->withCount('activities')
            ->withMax('activities', 'activity_date')
            ->orderBy('enquiry_date')->orderBy('id');
        $map = function (Enquiry $e) use ($visits) {
            $q = $e->quotations->sortByDesc('id')->first();
            $po = $e->customerPos->sortByDesc('id')->first();

            return [
                $e->enquiry_no, Csv::date($e->enquiry_date), $e->clientName(), $e->source?->name, $e->serviceCategory?->name, $e->lead_rating?->value, $e->assignee?->name, $e->status->value,
                ...($visits ? [$e->site_visits_count, $e->site_visits_max_visit_at ? Csv::date(Carbon::parse($e->site_visits_max_visit_at)) : null] : []),
                $e->activities_count, $e->activities_max_activity_date ? Csv::date(Carbon::parse($e->activities_max_activity_date)) : null,
                $q?->quotation_no, $q?->revision_no, $q?->status->value, $q?->grand_total,
                $po?->po_no, Csv::date($po?->po_date), $po?->po_value,
                $e->next_action, Csv::date($e->next_action_date), $e->unit?->name,
            ];
        };

        return [$headers, $rows, $map];
    }

    private function enquiryQuery(Carbon $from, Carbon $to, array $units, ?string $status, ?string $rating)
    {
        return Enquiry::query()
            ->whereIn('unit_id', $units)
            ->whereBetween('enquiry_date', [$from->toDateString(), $to->toDateTimeString()])
            ->when($status, fn ($w) => $w->where('status', $status))
            ->when($rating, fn ($w) => $w->where('lead_rating', $rating));
    }
}
