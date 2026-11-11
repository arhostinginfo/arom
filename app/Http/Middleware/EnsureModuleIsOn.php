<?php

namespace App\Http\Middleware;

use App\Support\Modules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Refuses the pages of an optional part of the CRM flow while it is switched off (route middleware "module:boq"). */
class EnsureModuleIsOn
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (! Modules::on($module)) {
            Modules::refuse($module);
        }

        return $next($request);
    }
}
