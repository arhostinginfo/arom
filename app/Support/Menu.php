<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/** Builds the left menu for the signed-in user from config/nursery.php. */
class Menu
{
    public static function permissionForPreview(string $page, ?string $arg): ?string
    {
        if ($page === 'reports') {
            return null; // landing page (S1); each report group is checked separately
        }
        foreach (config('nursery.nav') as $item) {
            if (($item['preview'] ?? null) === $page) {
                return $item['perm'];
            }
            foreach ($item['subs'] ?? [] as $sub) {
                $pages = array_merge([$sub['preview'] ?? null], $sub['also'] ?? []);
                if (in_array($page, $pages, true) && ($sub['arg'] ?? null) === ($page === 'sales' ? ($arg === 'so' ? 'so' : null) : ($sub['arg'] ?? null))) {
                    return $sub['perm'];
                }
            }
        }
        abort(404);
    }

    public static function previewTitle(string $page, ?string $arg): string
    {
        if ($page === 'reports' && $arg) {
            [$g, $i] = array_pad(explode('-', $arg, 2), 2, 0);

            return config("nursery.reports.$g")[(int) $i] ?? 'Reports';
        }
        if ($page === 'master' && $arg) {
            return $arg;
        }
        foreach (config('nursery.nav') as $item) {
            if (($item['preview'] ?? null) === $page) {
                return $item['title'];
            }
            foreach ($item['subs'] ?? [] as $sub) {
                if (($sub['preview'] ?? null) === $page && ($sub['arg'] ?? null) === ($page === 'sales' ? ($arg === 'so' ? 'so' : null) : ($sub['arg'] ?? null))) {
                    return $sub['title'];
                }
            }
        }

        return 'Nursery';
    }

    /** Report groups the user may open (OTHER POINTS A17). */
    public static function reportGroups(User $user): array
    {
        return array_keys(array_filter(config('nursery.report_perms'), fn ($perm) => $user->can($perm)));
    }

    public static function subUrl(array $sub): string
    {
        if (isset($sub['route'])) {
            return route($sub['route'], $sub['params'] ?? []);
        }

        return route('app.page', array_filter(['page' => $sub['preview'], 'arg' => $sub['arg'] ?? null]));
    }

    /** Menu groups with only the items this user may see, and which item is open. */
    public static function build(User $user, Request $request, ?array $preview): array
    {
        $groups = [];
        $reportGroups = self::reportGroups($user);
        $routeName = $request->route()?->getName();
        $in = $request->query('in');

        foreach (config('nursery.nav') as $item) {
            $entry = ['key' => $item['key'], 'icon' => $item['icon'], 'title' => $item['title'], 'subs' => [], 'reports' => [], 'on' => false];

            if (isset($item['subs'])) {
                foreach ($item['subs'] as $sub) {
                    // Hidden without the right; a page of a switched-off part has no right (the gate refuses it).
                    if (! $user->can($sub['perm'])) {
                        continue;
                    }
                    // A step that is a filtered list (CRM 4 Approval, 5 Quotation sent) is open only on that filter.
                    $on = isset($sub['route'])
                        ? $routeName && $request->routeIs($sub['match']) && collect($sub['params'] ?? [])->every(fn ($v, $k) => $request->query($k) === $v)
                        : $preview && in_array($preview['page'], array_merge([$sub['preview']], $sub['also'] ?? []), true)
                            && ($preview['page'] !== 'sales' || (($preview['arg'] === 'so') === (($sub['arg'] ?? null) === 'so')));
                    $title = ($sub['title_from'] ?? null) === 'quotation_step' ? strtok($sub['title'], ' ').' '.CrmFlow::quotationStep() : $sub['title'];
                    $entry['subs'][] = ['title' => $title, 'url' => self::subUrl($sub), 'on' => $on, 'filtered' => isset($sub['params'])];
                    $entry['on'] = $entry['on'] || $on;
                }
                // On a filtered step, the broader item of the same pages (3 Quotation) is not marked as well.
                if (collect($entry['subs'])->contains(fn ($s) => $s['on'] && $s['filtered'])) {
                    $entry['subs'] = array_map(fn ($s) => $s['filtered'] ? $s : ['on' => false] + $s, $entry['subs']);
                }
                if (isset($item['reports']) && in_array($item['reports'], $reportGroups, true)) {
                    $entry['reports'] = self::reportLinks($item['reports'], $preview, $item['key']);
                    if ($preview && $preview['page'] === 'reports' && $in === $item['key'] && str_starts_with((string) $preview['arg'], $item['reports'].'-')) {
                        $entry['on'] = true;
                    }
                    if (collect($entry['reports'])->contains('on', true)) {
                        $entry['on'] = true;
                    }
                }
                if (! $entry['subs'] && ! $entry['reports']) {
                    continue;
                }
                $entry['url'] = $entry['subs'][0]['url'] ?? $entry['reports'][0]['url'];
            } elseif ($item['key'] === 'reports') {
                $entry['url'] = route('app.page', ['page' => 'reports']);
                $entry['on'] = ($preview && $preview['page'] === 'reports' && ! $in) || (! $in && $request->routeIs('reports.*'));
                foreach ($reportGroups as $g) {
                    $entry['reports'][$g] = self::reportLinks($g, $in ? null : $preview, null);
                }
            } else {
                if (! $user->can($item['perm'])) {
                    continue;
                }
                if (isset($item['route'])) {
                    $entry['url'] = route($item['route']);
                    $entry['on'] = $request->routeIs($item['match']);
                } else {
                    $entry['url'] = route('app.page', ['page' => $item['preview']]);
                    $entry['on'] = $preview && $preview['page'] === $item['preview'];
                }
            }

            $groups[$item['group']][] = $entry;
        }

        return $groups;
    }

    /**
     * The horizontal menu bar (22/10/2026): one entry per top-level menu the user may see, taken from build() so the
     * permission, module and "open" rules live in one place. The selected menu is the open one (its page, one of
     * its reports, the Dashboard or the Reports page). Each menu's pages open from its drop-down on the bar; on a wide
     * screen the side menu then shows only the selected menu's reports.
     *
     * @return array{items: list<array{key: string, title: string, icon: string, url: string, on: bool, subs: list<array>}>, selected: ?string, reports: bool}
     */
    public static function bar(array $groups): array
    {
        $items = [];
        $selected = null;
        $reports = false;
        foreach ($groups as $entries) {
            foreach ($entries as $e) {
                $items[] = ['key' => $e['key'], 'title' => $e['title'], 'icon' => $e['icon'], 'url' => $e['url'], 'on' => $e['on'], 'subs' => $e['subs']];
                if ($selected === null && $e['on']) {
                    $selected = $e['key'];
                    $reports = (bool) $e['reports'];
                }
            }
        }

        return ['items' => $items, 'selected' => $selected, 'reports' => $reports];
    }

    private static function reportLinks(string $group, ?array $preview, ?string $in): array
    {
        $links = [];
        foreach (config("nursery.reports.$group") as $i => $title) {
            $key = "$group-$i";
            $route = config("nursery.report_routes.$group.$i");
            $links[] = [
                'title' => $title,
                'url' => $route
                    ? route($route, array_filter(['in' => $in]))
                    : route('app.page', array_filter(['page' => 'reports', 'arg' => $key, 'in' => $in])),
                'on' => $route
                    ? request()->routeIs($route) && request()->query('in') === $in
                    : $preview && $preview['page'] === 'reports' && $preview['arg'] === $key && request()->query('in') === $in,
            ];
        }

        return $links;
    }
}
