<?php

namespace App\Support;

use App\Services\ModuleSettings;

/** Short form for checks in code and Blade (@module('boq')): is this optional part of the CRM flow switched on? */
final class Modules
{
    /** True while the part is on. (The "po|crm" either-form of 01/11/2026 went with the Separate PO menu, 02/11/2026.) */
    public static function on(string $module): bool
    {
        return app(ModuleSettings::class)->on($module);
    }

    /**
     * Stop the request with "<part> is switched off" (404 status, so it reads as unavailable, not forbidden):
     * a bookmark or old link to a switched-off part never looks like a deleted record (UX review M3).
     */
    public static function refuse(string $module): never
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->view('errors.switched-off', [
            'part' => config("nursery.modules.$module.label", $module),
        ], 404));
    }

    /**
     * True when the page key (config 'pages' or 'page_fields') belongs to no optional part, or every part it belongs to is
     * on. A page may belong to several parts (01/11/2026: Site visit is part of CRM and has its own switch).
     */
    public static function allows(string|array|null $module): bool
    {
        return self::offOf($module) === null;
    }

    /** The first part of $module (one key or a list) that is switched off, or null when all are on. */
    public static function offOf(string|array|null $module): ?string
    {
        foreach ((array) $module as $m) {
            if (! self::on($m)) {
                return $m;
            }
        }

        return null;
    }

    /** "Quotation, BOQ and costing" while BOQ and costing are on, "Quotation and BOQ" with BOQ only, "Quotation" with neither. */
    public static function label(string $label, array $parts): string
    {
        $words = array_merge([$label], array_values(array_filter($parts, fn ($text, $module) => self::on($module), ARRAY_FILTER_USE_BOTH)));
        $last = array_pop($words);

        return $words ? implode(', ', $words).' and '.$last : $last;
    }
}
