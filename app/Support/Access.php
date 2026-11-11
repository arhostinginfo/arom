<?php

namespace App\Support;

/** Reads the page and permission list from config/nursery.php. */
class Access
{
    /** All permission names, e.g. "users.view". */
    public static function all(): array
    {
        $names = [];
        foreach (config('nursery.pages') as $pages) {
            foreach ($pages as $key => $page) {
                foreach ($page['actions'] as $action) {
                    $names[] = "$key.$action";
                }
            }
        }

        return $names;
    }

    /** Expand "quotation.*" style entries into real permission names. */
    public static function expand(array|string $patterns): array
    {
        if ($patterns === '*') {
            return self::all();
        }

        return array_values(array_filter(self::all(), function ($name) use ($patterns) {
            foreach ($patterns as $p) {
                if ($p === $name || (str_ends_with($p, '.*') && str_starts_with($name, substr($p, 0, -1)))) {
                    return true;
                }
            }

            return false;
        }));
    }

    /** Readable label, e.g. "Users list · Add". */
    public static function label(string $name): string
    {
        [$key, $action] = explode('.', $name, 2);
        foreach (config('nursery.pages') as $pages) {
            if (isset($pages[$key])) {
                return self::pageLabel($pages[$key]).' · '.(config('nursery.actions')[$action] ?? $action);
            }
        }

        return $name;
    }

    /**
     * The optional part (config 'modules') the page of a permission belongs to, e.g. "site_visit" for
     * "site-visit.view"; null for every other ability. Used by the gate to refuse a switched-off part everywhere.
     */
    public static function moduleOf(string $ability): ?string
    {
        static $map = null;
        $map ??= collect(config('nursery.pages'))->collapse()->map(fn ($page) => $page['module'] ?? null)->filter()->all();
        $modules = (array) ($map[explode('.', $ability, 2)[0]] ?? []);

        // A page of several parts (01/11/2026): the first one that is off, so the gate refuses it while any is off.
        return $modules ? (Modules::offOf($modules) ?? $modules[0]) : null;
    }

    /** A page's name on Access rights: "Quotation, BOQ and costing" names only the optional parts that are on. */
    public static function pageLabel(array $page): string
    {
        return isset($page['label_parts']) ? Modules::label($page['label'], $page['label_parts']) : $page['label'];
    }

    /** The page list of Access rights: group => key => page, without pages of an optional part that is off. */
    public static function pages(): array
    {
        $out = [];
        foreach (config('nursery.pages') as $group => $pages) {
            foreach ($pages as $key => $page) {
                if (Modules::allows($page['module'] ?? null)) {
                    $out[$group][$key] = $page;
                }
            }
        }

        return $out;
    }

    /** Permissions of pages whose optional part is off: not shown on Access rights, so not in what the form sends. */
    public static function hidden(): array
    {
        $shown = [];
        foreach (self::pages() as $pages) {
            foreach ($pages as $key => $page) {
                foreach ($page['actions'] as $action) {
                    $shown[] = "$key.$action";
                }
            }
        }

        return array_values(array_diff(self::all(), $shown));
    }

    /**
     * What Access rights sent plus the hidden permissions already held, so saving a role or user while a part is
     * off keeps its rights for when it is switched back on.
     */
    public static function keepHidden(array $sent, array $current): array
    {
        return array_values(array_unique(array_merge($sent, array_intersect($current, self::hidden()))));
    }

    /** A page's Add, Edit or Delete needs its View (decision U5). */
    public static function withViews(array $names): array
    {
        $all = self::all();
        $names = array_values(array_unique($names));
        foreach ($names as $p) {
            $view = explode('.', $p)[0].'.view';
            if (in_array($view, $all, true) && ! in_array($view, $names, true)) {
                $names[] = $view;
            }
        }

        return $names;
    }

    /** Permissions this user may give to others: everything for an Admin, otherwise what they hold (decision U8). */
    public static function grantable(\App\Models\User $user): array
    {
        return $user->isAdmin() ? self::all() : $user->getAllPermissions()->pluck('name')->all();
    }

    /** The permissions of $names that $actor may not give: empty when they hold them all (decision U9). */
    public static function notGrantable(\App\Models\User $actor, array $names): array
    {
        return array_values(array_diff(array_unique($names), self::grantable($actor)));
    }

    /** Permissions a role gives: every permission for Admin. */
    public static function ofRole(string $role): array
    {
        if ($role === Roles::ADMIN) {
            return self::all();
        }

        return \Spatie\Permission\Models\Role::findByName($role)->permissions->pluck('name')->all();
    }

    /**
     * Decision U9: a non-Admin may change, reset the password of, deactivate or delete only users
     * whose rights they hold themselves. Admin users stay Admin-only (U8).
     */
    public static function canManage(\App\Models\User $actor, \App\Models\User $target): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }
        if ($target->isAdmin()) {
            return false;
        }

        return self::notGrantable($actor, $target->getAllPermissions()->pluck('name')->all()) === [];
    }

    public static function labels(array $names): array
    {
        return array_map([self::class, 'label'], $names);
    }
}
