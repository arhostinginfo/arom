<?php

namespace App\Http\Controllers;

use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/*
| Pages of modules that are not built yet. Each shows the clickable prototype's screen
| for that page with its sample data, inside the real app shell, so the menu and
| permissions can be reviewed now. Nothing on these pages is saved.
*/
class AppPageController extends Controller
{
    public function show(Request $request, string $page = 'reports', ?string $arg = null): View|RedirectResponse
    {
        // Pages and reports that are built now open their real screens.
        if ($route = config("nursery.built_pages.$page")) {
            // A built page whose menu item still names its sample screen (Purchase Order, 27/10/2026) keeps that
            // item's right here, so /app/<page> without it is refused as before instead of redirecting.
            // "preview_perm" (rights separated by |) when the built page lets in more than the menu item's right.
            $sub = collect(config('nursery.nav'))->flatMap(fn ($i) => $i['subs'] ?? [])->firstWhere('preview', $page);
            $perm = $sub['preview_perm'] ?? $sub['perm'] ?? null;
            abort_if($perm && ! $request->user()->canAny(explode('|', $perm)), 403);

            return redirect()->route($route);
        }
        if ($page === 'reports' && $arg) {
            [$g, $i] = array_pad(explode('-', $arg, 2), 2, null);
            if ($route = config("nursery.report_routes.$g.$i")) {
                return redirect()->route($route, array_filter(['in' => $request->query('in')]));
            }
        }

        $user = $request->user();
        $perm = Menu::permissionForPreview($page, $arg);
        abort_unless($perm === null || $user->can($perm), 403);

        if ($page === 'reports' && $arg) {
            $group = explode('-', $arg)[0];
            $rp = config('nursery.report_perms')[$group] ?? null;
            // The group's module must be on too (01/11/2026), as in the menu.
            abort_unless($rp && in_array($group, Menu::reportGroups($user), true), 403);
        }

        return view('app.preview', [
            'page' => $page,
            'arg' => $arg,
            'title' => Menu::previewTitle($page, $arg),
        ]);
    }
}
