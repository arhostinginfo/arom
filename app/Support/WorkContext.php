<?php

namespace App\Support;

use App\Models\FinancialYear;
use App\Models\Unit;
use App\Models\User;

/** The unit chosen on the top bar (decision D10) and the current financial year (D20). */
class WorkContext
{
    public static function unit(?User $user = null): ?Unit
    {
        $user ??= auth()->user();
        $units = $user->units;

        return $units->firstWhere('id', session('unit_id')) ?? $units->first();
    }

    public static function financialYear(): FinancialYear
    {
        return FinancialYear::forDate(now());
    }

    /** Unit ids the user may see records of. */
    public static function unitIds(?User $user = null): array
    {
        $user ??= auth()->user();

        return $user->units->pluck('id')->all();
    }
}
