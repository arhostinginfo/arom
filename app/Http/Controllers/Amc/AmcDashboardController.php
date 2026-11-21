<?php

namespace App\Http\Controllers\Amc;

use App\Http\Controllers\Controller;
use App\Services\Amc\AmcDashboard;
use App\Support\WorkContext;
use Illuminate\View\View;

/** AMC › Dashboard (21/11/2026): its own page, separate from the main Dashboard. */
class AmcDashboardController extends Controller
{
    public function __invoke(AmcDashboard $dashboard): View
    {
        $fy = WorkContext::financialYear();

        return view('amc.dashboard', ['d' => $dashboard->figures($fy), 'fy' => $fy]);
    }
}
