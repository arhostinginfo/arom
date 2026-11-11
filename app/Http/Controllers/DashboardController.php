<?php

namespace App\Http\Controllers;

use App\Services\CrmCounts;
use App\Services\CrmDashboard;
use App\Support\WorkContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard (CRM changes, Sat 13/10/2026): the CRM dashboard for the unit on the top bar and the current
 * financial year, for users who can see enquiries. Modules not built yet add their tiles when they are built.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, CrmDashboard $dashboard, CrmCounts $counts): View
    {
        $unit = WorkContext::unit();
        $crm = $unit && $request->user()->can('enquiry.view');

        return view('dashboard', [
            'd' => $crm ? $dashboard->data($unit, WorkContext::financialYear()) : null,
            // Follow-ups due: the same list as the bell, the red pop up and Enquiry list › Follow-ups due (UX review M6).
            'due' => $crm ? app(\App\Services\CrmAlerts::class)->due($request->user(), $unit) : null,
            // CRM page counts, one tile per CRM page the user may open, each a link to that page (22/10/2026).
            'crmTiles' => $counts->tiles($request->user(), $unit),
            'unit' => $unit,
        ]);
    }
}
