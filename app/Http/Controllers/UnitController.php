<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

// Unit chip in the top bar: switch between the units this user is allowed (decision D10).
class UnitController extends Controller
{
    public function switch(Request $request): RedirectResponse
    {
        $unit = $request->user()->units()->whereKey((int) $request->input('unit'))->firstOrFail();
        $request->session()->put('unit_id', $unit->id);

        return back()->with('toast', 'Switched to '.$unit->name);
    }
}
