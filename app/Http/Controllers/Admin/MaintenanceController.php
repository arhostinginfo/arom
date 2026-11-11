<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;

// Clear every cache from the browser (11/11/2026), for a server without a terminal: config, routes, views, events,
// application cache and the permission cache. Admin only.
class MaintenanceController extends Controller
{
    public function clearCache(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $out = [];
        foreach (['optimize:clear', 'permission:cache-reset'] as $command) {
            $code = Artisan::call($command);
            $out[] = '$ php artisan '.$command.($code === 0 ? '' : "  (exit code $code)")."\n".trim(Artisan::output());
        }

        return response("All caches cleared.\n\n".implode("\n\n", $out)."\n", 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
